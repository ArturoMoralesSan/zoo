<?php

namespace App\Services;

use App\Exceptions\DuelException;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQuizState;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de los duelos (quiz) para obtener tarjetas.
 *
 * Toda la validación ocurre en el servidor:
 * - Se eligen las preguntas y nunca se envía cuál es la correcta.
 * - Se cuentan los reintentos y se aplica el tiempo de espera.
 * - La captura solo se crea si todas las respuestas son correctas.
 */
class CardDuelService
{
    /**
     * Minutos que tiene el usuario para responder un duelo.
     */
    public const ATTEMPT_TTL_MINUTES = 10;

    /**
     * Iniciar un duelo o reanudar el que está pendiente.
     *
     * @return array<string, mixed>
     */
    public function start(User $user, Card $card): array
    {
        $this->ensureCardIsAvailable($card);

        $card->loadMissing(['raritySetting', 'species:id,common_name,species_category_id', 'species.category:id,name']);

        $capture = $user->cardCaptures()
            ->where('card_id', $card->id)
            ->first();

        if ($capture) {
            throw DuelException::alreadyOwned(
                new CardResource($card, $capture->captured_at)
            );
        }

        $result = DB::transaction(function () use ($user, $card) {
            $state = $this->lockState($user, $card);

            $this->expireStaleAttempts($user, $card, $state);
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
                return DuelException::locked($state->locked_until);
            }

            /*
             * Reanudar el duelo pendiente para que el usuario
             * no pueda cambiar de preguntas volviendo a iniciar.
             */
            $attempt = $user->quizAttempts()
                ->where('card_id', $card->id)
                ->where('status', QuizAttempt::STATUS_PENDING)
                ->where('expires_at', '>', now())
                ->latest('id')
                ->first();

            if (! $attempt) {
                $questionIds = $card->quizQuestions()
                    ->where('is_active', true)
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
                    'expires_at' => now()->addMinutes(self::ATTEMPT_TTL_MINUTES),
                ]);
            }

            return [$attempt, $state];
        });

        if ($result instanceof DuelException) {
            throw $result;
        }

        [$attempt, $state] = $result;

        return [
            'attempt_id' => $attempt->id,
            'expires_at' => $attempt->expires_at->toJSON(),
            'retries_left' => $this->retriesLeft($card, $state),
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'card_image' => CardResource::publicUrl($card->card_image),
                'rarity' => $card->rarity,
                'species_name' => $card->species?->common_name,
                'stats' => [
                    'astucia' => $card->astucia,
                    'rapidez' => $card->rapidez,
                    'fuerza' => $card->fuerza,
                ],
            ],
            'questions' => $this->presentQuestions($attempt),
        ];
    }

    /**
     * Calificar un duelo.
     *
     * @param  array<int, array{question_id: int, answer_id: int}>  $answers
     * @return array<string, mixed>
     */
    public function submit(
        User $user,
        QuizAttempt $attempt,
        array $answers,
        ?float $latitude = null,
        ?float $longitude = null
    ): array {
        $card = $attempt->card;

        $this->ensureCardIsAvailable($card);

        $card->loadMissing(['raritySetting', 'species:id,common_name,species_category_id', 'species.category:id,name']);

        $result = DB::transaction(function () use ($user, $attempt, $card, $answers, $latitude, $longitude) {
            $state = $this->lockState($user, $card);

            $attempt = QuizAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $attempt->isPending()) {
                return DuelException::attemptClosed();
            }

            /*
             * Un duelo vencido cuenta como intento fallido.
             */
            if ($attempt->isExpired()) {
                $this->expireAttempt($attempt, $card, $state);

                return DuelException::attemptExpired(
                    $this->retriesLeft($card, $state),
                    $state->locked_until
                );
            }

            $questions = $this->servedQuestions($attempt);

            if ($questions->isEmpty()) {
                return DuelException::noQuestions();
            }

            $correctCount = $this->grade($questions, $answers);

            if ($correctCount === null) {
                return DuelException::invalidAnswers();
            }

            $passed = $correctCount === $questions->count();

            $attempt->update([
                'status' => $passed
                    ? QuizAttempt::STATUS_PASSED
                    : QuizAttempt::STATUS_FAILED,
                'correct_count' => $correctCount,
                'submitted_at' => now(),
            ]);

            if ($passed) {
                $capture = CardCapture::firstOrCreate(
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

                return [
                    'passed' => true,
                    'card' => new CardResource($card, $capture->captured_at),
                ];
            }

            $this->registerFailure($card, $state, now());

            /*
             * Solo se informa el puntaje, nunca cuáles
             * respuestas eran las correctas.
             */
            return [
                'passed' => false,
                'correct_count' => $correctCount,
                'total_questions' => $questions->count(),
                'retries_left' => $this->retriesLeft($card, $state),
                'locked_until' => $state->locked_until?->toJSON(),
            ];
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
     * Marcar como vencidos los duelos pendientes que ya expiraron.
     */
    private function expireStaleAttempts(User $user, Card $card, CardQuizState $state): void
    {
        $user->quizAttempts()
            ->where('card_id', $card->id)
            ->where('status', QuizAttempt::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (QuizAttempt $attempt) => $this->expireAttempt($attempt, $card, $state));
    }

    private function expireAttempt(QuizAttempt $attempt, Card $card, CardQuizState $state): void
    {
        $attempt->update([
            'status' => QuizAttempt::STATUS_EXPIRED,
        ]);

        $this->registerFailure($card, $state, $attempt->expires_at);
    }

    /**
     * Sumar un intento fallido y bloquear al agotar los reintentos.
     *
     * El tiempo de espera cuenta desde el momento del fallo.
     */
    private function registerFailure(Card $card, CardQuizState $state, CarbonInterface $failedAt): void
    {
        $state->failed_attempts++;

        if ($state->failed_attempts >= $card->effective_retries) {
            $state->locked_until = $failedAt->copy()
                ->addMinutes($card->effective_cooldown_minutes);
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
     * Preguntas en el orden servido, con respuestas mezcladas
     * y sin indicar cuál es la correcta.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentQuestions(QuizAttempt $attempt): array
    {
        $questions = $this->servedQuestions($attempt);

        return collect($attempt->question_ids)
            ->filter(fn (int $id) => $questions->has($id))
            ->map(fn (int $id) => [
                'id' => $id,
                'question' => $questions[$id]->question,
                'answers' => $questions[$id]->answers
                    ->shuffle()
                    ->map(fn ($answer) => [
                        'id' => $answer->id,
                        'answer' => $answer->answer,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Contar las respuestas correctas.
     *
     * Devuelve null si las respuestas no corresponden exactamente
     * a las preguntas servidas o a sus opciones.
     *
     * @param  Collection<int, QuizQuestion>  $questions
     * @param  array<int, array{question_id: int, answer_id: int}>  $answers
     */
    private function grade(Collection $questions, array $answers): ?int
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

        $correctCount = 0;

        foreach ($submitted as $questionId => $answerId) {
            $answer = $questions[$questionId]->answers->firstWhere('id', $answerId);

            if (! $answer) {
                return null;
            }

            if ($answer->is_correct) {
                $correctCount++;
            }
        }

        return $correctCount;
    }
}
