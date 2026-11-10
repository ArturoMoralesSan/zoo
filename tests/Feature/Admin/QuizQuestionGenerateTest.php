<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\QuizQuestion;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class QuizQuestionGenerateTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->withoutVite();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            // El SSR de Inertia también usa Http y lo frenaría preventStrayRequests.
            'inertia.ssr.enabled' => false,
        ]);

        foreach (['cards.view', 'cards.create', 'cards.edit', 'cards.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.edit']);

        $category = SpeciesCategory::create(['name' => 'Felinos', 'is_active' => true]);

        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Ocelote',
            'scientific_name' => 'Leopardus pardalis',
            'slug' => 'ocelote',
            'diet' => 'Roedores y aves pequeñas',
            'is_active' => true,
        ]);

        $this->card = Card::create([
            'species_id' => $species->id,
            'name' => 'Ocelote',
            'rarity' => 'comun',
            'duel_settings_source' => 'rarity',
            'is_active' => true,
        ]);
    }

    private function question(string $text = '¿Qué come el ocelote?'): array
    {
        return [
            'question' => $text,
            'correct_answer' => 'Roedores',
            'wrong_answers' => ['Frutas', 'Hojas'],
        ];
    }

    private function geminiReturns(array $questions): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => json_encode(['questions' => $questions])]]],
            ]],
        ])]);
    }

    private function generate(array $payload = [])
    {
        return $this->actingAs($this->admin)->postJson(
            route('admin.cards.quiz.generate.store', $this->card),
            ['count' => 5, 'topic' => 'general', ...$payload]
        );
    }

    public function test_page_is_not_taken_as_a_question_and_reports_configuration(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/cards/'.$this->card->id.'/quiz/generate')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/quizquestions/Generate', false)
                ->where('aiEnabled', true)
                ->has('topics', 5));

        config(['services.gemini.key' => null]);

        $this->actingAs($this->admin)
            ->get('/admin/cards/'.$this->card->id.'/quiz/generate')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('aiEnabled', false));
    }

    public function test_requires_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->postJson(route('admin.cards.quiz.generate.store', $this->card), ['count' => 5, 'topic' => 'general'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_validates_count_and_topic(): void
    {
        $this->generate(['count' => 101, 'topic' => 'otro'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['count', 'topic']);

        $this->generate(['count' => 0])->assertJsonValidationErrors(['count']);
        $this->generate(['count' => 2.5])->assertJsonValidationErrors(['count']);

        Http::assertNothingSent();
    }

    public function test_accepts_any_amount_from_one_to_the_save_limit(): void
    {
        $this->geminiReturns([$this->question()]);

        $this->generate(['count' => 1])->assertOk();
        $this->generate(['count' => 37])->assertOk();
        $this->generate(['count' => 100])->assertOk();

        Http::assertSentCount(3);
    }

    public function test_returns_questions_without_saving_them(): void
    {
        $this->geminiReturns([$this->question()]);

        $this->generate()
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => ['questions' => [$this->question()]],
            ]);

        $this->assertSame(0, QuizQuestion::count());
    }

    public function test_request_carries_key_species_data_and_questions_to_avoid(): void
    {
        $this->card->quizQuestions()->create(['question' => '¿Dónde vive el ocelote?', 'is_active' => true]);
        $this->geminiReturns([$this->question()]);

        $this->generate(['topic' => 'alimentacion', 'exclude' => ['¿Cuánto pesa el ocelote?']])->assertOk();

        Http::assertSent(function (Request $request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return str_contains($request->url(), 'models/gemini-test:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && ! str_contains($request->url(), 'test-key')
                && $request['generationConfig']['responseMimeType'] === 'application/json'
                && str_contains($prompt, 'Leopardus pardalis')
                && str_contains($prompt, 'Roedores y aves pequeñas')
                && str_contains($prompt, 'su alimentación')
                && str_contains($prompt, '¿Dónde vive el ocelote?')
                && str_contains($prompt, '¿Cuánto pesa el ocelote?')
                && str_contains($prompt, 'no menciones el zoológico');
        });
    }

    public function test_drops_malformed_rows_and_limits_to_the_requested_count(): void
    {
        $this->geminiReturns([
            $this->question('Pregunta 1'),
            ['question' => 'Sin correcta', 'wrong_answers' => ['a', 'b']],
            ['question' => 'Una incorrecta', 'correct_answer' => 'a', 'wrong_answers' => ['b']],
            'texto suelto',
            ...array_map(fn (int $n) => $this->question("Pregunta {$n}"), range(2, 8)),
        ]);

        $this->generate()
            ->assertOk()
            ->assertJsonCount(5, 'data.questions')
            ->assertJsonPath('data.questions.0.question', 'Pregunta 1')
            ->assertJsonPath('data.questions.1.question', 'Pregunta 2');
    }

    public function test_generated_questions_can_be_saved_through_the_import(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.quiz.import.store', $this->card), [
                'questions' => [$this->question()],
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertSame(1, $this->card->quizQuestions()->count());
    }

    public function test_missing_key_gives_a_friendly_error_and_calls_nothing(): void
    {
        config(['services.gemini.key' => '']);

        $this->generate()
            ->assertStatus(503)
            ->assertExactJson([
                'success' => false,
                'message' => 'La generación con IA aún no está activada. Avisa al equipo técnico.',
            ]);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    public static function apiErrors(): array
    {
        return [
            'cuota' => [429, 429, 'límite de uso'],
            'clave inválida' => [400, 502, 'bien configurada'],
            'prohibido' => [403, 502, 'bien configurada'],
            'caído' => [500, 503, 'no respondió'],
        ];
    }

    #[DataProvider('apiErrors')]
    public function test_api_errors_become_friendly_messages(int $apiStatus, int $status, string $message): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key: secreto']], $apiStatus)]);

        $response = $this->generate()->assertStatus($status)->assertJsonPath('success', false);

        $this->assertStringContainsString($message, $response->json('message'));
        $this->assertStringNotContainsString('secreto', $response->getContent());
    }

    public function test_retries_once_when_the_service_is_saturated(): void
    {
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
            ->push([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [['text' => json_encode(['questions' => [$this->question()]])]]],
                ]],
            ]);

        $this->generate()->assertOk()->assertJsonCount(1, 'data.questions');

        Http::assertSentCount(2);
    }

    public function test_timeout_becomes_a_friendly_message(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $response = $this->generate()->assertStatus(503);

        $this->assertStringContainsString('no respondió', $response->json('message'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: int, 2: string}>
     */
    public static function unusableResponses(): array
    {
        return [
            'prompt bloqueado' => [['promptFeedback' => ['blockReason' => 'SAFETY']], 422, 'otro tema'],
            'respuesta bloqueada' => [['candidates' => [['finishReason' => 'SAFETY']]], 422, 'otro tema'],
            'truncada' => [['candidates' => [['finishReason' => 'MAX_TOKENS', 'content' => ['parts' => [['text' => '{"questions":[']]]]]], 502, 'incompleta'],
            'json inválido' => [['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'no es json']]]]]], 502, 'preguntas válidas'],
            'sin candidatos' => [['candidates' => []], 502, 'preguntas válidas'],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    #[DataProvider('unusableResponses')]
    public function test_unusable_responses_become_friendly_messages(array $body, int $status, string $message): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($body)]);

        $response = $this->generate()->assertStatus($status);

        $this->assertStringContainsString($message, $response->json('message'));
        $this->assertSame(0, QuizQuestion::count());
    }

    public function test_ignores_thought_parts(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [
                    ['text' => 'pensando…', 'thought' => true],
                    ['text' => json_encode(['questions' => [$this->question()]])],
                ]],
            ]],
        ])]);

        $this->generate()->assertOk()->assertJsonCount(1, 'data.questions');
    }
}
