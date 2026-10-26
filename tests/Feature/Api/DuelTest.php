<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQrCode;
use App\Models\CardRaritySetting;
use App\Models\DuelMessageSetting;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DuelTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected Species $species;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = SpeciesCategory::create([
            'name' => 'Aves ' . uniqid(),
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Quetzal ' . uniqid(),
            'scientific_name' => 'Pharomachrus mocinno ' . uniqid(),
            'slug' => 'quetzal-' . uniqid(),
            'is_active' => true,
        ]);

        $this->card = $this->createCard([
            'duel_settings_source' => 'card',
            'questions_per_duel' => 2,
            'retries' => 2,
            'cooldown_minutes' => 60,
        ]);

        // Las pruebas esperan los mensajes integrados.
        DuelMessageSetting::current()->update([
            'win_message' => null,
            'retry_message' => null,
            'locked_message' => null,
        ]);

        Sanctum::actingAs($this->user);
    }

    private function createCard(array $attributes = [], int $questions = 3): Card
    {
        $card = Card::create(array_merge([
            'species_id' => $this->species->id,
            'name' => 'Quetzal Dorado',
            'rarity' => 'rara',
            'model_file' => 'cards/9/models/quetzal.glb',
            'generated_image' => 'cards/9/quetzal.png',
            'astucia' => 60,
            'rapidez' => 70,
            'fuerza' => 40,
            'is_active' => true,
        ], $attributes));

        for ($i = 0; $i < $questions; $i++) {
            $question = $card->quizQuestions()->create([
                'question' => "Pregunta {$i}",
                'is_active' => true,
            ]);

            $question->answers()->createMany([
                ['answer' => "Correcta {$i}", 'is_correct' => true, 'sort_order' => 0],
                ['answer' => "Incorrecta A {$i}", 'is_correct' => false, 'sort_order' => 1],
                ['answer' => "Incorrecta B {$i}", 'is_correct' => false, 'sort_order' => 2],
            ]);
        }

        $card->qrCodes()->create([
            'label' => 'Entrada',
            'is_active' => true,
        ]);

        return $card;
    }

    /**
     * Iniciar un duelo escaneando el primer QR de la tarjeta.
     */
    private function startDuel(?Card $card = null): TestResponse
    {
        $qrCode = ($card ?? $this->card)->qrCodes()->first();

        return $this->scan($qrCode->token);
    }

    private function scan(?string $code): TestResponse
    {
        return $this->postJson(route('api.duels.store'), ['code' => $code]);
    }

    /**
     * Construir las respuestas del duelo; $wrong indica cuántas fallar.
     */
    private function answersFor(TestResponse $duel, int $wrong = 0): array
    {
        return collect($duel->json('data.questions'))
            ->values()
            ->map(function (array $question, int $index) use ($wrong) {
                $answer = QuizAnswer::query()
                    ->where('quiz_question_id', $question['id'])
                    ->where('is_correct', $index >= $wrong)
                    ->first();

                return [
                    'question_id' => $question['id'],
                    'answer_id' => $answer->id,
                ];
            })
            ->all();
    }

    private function submit(TestResponse $duel, array $answers): TestResponse
    {
        return $this->postJson(
            route('api.duels.submit', $duel->json('data.attempt_id')),
            ['answers' => $answers]
        );
    }

    public function test_start_serves_configured_number_of_questions_without_correct_answers(): void
    {
        $duel = $this->startDuel()->assertOk();

        $this->assertCount(2, $duel->json('data.questions'));
        $this->assertCount(3, $duel->json('data.questions.0.answers'));
        $this->assertSame(['id', 'answer'], array_keys($duel->json('data.questions.0.answers.0')));
        $this->assertSame(['max' => 2, 'used' => 0, 'left' => 2, 'cooldown_minutes' => 60, 'locked_until' => null], $duel->json('data.attempts'));
        $this->assertSame(600, $duel->json('data.time_limit_seconds'));
        $this->assertSame(600, $duel->json('data.time_left_seconds'));
        $this->assertStringNotContainsString('is_correct', $duel->getContent());
        $this->assertArrayNotHasKey('model_url', $duel->json('data.card'));
        $this->assertStringEndsWith('cards/9/quetzal.png', $duel->json('data.card.card_image_url'));
    }

    public function test_start_returns_404_for_inactive_card(): void
    {
        $this->card->update(['is_active' => false]);

        $this->startDuel()->assertNotFound();
    }

    public function test_start_returns_409_with_card_when_already_owned(): void
    {
        CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
            'captured_at' => now(),
        ]);

        $this->startDuel()
            ->assertStatus(409)
            ->assertJsonPath('data.card.encountered', true);
    }

    public function test_start_returns_422_when_card_has_no_questions(): void
    {
        $card = $this->createCard([], questions: 0);

        $this->startDuel($card)->assertStatus(422);
    }

    public function test_starting_again_resumes_the_same_attempt(): void
    {
        $first = $this->startDuel()->assertOk();
        $second = $this->startDuel()->assertOk();

        $this->assertSame($first->json('data.attempt_id'), $second->json('data.attempt_id'));
        $this->assertEqualsCanonicalizing(
            collect($first->json('data.questions'))->pluck('id')->all(),
            collect($second->json('data.questions'))->pluck('id')->all()
        );
    }

    public function test_all_correct_answers_capture_the_card(): void
    {
        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'won')
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('message', '¡Ganaste el duelo! Quetzal Dorado ya es parte de tu colección.')
            ->assertJsonPath('data.attempts', null)
            ->assertJsonPath('data.review', null)
            ->assertJsonPath('data.card.attempts', null)
            ->assertJsonPath('data.card.id', $this->card->id)
            ->assertJsonPath('data.card.encountered', true)
            ->assertJsonPath('data.card.model_url', fn ($url) => str_ends_with($url, 'cards/9/models/quetzal.glb'));

        $this->assertDatabaseHas('card_captures', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
        ]);

        $this->startDuel()->assertStatus(409);
    }

    public function test_one_wrong_answer_returns_feedback_without_revealing_answers(): void
    {
        $duel = $this->startDuel();
        $answers = $this->answersFor($duel, wrong: 1);

        $response = $this->submit($duel, $answers)
            ->assertOk()
            ->assertJsonPath('data.outcome', 'retry')
            ->assertJsonPath('data.passed', false)
            ->assertJsonPath('data.correct_count', 1)
            ->assertJsonPath('data.total_questions', 2)
            ->assertJsonPath('data.attempts.left', 1)
            ->assertJsonPath('data.attempts.used', 1)
            ->assertJsonPath('data.attempts.locked_until', null)
            ->assertJsonPath('data.review', null)
            ->assertJsonPath('data.card', null)
            ->assertJsonPath('message', 'Acertaste 1 de 2. Te quedan 1 de 2 intentos.');

        // Por pregunta: la respuesta elegida y si fue correcta, en el orden servido.
        $this->assertSame([
            ['question_id' => $answers[0]['question_id'], 'answer_id' => $answers[0]['answer_id'], 'is_correct' => false],
            ['question_id' => $answers[1]['question_id'], 'answer_id' => $answers[1]['answer_id'], 'is_correct' => true],
        ], $response->json('data.results'));

        $this->assertStringNotContainsString('correct_answer', $response->getContent());

        $this->assertDatabaseMissing('card_captures', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
        ]);
    }

    public function test_submit_rejects_answers_that_do_not_match_the_served_questions(): void
    {
        $duel = $this->startDuel();
        $answers = $this->answersFor($duel);

        // Falta una pregunta
        $this->submit($duel, [$answers[0]])->assertStatus(422);

        // Respuesta de otra pregunta
        $this->submit($duel, [
            $answers[0],
            ['question_id' => $answers[1]['question_id'], 'answer_id' => $answers[0]['answer_id']],
        ])->assertStatus(422);

        // El duelo sigue pendiente y puede responderse
        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $duel->json('data.attempt_id'),
            'status' => QuizAttempt::STATUS_PENDING,
        ]);

        $this->submit($duel, $answers)->assertCreated();
    }

    public function test_user_cannot_submit_another_users_attempt(): void
    {
        $duel = $this->startDuel();

        Sanctum::actingAs(User::factory()->create());

        $this->submit($duel, $this->answersFor($duel))->assertForbidden();
    }

    public function test_attempt_cannot_be_submitted_twice(): void
    {
        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel, wrong: 1))->assertOk();
        $this->submit($duel, $this->answersFor($duel))->assertStatus(409);
    }

    public function test_expired_attempt_counts_as_a_failure_on_submit(): void
    {
        $duel = $this->startDuel();

        $this->travel(11)->minutes();

        $this->submit($duel, $this->answersFor($duel))
            ->assertStatus(410)
            ->assertJsonPath('data.attempts.left', 1);

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $duel->json('data.attempt_id'),
            'status' => QuizAttempt::STATUS_EXPIRED,
        ]);
    }

    public function test_abandoned_attempt_counts_as_a_failure_on_next_start(): void
    {
        $first = $this->startDuel();

        $this->travel(11)->minutes();

        $second = $this->startDuel()
            ->assertOk()
            ->assertJsonPath('data.attempts.left', 1);

        $this->assertNotSame($first->json('data.attempt_id'), $second->json('data.attempt_id'));
    }

    public function test_exhausting_retries_locks_the_card_until_the_cooldown_ends(): void
    {
        $this->freezeSecond();

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel, wrong: 1))->assertOk();

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel, wrong: 2))
            ->assertOk()
            ->assertJsonPath('data.outcome', 'locked')
            ->assertJsonPath('data.attempts.left', 0)
            ->assertJsonPath('data.attempts.locked_until', now()->addMinutes(60)->toJSON())
            ->assertJsonPath('message', 'Acertaste 0 de 2 y ya no te quedan intentos. Vuelve a intentarlo en 1 hora.');

        $this->startDuel()
            ->assertStatus(423)
            ->assertJsonPath('data.attempts.left', 0)
            ->assertJsonPath('data.attempts.locked_until', now()->addMinutes(60)->toJSON());

        $this->travel(61)->minutes();

        $this->startDuel()
            ->assertOk()
            ->assertJsonPath('data.attempts.left', 2);
    }

    public function test_rarity_cooldown_applies_when_card_uses_rarity_settings(): void
    {
        $this->freezeSecond();

        CardRaritySetting::updateOrCreate(
            ['rarity' => 'epica'],
            ['questions_per_duel' => 1, 'retries' => 1, 'cooldown_minutes' => 30]
        );

        $card = $this->createCard([
            'rarity' => 'epica',
            'duel_settings_source' => 'rarity',
        ]);

        $duel = $this->startDuel($card)->assertOk();

        $this->assertCount(1, $duel->json('data.questions'));

        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertOk()
            ->assertJsonPath('data.outcome', 'locked')
            ->assertJsonPath('data.attempts.left', 0)
            ->assertJsonPath('data.attempts.locked_until', now()->addMinutes(30)->toJSON());
    }

    public function test_lockout_reveals_answers_only_for_rarities_that_allow_it(): void
    {
        CardRaritySetting::updateOrCreate(['rarity' => 'rara'], ['reveal_answers_on_lockout' => true]);
        CardRaritySetting::updateOrCreate(['rarity' => 'epica'], ['reveal_answers_on_lockout' => false]);

        // Mientras quedan intentos, nunca hay repaso.
        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertJsonPath('data.outcome', 'retry')
            ->assertJsonPath('data.review', null);

        // Al agotar los intentos (rara): repaso con la respuesta elegida y la correcta.
        $duel = $this->startDuel();
        $answers = $this->answersFor($duel, wrong: 1);

        $review = $this->submit($duel, $answers)
            ->assertJsonPath('data.outcome', 'locked')
            ->json('data.review');

        $this->assertCount(2, $review);
        $this->assertSame($answers[0]['question_id'], $review[0]['question_id']);
        $this->assertSame($answers[0]['answer_id'], $review[0]['selected_answer_id']);
        $this->assertCount(3, $review[0]['answers']);
        $this->assertCount(1, array_filter(array_column($review[0]['answers'], 'is_correct')));

        // Rareza que no revela respuestas (épica).
        $epica = $this->createCard([
            'rarity' => 'epica',
            'duel_settings_source' => 'card',
            'questions_per_duel' => 1,
            'retries' => 1,
            'cooldown_minutes' => 30,
        ]);

        $duel = $this->startDuel($epica);
        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertJsonPath('data.outcome', 'locked')
            ->assertJsonPath('data.review', null);
    }

    public function test_card_messages_override_the_default_messages(): void
    {
        DuelMessageSetting::current()->update([
            'win_message' => 'Mensaje predeterminado',
            'retry_message' => 'Reintento predeterminado: {intentos_restantes}',
        ]);

        // Mensaje propio solo para la victoria: el reintento usa el predeterminado.
        $this->card->update([
            'messages_source' => 'card',
            'win_message' => '¡{tarjeta} es tuya!',
            'retry_message' => null,
        ]);

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertJsonPath('message', 'Reintento predeterminado: 1');

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel))
            ->assertJsonPath('message', '¡Quetzal Dorado es tuya!');
    }

    public function test_card_using_default_messages_ignores_its_own_texts(): void
    {
        DuelMessageSetting::current()->update([
            'win_message' => 'Mensaje predeterminado',
        ]);

        $this->card->update([
            'messages_source' => 'default',
            'win_message' => 'Mensaje viejo de la tarjeta',
        ]);

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel))
            ->assertJsonPath('message', 'Mensaje predeterminado');
    }

    public function test_default_messages_fill_placeholders(): void
    {
        DuelMessageSetting::current()->update([
            'win_message' => '{tarjeta}: {correctas}/{total}',
            'retry_message' => 'Quedan {intentos_restantes} de {intentos_maximos}',
        ]);

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertJsonPath('message', 'Quedan 1 de 2');

        $duel = $this->startDuel();
        $this->submit($duel, $this->answersFor($duel))
            ->assertJsonPath('message', 'Quetzal Dorado: 2/2');
    }

    public function test_unknown_qr_code_returns_404(): void
    {
        $this->scan(str_repeat('x', 64))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_inactive_qr_code_returns_404(): void
    {
        $this->card->qrCodes()->update(['is_active' => false]);

        $this->startDuel()->assertNotFound();
    }

    public function test_expired_qr_code_returns_410(): void
    {
        $this->card->qrCodes()->update(['expires_at' => now()->subMinute()]);

        $this->startDuel()->assertStatus(410);
    }

    public function test_qr_code_with_future_expiration_starts_a_duel(): void
    {
        $this->card->qrCodes()->update(['expires_at' => now()->addDay()]);

        $this->startDuel()->assertOk();
    }

    public function test_scanning_records_the_scan(): void
    {
        $this->startDuel()->assertOk();
        $this->startDuel()->assertOk();

        $qrCode = $this->card->qrCodes()->first();

        $this->assertSame(2, $qrCode->scans_count);
        $this->assertNotNull($qrCode->last_scanned_at);
    }

    public function test_code_is_required(): void
    {
        $this->scan(null)->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_duel_response_never_exposes_the_qr_token(): void
    {
        $token = $this->card->qrCodes()->first()->token;

        $this->assertStringNotContainsString($token, $this->startDuel()->getContent());
    }

    public function test_duel_cannot_be_started_by_card_id(): void
    {
        $this->postJson("/api/cards/{$this->card->id}/duel")->assertNotFound();
    }

    public function test_qr_codes_get_a_unique_random_token(): void
    {
        $first = $this->card->qrCodes()->first();
        $second = CardQrCode::create(['card_id' => $this->card->id]);

        $this->assertSame(64, strlen($first->token));
        $this->assertNotSame($first->token, $second->token);
    }
}
