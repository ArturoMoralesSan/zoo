<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\QuizQuestion;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuizQuestionAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['cards.view', 'cards.create', 'cards.edit', 'cards.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.edit', 'cards.delete']);

        $this->card = $this->createCard();
    }

    private function createCard(): Card
    {
        $category = SpeciesCategory::create([
            'name' => 'Felinos ' . uniqid(),
            'is_active' => true,
        ]);

        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Ocelote ' . uniqid(),
            'scientific_name' => 'Leopardus pardalis ' . uniqid(),
            'slug' => 'ocelote-' . uniqid(),
            'is_active' => true,
        ]);

        return Card::create([
            'species_id' => $species->id,
            'name' => 'Ocelote',
            'rarity' => 'comun',
            'duel_settings_source' => 'card',
            'questions_per_duel' => 2,
            'retries' => 3,
            'cooldown_minutes' => 60,
            'is_active' => true,
        ]);
    }

    private function questionPayload(string $question = '¿Qué come el ocelote?', int $correct = 0): array
    {
        return [
            'question' => $question,
            'is_active' => true,
            'sort_order' => 0,
            'answers' => collect(['Roedores', 'Frutas', 'Hojas'])
                ->map(fn (string $answer, int $index) => [
                    'answer' => $answer,
                    'is_correct' => $index === $correct,
                ])
                ->all(),
        ];
    }

    private function storeQuestion(array $payload): QuizQuestion
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.quiz.store', $this->card), $payload)
            ->assertRedirect(route('admin.cards.quiz.index', $this->card));

        return $this->card->quizQuestions()->latest('id')->firstOrFail();
    }

    public function test_index_lists_the_card_questions(): void
    {
        $question = $this->storeQuestion($this->questionPayload());

        $this->actingAs($this->admin)
            ->get(route('admin.cards.quiz.index', $this->card))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/quizquestions/Index')
                ->where('questions.data.0.id', $question->id));
    }

    public function test_admin_can_create_a_question_with_three_answers(): void
    {
        $question = $this->storeQuestion($this->questionPayload());

        $this->assertSame('¿Qué come el ocelote?', $question->question);
        $this->assertCount(3, $question->answers);
        $this->assertSame(1, $question->answers()->where('is_correct', true)->count());
    }

    public function test_question_requires_three_answers_and_exactly_one_correct(): void
    {
        $payload = $this->questionPayload();

        $twoAnswers = $payload;
        array_pop($twoAnswers['answers']);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.quiz.store', $this->card), $twoAnswers)
            ->assertSessionHasErrors('answers');

        $twoCorrect = $payload;
        $twoCorrect['answers'][1]['is_correct'] = true;

        $this->actingAs($this->admin)
            ->post(route('admin.cards.quiz.store', $this->card), $twoCorrect)
            ->assertSessionHasErrors('answers');

        $this->assertSame(0, $this->card->quizQuestions()->count());
    }

    public function test_updating_a_question_keeps_its_answer_ids(): void
    {
        $question = $this->storeQuestion($this->questionPayload());
        $answers = $question->answers;

        $this->actingAs($this->admin)
            ->put(route('admin.cards.quiz.update', [$this->card, $question]), [
                'question' => '¿Dónde vive el ocelote?',
                'is_active' => false,
                'sort_order' => 2,
                'answers' => $answers->map(fn ($answer, int $index) => [
                    'id' => $answer->id,
                    'answer' => "Respuesta {$index}",
                    'is_correct' => $index === 2,
                ])->all(),
            ])
            ->assertRedirect(route('admin.cards.quiz.index', $this->card));

        $question->refresh();

        $this->assertSame('¿Dónde vive el ocelote?', $question->question);
        $this->assertFalse($question->is_active);
        $this->assertSame($answers->pluck('id')->all(), $question->answers->pluck('id')->all());
        $this->assertTrue($question->answers[2]->is_correct);
        $this->assertSame(1, $question->answers()->where('is_correct', true)->count());
    }

    public function test_admin_can_delete_a_question_and_its_answers(): void
    {
        $question = $this->storeQuestion($this->questionPayload());

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.quiz.destroy', [$this->card, $question]))
            ->assertRedirect(route('admin.cards.quiz.index', $this->card));

        $this->assertModelMissing($question);
        $this->assertDatabaseMissing('quiz_answers', ['quiz_question_id' => $question->id]);
    }

    public function test_question_cannot_be_managed_through_another_card(): void
    {
        $question = $this->storeQuestion($this->questionPayload());
        $otherCard = $this->createCard();

        $this->actingAs($this->admin)
            ->get(route('admin.cards.quiz.edit', [$otherCard, $question]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.quiz.destroy', [$otherCard, $question]))
            ->assertNotFound();

        $this->assertModelExists($question);
    }

    public function test_card_show_page_reports_question_counts(): void
    {
        $this->storeQuestion($this->questionPayload('Activa 1'));
        $this->storeQuestion(['is_active' => false] + $this->questionPayload('Inactiva'));

        $this->actingAs($this->admin)
            ->get(route('admin.cards.show', $this->card))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('card.quiz_questions_count', 2)
                ->where('card.active_quiz_questions_count', 1)
                ->missing('card.quiz_questions'));
    }

    public function test_questions_created_in_the_quiz_section_are_used_by_duels(): void
    {
        $this->storeQuestion($this->questionPayload('Pregunta A', correct: 0));
        $this->storeQuestion($this->questionPayload('Pregunta B', correct: 1));

        $qrCode = $this->card->qrCodes()->create();
        $player = User::factory()->create();

        Sanctum::actingAs($player);

        $duel = $this->postJson(route('api.duels.store'), ['code' => $qrCode->token])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Pregunta A', 'Pregunta B'],
            collect($duel->json('data.questions'))->pluck('question')->all()
        );

        $answers = collect($duel->json('data.questions'))
            ->map(fn (array $question) => [
                'question_id' => $question['id'],
                'answer_id' => QuizQuestion::find($question['id'])
                    ->answers()
                    ->where('is_correct', true)
                    ->value('id'),
            ])
            ->all();

        $this->postJson(route('api.duels.submit', $duel->json('data.attempt_id')), [
            'answers' => $answers,
        ])
            ->assertCreated()
            ->assertJsonPath('data.passed', true);

        $this->assertDatabaseHas('card_captures', [
            'user_id' => $player->id,
            'card_id' => $this->card->id,
        ]);
    }

    public function test_permissions_are_checked_per_action(): void
    {
        $question = $this->storeQuestion($this->questionPayload());

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        // Solo con cards.view puede ver, sin necesitar los demás permisos
        $this->actingAs($viewer)->get(route('admin.cards.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.cards.show', $this->card))->assertOk();
        $this->actingAs($viewer)->get(route('admin.cards.quiz.index', $this->card))->assertOk();
        $this->actingAs($viewer)->get(route('admin.cards.qr-codes.index', $this->card))->assertOk();

        // Pero no puede modificar
        $this->actingAs($viewer)
            ->post(route('admin.cards.quiz.store', $this->card), $this->questionPayload('Nueva'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('admin.cards.quiz.destroy', [$this->card, $question]))
            ->assertForbidden();

        $this->assertSame(1, $this->card->quizQuestions()->count());
    }
}
