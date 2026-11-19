<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQrCode;
use App\Models\CardRaritySetting;
use App\Models\DuelMessageSetting;
use App\Models\Level;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class DuelTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $user;

    protected Species $species;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = SpeciesCategory::create([
            'name' => 'Aves '.uniqid(),
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Quetzal '.uniqid(),
            'scientific_name' => 'Pharomachrus mocinno '.uniqid(),
            'slug' => 'quetzal-'.uniqid(),
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
        $this->assertSame(['max' => 2, 'left' => 2, 'locked_until' => null], $duel->json('data.attempts'));
        $this->assertSame(['attempt_id', 'attempts', 'card', 'questions'], array_keys($duel->json('data')));
        $this->assertSame(['name', 'rarity', 'category_name', 'thumbnail_image', 'stats', 'model_url', 'pack'], array_keys($duel->json('data.card')));
        $this->assertStringEndsWith('cards/9/models/quetzal.glb', $duel->json('data.card.model_url'));
        $this->assertSame($this->species->category->name, $duel->json('data.card.category_name'));
        $this->assertStringNotContainsString('is_correct', $duel->getContent());
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
            ->assertJsonPath('message', '¡Ganaste el duelo! Quetzal Dorado ya es parte de tu colección.')
            ->assertJsonPath('data.attempts', null)
            ->assertJsonPath('data.review', null)
            ->assertJsonMissingPath('data.card.attempts')
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
            ->assertJsonPath('data.attempts.left', 1)
            ->assertJsonPath('data.attempts.locked_until', null)
            ->assertJsonPath('data.review', null)
            ->assertJsonPath('data.card', null)
            ->assertJsonPath('message', 'Acertaste 1 de 2. Te quedan 1 de 2 intentos.');

        $this->assertSame(['outcome', 'results', 'attempts', 'review', 'card', 'points'], array_keys($response->json('data')));

        // Por pregunta: si la respuesta elegida fue correcta, en el orden servido.
        $this->assertSame([
            ['question_id' => $answers[0]['question_id'], 'is_correct' => false],
            ['question_id' => $answers[1]['question_id'], 'is_correct' => true],
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

    public function test_pending_duel_never_expires_and_is_resumed(): void
    {
        $first = $this->startDuel();

        $this->travel(3)->days();

        $second = $this->startDuel()
            ->assertOk()
            ->assertJsonPath('data.attempts.left', 2);

        $this->assertSame($first->json('data.attempt_id'), $second->json('data.attempt_id'));

        $this->submit($second, $this->answersFor($second))->assertCreated();
    }

    public function test_resumed_duel_without_questions_is_replaced(): void
    {
        $first = $this->startDuel();

        QuizAttempt::find($first->json('data.attempt_id'))->update([
            'question_ids' => [999999],
        ]);

        $second = $this->startDuel()->assertOk();

        $this->assertNotSame($first->json('data.attempt_id'), $second->json('data.attempt_id'));
        $this->assertCount(2, $second->json('data.questions'));
        $this->assertDatabaseMissing('quiz_attempts', ['id' => $first->json('data.attempt_id')]);
    }

    public function test_each_new_attempt_draws_its_questions_again(): void
    {
        // Varios duelos seguidos superarían el límite de peticiones por minuto.
        $this->withoutMiddleware(ThrottleRequests::class);

        $card = $this->createCard([
            'duel_settings_source' => 'card',
            'questions_per_duel' => 1,
            'retries' => 50,
        ], questions: 5);

        $served = [];

        for ($i = 0; $i < 20; $i++) {
            $duel = $this->startDuel($card)->assertOk();
            $served[] = $duel->json('data.questions.0.id');
            $this->submit($duel, $this->answersFor($duel, wrong: 1))->assertOk();
        }

        $this->assertGreaterThan(1, count(array_unique($served)));
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

        // Al agotar los intentos (rara): la respuesta correcta de las preguntas de este duelo.
        $duel = $this->startDuel();
        $correct = $this->answersFor($duel);

        $review = $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertJsonPath('data.outcome', 'locked')
            ->json('data.review');

        $this->assertSame(array_map(fn (array $answer) => [
            'question_id' => $answer['question_id'],
            'correct_answer_id' => $answer['answer_id'],
        ], $correct), $review);

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

    public function test_winning_awards_the_points_of_the_card_rarity(): void
    {
        $this->user->update(['points' => 15]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 50)
            ->assertJsonPath('data.points.total', 65)
            ->assertJsonPath('data.points.level', null)
            ->assertJsonPath('data.points.level_up', false);

        $capture = CardCapture::query()->where('user_id', $this->user->id)->firstOrFail();

        $this->assertSame(65, $this->user->fresh()->points);
        $this->assertDatabaseHas('point_movements', [
            'user_id' => $this->user->id,
            'points' => 50,
            'type' => 'card_capture_rara',
            'reference_type' => CardCapture::class,
            'reference_id' => $capture->id,
        ]);
    }

    public function test_each_rarity_awards_its_own_rule(): void
    {
        $expected = [
            'comun' => 25,
            'rara' => 50,
            'epica' => 75,
            'edicion_especial' => 100,
        ];

        foreach ($expected as $rarity => $points) {
            $card = $this->createCard([
                'rarity' => $rarity,
                'duel_settings_source' => 'card',
                'questions_per_duel' => 2,
                'retries' => 2,
                'cooldown_minutes' => 60,
            ]);

            $duel = $this->startDuel($card);

            $this->submit($duel, $this->answersFor($duel))
                ->assertCreated()
                ->assertJsonPath('data.points.awarded', $points);
        }

        $this->assertSame(array_sum($expected), $this->user->fresh()->points);
    }

    public function test_losing_awards_no_points(): void
    {
        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel, wrong: 1))
            ->assertOk()
            ->assertJsonPath('data.points', null);

        $this->assertSame(0, (int) $this->user->fresh()->points);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_inactive_rule_still_captures_the_card_without_points(): void
    {
        PointRule::query()->where('type', 'card_capture_rara')->update(['is_active' => false]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'won')
            ->assertJsonPath('data.points.awarded', 0)
            ->assertJsonPath('data.points.total', 0);

        $this->assertDatabaseHas('card_captures', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
        ]);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_capture_points_can_level_up_the_user(): void
    {
        Level::create(['name' => 'Bronce', 'min_points' => 0, 'max_points' => 499]);
        $silver = Level::create(['name' => 'Plata', 'min_points' => 500, 'max_points' => null]);

        $this->user->update(['points' => 480]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.total', 530)
            ->assertJsonPath('data.points.level.id', $silver->id)
            ->assertJsonPath('data.points.level.name', 'Plata')
            ->assertJsonPath('data.points.level_up', true);
    }

    public function test_winning_uses_the_point_rule_chosen_for_the_rarity(): void
    {
        $bonus = PointRule::create([
            'type' => 'bonus_test',
            'name' => 'Bono',
            'points' => 200,
            'is_active' => true,
        ]);

        CardRaritySetting::where('rarity', 'rara')->update(['point_rule_id' => $bonus->id]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 200)
            ->assertJsonPath('data.points.total', 200);

        $this->assertDatabaseHas('point_movements', [
            'user_id' => $this->user->id,
            'points' => 200,
            'type' => 'bonus_test',
        ]);
    }

    public function test_rarity_without_point_rule_captures_without_points(): void
    {
        CardRaritySetting::where('rarity', 'rara')->update(['point_rule_id' => null]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'won')
            ->assertJsonPath('data.points.awarded', 0)
            ->assertJsonPath('data.points.total', 0);

        $this->assertDatabaseHas('card_captures', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
        ]);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_active_point_mode_changes_the_capture_points(): void
    {
        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $mode = PointMode::create(['name' => 'Temporada difícil', 'is_active' => true]);
        $mode->rules()->attach($rare->id, ['points' => 20]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 20)
            ->assertJsonPath('data.points.total', 20);
    }

    public function test_point_mode_with_zero_still_captures_the_card(): void
    {
        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $mode = PointMode::create(['name' => 'Sin puntos', 'is_active' => true]);
        $mode->rules()->attach($rare->id, ['points' => 0]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.outcome', 'won')
            ->assertJsonPath('data.points.awarded', 0);

        $this->assertDatabaseHas('card_captures', [
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
        ]);
    }

    public function test_visible_season_is_sent_with_the_won_points(): void
    {
        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $mode = PointMode::create([
            'name' => 'Interno',
            'is_active' => true,
            'is_public' => true,
            'public_title' => 'Temporada difícil',
            'public_message' => 'Las capturas dan menos puntos.',
            'icon' => 'snowflake',
            'color' => 'azul-invierno',
        ]);
        $mode->rules()->attach($rare->id, ['points' => 30]);

        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 30)
            ->assertJsonPath('data.points.season', [
                'title' => 'Temporada difícil',
                'message' => 'Las capturas dan menos puntos.',
                'base_points' => 50,
                'icon' => 'snowflake',
                'color' => [
                    'key' => 'azul-invierno',
                    'accent' => '#1F5F8B',
                    'on_accent' => '#FFFFFF',
                    'soft' => '#E2EDF5',
                    'text' => '#123B57',
                ],
            ]);
    }

    public function test_hidden_season_or_no_mode_sends_null_season(): void
    {
        $duel = $this->startDuel();

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 50)
            ->assertJsonPath('data.points.season', null);

        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $mode = PointMode::create(['name' => 'Oculto', 'is_active' => true, 'is_public' => false]);
        $mode->rules()->attach($rare->id, ['points' => 10]);

        $card = $this->createCard([
            'duel_settings_source' => 'card',
            'questions_per_duel' => 2,
            'retries' => 2,
            'cooldown_minutes' => 60,
        ]);
        $duel = $this->startDuel($card);

        $this->submit($duel, $this->answersFor($duel))
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 10)
            ->assertJsonPath('data.points.season', null);
    }
}
