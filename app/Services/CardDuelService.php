<?php

namespace App\Services;

use App\Exceptions\DuelException;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQrCode;
use App\Models\CardQuizState;
use App\Models\DuelMessageSetting;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\Duel\DuelAttempts;
use App\Services\Duel\DuelQuestionResult;
use App\Services\Duel\DuelResult;
use App\Services\Duel\DuelReviewItem;
use App\Services\Duel\DuelStart;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de los duelos (quiz) para obtener tarjetas.
 *
 * Toda la validación ocurre en el servidor:
 * - Se eligen las preguntas y nunca se envía cuál es la correcta.
 * - Se cuentan los reintentos y se aplica el tiempo de espera.
 * - Un duelo no tiene tiempo límite: queda pendiente hasta que se responde.
 * - La captura solo se crea si todas las respuestas son correctas.
 * - Las respuestas correctas solo se revelan al agotar los intentos,
 *   y solo si la rareza de la tarjeta lo permite.
 */
class CardDuelService
{
    /**
     * Iniciar (o reanudar) un duelo a partir del token de un código QR.
     *
     * Es la única forma de iniciar un duelo: la tarjeta se obtiene
     * del QR, nunca de un id enviado por la app.
     */
    public function startFromQr(User $user, string $token): DuelStart
    {
        $qrCode = CardQrCode::query()
            ->with('card')
            ->where('token', $token)
            ->first();

        if (! $qrCode || ! $qrCode->is_active) {
            throw DuelException::invalidQr();
        }

        if ($qrCode->isExpired()) {
            throw DuelException::expiredQr();
        }

        $qrCode->increment('scans_count', 1, [
            'last_scanned_at' => now(),
        ]);

        return $this->start($user, $qrCode->card);
    }

    /**
     * Iniciar un duelo o reanudar el que está pendiente.
     */
    private function start(User $user, Card $card): DuelStart
    {
        $this->ensureCardIsAvailable($card);

        $card->loadMissing(['raritySetting', 'species:id,species_category_id', 'species.category:id,name']);

        $capture = $user->cardCaptures()
            ->where('card_id', $card->id)
            ->first();

        if ($capture) {
            throw DuelException::alreadyOwned(
                new CardResource($card, owned: true)
            );
        }

        $result = DB::transaction(function () use ($user, $card) {
            $state = $this->lockState($user, $card);

            $this->releaseFinishedLock($state);

            /*
             * Si el administrador redujo los reintentos,
             * el usuario puede haberlos agotado sin bloqueo.
             */
            if (! $state->isLocked() && $this->retriesLeft($card, $state) === 0) {
                $state->update([
                    'locked_until' => now()->addMinutes($card->effective_cooldown_minutes),
                ]);
            }

            if ($state->isLocked()) {
                return DuelException::locked($this->attempts($card, $state));
            }

            /*
             * Reanudar el duelo pendiente para que el usuario
             * no pueda cambiar de preguntas volviendo a iniciar.
             */
            $attempt = $user->quizAttempts()
                ->where('card_id', $card->id)
                ->where('status', QuizAttempt::STATUS_PENDING)
                ->latest('id')
                ->first();

            /*
             * Sin tiempo límite, un duelo pendiente puede quedarse sin preguntas
             * si el administrador las borró; se reemplaza por uno nuevo.
             */
            if ($attempt && $this->servedQuestions($attempt)->isEmpty()) {
                $attempt->delete();
                $attempt = null;
            }

            if (! $attempt) {
                /*
                 * reorder() quita el orden por id de la relación;
                 * si no, siempre saldrían las primeras preguntas.
                 */
                $questionIds = $card->quizQuestions()
                    ->where('is_active', true)
                    ->reorder()
                    ->inRandomOrder()
                    ->limit($card->effective_questions_per_duel)
                    ->pluck('id')
                    ->all();

                if (! $questionIds) {
                    return DuelException::noQuestions();
                }

                $attempt = $user->quizAttempts()->create([
                    'card_id' => $card->id,
                    'question_ids' => $questionIds,
                    'status' => QuizAttempt::STATUS_PENDING,
                    'total_questions' => count($questionIds),
                ]);
            }

            return [$attempt, $state];
        });

        if ($result instanceof DuelException) {
            throw $result;
        }

        [$attempt, $state] = $result;

        return new DuelStart(
            attempt: $attempt,
            attempts: $this->attempts($card, $state),
            card: $card,
            questions: $this->presentQuestions($attempt),
        );
    }

    /**
     * Calificar un duelo.
     *
     * @param  array<int, array{question_id: int, answer_id: int}>  $answers
     */
    public function submit(
        User $user,
        QuizAttempt $attempt,
        array $answers,
        ?float $latitude = null,
        ?float $longitude = null
    ): DuelResult {
        $card = $attempt->card;

        $this->ensureCardIsAvailable($card);

        $card->loadMissing(['raritySetting', 'species:id,species_category_id', 'species.category:id,name']);

        $result = DB::transaction(function () use ($user, $attempt, $card, $answers, $latitude, $longitude) {
            $state = $this->lockState($user, $card);

            $attempt = QuizAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $attempt->isPending()) {
                return DuelException::attemptClosed();
            }

            $questions = $this->servedQuestions($attempt);

            if ($questions->isEmpty()) {
                return DuelException::noQuestions();
            }

            $results = $this->grade($attempt, $questions, $answers);

            if ($results === null) {
                return DuelException::invalidAnswers();
            }

            $correctCount = collect($results)
                ->filter(fn (DuelQuestionResult $result) => $result->isCorrect)
                ->count();

            $passed = $correctCount === $questions->count();

            $attempt->update([
                'status' => $passed
                    ? QuizAttempt::STATUS_PASSED
                    : QuizAttempt::STATUS_FAILED,
                'correct_count' => $correctCount,
                'submitted_at' => now(),
            ]);

            if ($passed) {
                CardCapture::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'card_id' => $card->id,
                    ],
                    [
                        'captured_at' => now(),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]
                );

                $state->delete();

                return new DuelResult(
                    outcome: DuelResult::OUTCOME_WON,
                    message: $this->resultMessage($card, DuelResult::OUTCOME_WON, $correctCount, $questions->count(), null),
                    results: $results,
                    attempts: null,
                    review: null,
                    card: $card,
                );
            }

            $this->registerFailure($card, $state);

            $attempts = $this->attempts($card, $state);

            $outcome = $attempts->left === 0
                ? DuelResult::OUTCOME_LOCKED
                : DuelResult::OUTCOME_RETRY;

            return new DuelResult(
                outcome: $outcome,
                message: $this->resultMessage($card, $outcome, $correctCount, $questions->count(), $attempts),
                results: $results,
                attempts: $attempts,
                review: $outcome === DuelResult::OUTCOME_LOCKED
                    ? $this->review($card, $attempt, $questions)
                    : null,
                card: null,
            );
        });

        if ($result instanceof DuelException) {
            throw $result;
        }

        return $result;
    }

    private function ensureCardIsAvailable(Card $card): void
    {
        if (! $card->is_active) {
            throw DuelException::cardNotAvailable();
        }
    }

    /**
     * Obtener y bloquear el estado del usuario para la tarjeta,
     * evitando condiciones de carrera entre peticiones simultáneas.
     */
    private function lockState(User $user, Card $card): CardQuizState
    {
        $state = CardQuizState::createOrFirst([
            'user_id' => $user->id,
            'card_id' => $card->id,
        ]);

        return CardQuizState::query()
            ->whereKey($state->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Sumar un intento fallido y bloquear al agotar los reintentos.
     *
     * El tiempo de espera cuenta desde el momento del fallo.
     */
    private function registerFailure(Card $card, CardQuizState $state): void
    {
        $state->failed_attempts++;

        if ($state->failed_attempts >= $card->effective_retries) {
            $state->locked_until = now()->addMinutes($card->effective_cooldown_minutes);
        }

        $state->save();
    }

    /**
     * Al terminar el tiempo de espera, los reintentos se reinician.
     */
    private function releaseFinishedLock(CardQuizState $state): void
    {
        if ($state->locked_until && ! $state->isLocked()) {
            $state->update([
                'failed_attempts' => 0,
                'locked_until' => null,
            ]);
        }
    }

    private function retriesLeft(Card $card, CardQuizState $state): int
    {
        return max(0, $card->effective_retries - $state->failed_attempts);
    }

    /**
     * Intentos del usuario para la tarjeta en la ronda actual.
     */
    private function attempts(Card $card, CardQuizState $state): DuelAttempts
    {
        $left = $this->retriesLeft($card, $state);

        return new DuelAttempts(
            max: $card->effective_retries,
            left: $left,
            lockedUntil: $state->isLocked() ? $state->locked_until : null,
        );
    }

    /**
     * Intentos para mostrar sin modificar nada (lista de tarjetas).
     *
     * Un bloqueo que ya terminó se muestra con los intentos reiniciados,
     * igual que al iniciar el siguiente duelo.
     */
    public function attemptsFor(Card $card, ?CardQuizState $state): DuelAttempts
    {
        if (! $state || ($state->locked_until && ! $state->isLocked())) {
            $state = new CardQuizState(['failed_attempts' => 0]);
        }

        return $this->attempts($card, $state);
    }

    /**
     * Mensaje configurado (de la tarjeta o el predeterminado) para el resultado del duelo,
     * con los marcadores reemplazados.
     *
     * @param  'won'|'retry'|'locked'  $outcome
     */
    private function resultMessage(
        Card $card,
        string $outcome,
        int $correctCount,
        int $totalQuestions,
        ?DuelAttempts $attempts
    ): string {
        $field = match ($outcome) {
            DuelResult::OUTCOME_WON => 'win_message',
            DuelResult::OUTCOME_RETRY => 'retry_message',
            default => 'locked_message',
        };

        /*
         * Mensaje propio de la tarjeta (si usa mensajes personalizados y no
         * está vacío); si no, el predeterminado configurado en el panel.
         */
        $template = (! $card->usesDefaultMessages() ? $card->{$field} : null)
            ?: DuelMessageSetting::current()->effective()[$field];

        return strtr($template, [
            '{tarjeta}' => $card->name,
            '{correctas}' => (string) $correctCount,
            '{total}' => (string) $totalQuestions,
            '{intentos_restantes}' => (string) ($attempts->left ?? 0),
            '{intentos_maximos}' => (string) $card->effective_retries,
            '{espera}' => self::readableMinutes($card->effective_cooldown_minutes),
        ]);
    }

    /**
     * Minutos como texto legible, p. ej. "1 día" o "2 horas 30 minutos".
     */
    public static function readableMinutes(int $minutes): string
    {
        return CarbonInterval::minutes($minutes)
            ->cascade()
            ->locale('es')
            ->forHumans(['parts' => 2]);
    }

    /**
     * Repaso con la respuesta correcta de cada pregunta del último duelo.
     *
     * Solo si la rareza de la tarjeta permite mostrarlas al agotar los intentos.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @return list<DuelReviewItem>|null
     */
    private function review(Card $card, QuizAttempt $attempt, Collection $questions): ?array
    {
        if (! $card->raritySetting?->reveal_answers_on_lockout) {
            return null;
        }

        return collect($attempt->question_ids)
            ->filter(fn (int $id) => $questions->has($id))
            ->map(fn (int $id) => [$id, $questions[$id]->answers->firstWhere('is_correct', true)])
            ->filter(fn (array $item) => $item[1] !== null)
            ->map(fn (array $item) => new DuelReviewItem(
                questionId: $item[0],
                correctAnswerId: $item[1]->id,
            ))
            ->values()
            ->all();
    }

    /**
     * Preguntas servidas en el duelo que todavía existen.
     *
     * @return Collection<int, QuizQuestion>
     */
    private function servedQuestions(QuizAttempt $attempt): Collection
    {
        return QuizQuestion::query()
            ->whereIn('id', $attempt->question_ids)
            ->with('answers:id,quiz_question_id,answer,is_correct')
            ->get()
            ->keyBy('id');
    }

    /**
     * Preguntas en el orden servido, con respuestas mezcladas.
     * El recurso de la API nunca incluye cuál es la correcta.
     *
     * @return SupportCollection<int, QuizQuestion>
     */
    private function presentQuestions(QuizAttempt $attempt): SupportCollection
    {
        $questions = $this->servedQuestions($attempt);

        return collect($attempt->question_ids)
            ->filter(fn (int $id) => $questions->has($id))
            ->map(fn (int $id) => $questions[$id]->setRelation(
                'answers',
                $questions[$id]->answers->shuffle()
            ))
            ->values();
    }

    /**
     * Calificar cada respuesta, en el orden en que se sirvieron las preguntas.
     *
     * Devuelve null si las respuestas no corresponden exactamente
     * a las preguntas servidas o a sus opciones.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @param  array<int, array{question_id: int, answer_id: int}>  $answers
     * @return list<DuelQuestionResult>|null
     */
    private function grade(QuizAttempt $attempt, Collection $questions, array $answers): ?array
    {
        $submitted = collect($answers)->mapWithKeys(fn (array $answer) => [
            (int) $answer['question_id'] => (int) $answer['answer_id'],
        ]);

        if (
            $submitted->count() !== count($answers) ||
            $submitted->keys()->sort()->values()->all() !== $questions->keys()->sort()->values()->all()
        ) {
            return null;
        }

        $results = [];

        $servedOrder = collect($attempt->question_ids)
            ->filter(fn (int $id) => $submitted->has($id));

        foreach ($servedOrder as $questionId) {
            $answerId = $submitted[$questionId];
            $answer = $questions[$questionId]->answers->firstWhere('id', $answerId);

            if (! $answer) {
                return null;
            }

            $results[] = new DuelQuestionResult(
                questionId: $questionId,
                answerId: $answerId,
                isCorrect: (bool) $answer->is_correct,
            );
        }

        return $results;
    }
}
