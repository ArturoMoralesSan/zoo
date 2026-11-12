<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQuizState;
use App\Models\QuizAttempt;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CardCaptureAdminTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected User $player;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.zoodeck_dev' => true]);

        foreach (['cards.view', 'cards.create', 'cards.edit', 'cards.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.edit', 'cards.delete']);

        $this->player = User::factory()->create();

        $category = SpeciesCategory::create([
            'name' => 'Aves ' . uniqid(),
            'is_active' => true,
        ]);

        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Quetzal ' . uniqid(),
            'scientific_name' => 'Pharomachrus mocinno ' . uniqid(),
            'slug' => 'quetzal-' . uniqid(),
            'is_active' => true,
        ]);

        $this->card = Card::create([
            'species_id' => $species->id,
            'name' => 'Quetzal Dorado',
            'rarity' => 'rara',
            'is_active' => true,
        ]);
    }

    /**
     * Tarjeta ganada con su historial de duelos.
     */
    private function win(User $user, Card $card): CardCapture
    {
        QuizAttempt::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'question_ids' => [1],
            'status' => QuizAttempt::STATUS_FAILED,
            'total_questions' => 1,
        ]);

        QuizAttempt::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'question_ids' => [1],
            'status' => QuizAttempt::STATUS_PASSED,
            'total_questions' => 1,
        ]);

        CardQuizState::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'failed_attempts' => 1,
        ]);

        return CardCapture::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'captured_at' => now(),
        ]);
    }

    public function test_index_lists_captures(): void
    {
        $capture = $this->win($this->player, $this->card);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('captures', 1)
                ->where('captures.0.id', $capture->id)
                ->where('captures.0.user.email', $this->player->email)
                ->where('captures.0.card.name', 'Quetzal Dorado'));
    }

    public function test_revoking_removes_the_capture_and_its_duel_history(): void
    {
        $capture = $this->win($this->player, $this->card);

        // Lo de otro usuario no se toca.
        $other = User::factory()->create();
        $otherCapture = $this->win($other, $this->card);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.captures.destroy', $capture))
            ->assertRedirect()
            ->assertSessionHas('success');

        $where = ['user_id' => $this->player->id, 'card_id' => $this->card->id];

        $this->assertModelMissing($capture);
        $this->assertDatabaseMissing('quiz_attempts', $where);
        $this->assertDatabaseMissing('card_quiz_states', $where);

        $this->assertModelExists($otherCapture);
        $this->assertDatabaseCount('quiz_attempts', 2);
        $this->assertDatabaseCount('card_quiz_states', 1);
    }

    public function test_revoking_requires_the_delete_permission(): void
    {
        $capture = $this->win($this->player, $this->card);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->delete(route('admin.cards.captures.destroy', $capture))
            ->assertForbidden();

        $this->assertModelExists($capture);
    }

    public function test_index_lists_quiz_states_with_lock(): void
    {
        $this->freezeSecond();

        $state = CardQuizState::create([
            'user_id' => $this->player->id,
            'card_id' => $this->card->id,
            'failed_attempts' => 3,
            'locked_until' => now()->addHour(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('quizStates', 1)
                ->where('quizStates.0.id', $state->id)
                ->where('quizStates.0.failed_attempts', 3)
                ->where('quizStates.0.max_attempts', $this->card->effective_retries)
                ->where('quizStates.0.locked_until', now()->addHour()->toJSON())
                ->where('quizStates.0.user.email', $this->player->email));
    }

    public function test_resetting_clears_the_lock_and_unwon_duels_only(): void
    {
        $where = ['user_id' => $this->player->id, 'card_id' => $this->card->id];

        foreach ([QuizAttempt::STATUS_FAILED, QuizAttempt::STATUS_PENDING] as $status) {
            QuizAttempt::create($where + [
                'question_ids' => [1],
                'status' => $status,
                'total_questions' => 1,
            ]);
        }

        $state = CardQuizState::create($where + [
            'failed_attempts' => 3,
            'locked_until' => now()->addHour(),
        ]);

        // Lo de otro usuario no se toca.
        $other = User::factory()->create();
        $otherState = CardQuizState::create([
            'user_id' => $other->id,
            'card_id' => $this->card->id,
            'failed_attempts' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.quiz-states.destroy', $state))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($state);
        $this->assertDatabaseMissing('quiz_attempts', $where);
        $this->assertModelExists($otherState);
    }

    public function test_resetting_requires_the_edit_permission(): void
    {
        $state = CardQuizState::create([
            'user_id' => $this->player->id,
            'card_id' => $this->card->id,
            'failed_attempts' => 1,
        ]);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->delete(route('admin.cards.quiz-states.destroy', $state))
            ->assertForbidden();

        $this->assertModelExists($state);
    }

    public function test_dev_tools_are_hidden_when_disabled(): void
    {
        config(['app.zoodeck_dev' => false]);

        $capture = $this->win($this->player, $this->card);
        $state = CardQuizState::create([
            'user_id' => User::factory()->create()->id,
            'card_id' => $this->card->id,
            'failed_attempts' => 1,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('devTools', false)
                ->has('captures', 0)
                ->has('quizStates', 0)
            );

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.captures.destroy', $capture))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.quiz-states.destroy', $state))
            ->assertNotFound();

        $this->assertModelExists($capture);
        $this->assertModelExists($state);
    }
}
