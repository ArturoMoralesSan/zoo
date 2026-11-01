<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\QuizQuestion;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class QuizQuestionImportTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function item(string $question, string $correct = 'Roedores', array $wrong = ['Frutas', 'Hojas']): array
    {
        return [
            'question' => $question,
            'correct_answer' => $correct,
            'wrong_answers' => $wrong,
        ];
    }

    private function import(array $questions, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(
            route('admin.cards.quiz.import.store', $this->card),
            ['questions' => $questions, 'is_active' => true, ...$extra]
        );
    }

    public function test_import_page_shows_existing_questions_and_is_not_taken_as_a_question(): void
    {
        $this->card->quizQuestions()->create(['question' => '¿Qué come el ocelote?', 'is_active' => true]);

        $this->actingAs($this->admin)
            ->get('/admin/cards/'.$this->card->id.'/quiz/import')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/quizquestions/Import')
                ->where('existingQuestions', ['¿Qué come el ocelote?'])
                ->where('maxQuestions', 100)
                ->where('card.species.common_name', 'Ocelote'));
    }

    public function test_import_requires_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->get(route('admin.cards.quiz.import', $this->card))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.cards.quiz.import.store', $this->card), ['questions' => [$this->item('¿Hola?')]])
            ->assertForbidden();
    }

    public function test_valid_batch_creates_questions_with_one_correct_answer(): void
    {
        $this->import([
            $this->item('¿Qué come el ocelote?'),
            $this->item('¿Dónde vive el ocelote?', 'En selvas y bosques', ['En el desierto', 'En el mar']),
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.quiz.index', $this->card))
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', '2 preguntas importadas.');

        $questions = $this->card->quizQuestions()->with('answers')->get();

        $this->assertCount(2, $questions);

        foreach ($questions as $question) {
            $this->assertTrue($question->is_active);
            $this->assertCount(3, $question->answers);
            $this->assertSame(1, $question->answers->where('is_correct', true)->count());
            $this->assertSame([0, 1, 2], $question->answers->pluck('sort_order')->sort()->values()->all());
        }

        $this->assertSame(
            'En selvas y bosques',
            $questions[1]->answers->firstWhere('is_correct', true)->answer
        );
    }

    public function test_answer_order_is_randomised(): void
    {
        $positions = [];

        foreach (range(1, 30) as $number) {
            $this->import([$this->item("¿Pregunta {$number}?")]);
        }

        foreach ($this->card->quizQuestions()->with('answers')->get() as $question) {
            $positions[] = $question->answers->firstWhere('is_correct', true)->sort_order;
        }

        // Con 30 preguntas, la correcta no queda siempre en la misma posición.
        $this->assertGreaterThan(1, count(array_unique($positions)));
    }

    public function test_questions_can_be_imported_inactive(): void
    {
        $this->import([$this->item('¿Qué come el ocelote?')], ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->card->quizQuestions()->first()->is_active);
    }

    public function test_invalid_rows_return_errors_by_index(): void
    {
        $this->import([
            $this->item('¿Válida?'),
            ['question' => '¿Sin correcta?', 'wrong_answers' => ['A', 'B']],
            $this->item('¿Una sola incorrecta?', 'A', ['B']),
            $this->item('¿Tres incorrectas?', 'A', ['B', 'C', 'D']),
            $this->item('¿Repetidas?', 'Roedores', ['roedores', 'Hojas']),
            $this->item(str_repeat('a', 5001)),
        ])->assertSessionHasErrors([
            'questions.1.correct_answer' => 'Falta la respuesta correcta.',
            'questions.2.wrong_answers' => 'Debe haber exactamente 2 respuestas incorrectas.',
            'questions.3.wrong_answers' => 'Debe haber exactamente 2 respuestas incorrectas.',
            'questions.4.wrong_answers' => 'Las tres respuestas deben ser distintas.',
            'questions.5.question',
        ]);

        // Si una falla, no se importa ninguna.
        $this->assertSame(0, QuizQuestion::count());
    }

    public function test_batch_size_is_limited(): void
    {
        $questions = array_map(fn (int $number) => $this->item("¿Pregunta {$number}?"), range(1, 101));

        $this->import($questions)->assertSessionHasErrors([
            'questions' => 'Importa como máximo 100 preguntas a la vez.',
        ]);

        $this->import([])->assertSessionHasErrors('questions');
    }

    public function test_repeated_questions_are_skipped(): void
    {
        $this->card->quizQuestions()->create(['question' => '¿Qué come el ocelote?', 'is_active' => true]);

        $this->import([
            $this->item('¿Que come el OCELOTE'),
            $this->item('¿Dónde vive el ocelote?'),
            $this->item('Donde vive el ocelote'),
        ])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', '1 pregunta importada (2 omitidas por repetidas).');

        $this->assertSame(2, $this->card->quizQuestions()->count());
    }

    public function test_only_repeated_questions_show_a_warning(): void
    {
        $this->card->quizQuestions()->create(['question' => '¿Qué come el ocelote?', 'is_active' => true]);

        $this->import([$this->item('¿Qué come el ocelote?')])
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash('toast.message', '0 preguntas importadas (1 omitida por repetida).');
    }

    public function test_imported_questions_count_for_duels(): void
    {
        $this->import([
            $this->item('¿Qué come el ocelote?'),
            $this->item('¿Dónde vive el ocelote?'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.show', $this->card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('card.active_quiz_questions_count', 2));
    }

    public function test_normalize_text_matches_the_frontend(): void
    {
        $this->assertSame('cual es el felino mas grande', QuizQuestion::normalizeText('  ¿Cuál es   el FELINO más grande?  '));
        $this->assertSame('nino', QuizQuestion::normalizeText('¡Niño!'));
        $this->assertSame('que come el nandu', QuizQuestion::normalizeText('Qué come el ñandú.'));
    }
}
