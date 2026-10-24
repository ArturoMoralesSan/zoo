<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\CardRaritySetting;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CardAdminTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;
    protected SpeciesCategory $category;
    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permissions exist
        Permission::findOrCreate('cards.view');
        Permission::findOrCreate('cards.create');
        Permission::findOrCreate('cards.edit');
        Permission::findOrCreate('cards.delete');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.create', 'cards.edit', 'cards.delete']);

        $this->category = SpeciesCategory::create([
            'name' => 'Aves ' . uniqid(),
            'description' => 'Categoría de aves',
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $this->category->id,
            'common_name' => 'Águila Real ' . uniqid(),
            'scientific_name' => 'Aquila chrysaetos ' . uniqid(),
            'slug' => 'aguila-real-' . uniqid(),
            'description' => 'Especie de prueba',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_store_card_with_stats_retries_and_questions(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cards.store'), [
            'species_id' => $this->species->id,
            'name' => 'Águila Legendaria',
            'rarity' => 'epica',
            'astucia' => 85,
            'rapidez' => 95,
            'fuerza' => 90,
            'questions_per_duel' => 5,
            'duel_settings_source' => 'card',
            'retries' => 4,
            'is_active' => true,
            'sort_order' => 1,
            'questions' => [
                [
                    'question' => '¿Cuál es la envergadura del águila real?',
                    'is_active' => true,
                    'sort_order' => 0,
                    'answers' => [
                        ['answer' => 'Más de 2 metros', 'is_correct' => true, 'sort_order' => 0],
                        ['answer' => '50 centímetros', 'is_correct' => false, 'sort_order' => 1],
                        ['answer' => '1 metro', 'is_correct' => false, 'sort_order' => 2],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.cards.index'));

        $this->assertDatabaseHas('cards', [
            'name' => 'Águila Legendaria',
            'astucia' => 85,
            'rapidez' => 95,
            'fuerza' => 90,
            'questions_per_duel' => 5,
            'duel_settings_source' => 'card',
            'retries' => 4,
        ]);

        $card = Card::where('name', 'Águila Legendaria')->first();
        $this->assertCount(1, $card->quizQuestions);

        $question = $card->quizQuestions->first();
        $this->assertEquals('¿Cuál es la envergadura del águila real?', $question->question);
        $this->assertCount(3, $question->answers);
        $this->assertEquals(1, $question->answers()->where('is_correct', true)->count());
    }

    public function test_admin_can_update_card_to_use_rarity_settings_and_sync_questions(): void
    {
        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Águila Juvenil',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'questions_per_duel' => 3,
            'duel_settings_source' => 'card',
            'retries' => 3,
            'is_active' => true,
        ]);

        $q1 = $card->quizQuestions()->create([
            'question' => 'Pregunta antigua',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $q1->answers()->createMany([
            ['answer' => 'Opcion 1', 'is_correct' => true, 'sort_order' => 0],
            ['answer' => 'Opcion 2', 'is_correct' => false, 'sort_order' => 1],
        ]);

        CardRaritySetting::updateOrCreate(
            ['rarity' => 'rara'],
            ['questions_per_duel' => 5, 'retries' => 8]
        );

        $response = $this->actingAs($this->admin)->put(route('admin.cards.update', $card), [
            'species_id' => $this->species->id,
            'name' => 'Águila Adulta',
            'rarity' => 'rara',
            'astucia' => 70,
            'rapidez' => 80,
            'fuerza' => 85,
            'duel_settings_source' => 'rarity',
            'is_active' => true,
            'sort_order' => 0,
            'questions' => [
                [
                    'id' => $q1->id,
                    'question' => 'Pregunta actualizada',
                    'is_active' => true,
                    'sort_order' => 0,
                    'answers' => [
                        ['answer' => 'Nueva opcion correcta', 'is_correct' => true, 'sort_order' => 0],
                        ['answer' => 'Nueva opcion incorrecta', 'is_correct' => false, 'sort_order' => 1],
                        ['answer' => 'Tercera opcion', 'is_correct' => false, 'sort_order' => 2],
                    ],
                ],
                [
                    'question' => 'Segunda pregunta nueva',
                    'is_active' => true,
                    'sort_order' => 1,
                    'answers' => [
                        ['answer' => 'Respuesta A', 'is_correct' => false, 'sort_order' => 0],
                        ['answer' => 'Respuesta B', 'is_correct' => true, 'sort_order' => 1],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.cards.index'));

        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'name' => 'Águila Adulta',
            'astucia' => 70,
            'rapidez' => 80,
            'fuerza' => 85,
            'duel_settings_source' => 'rarity',
            'questions_per_duel' => null,
            'retries' => null,
        ]);

        // Los valores efectivos provienen de la rareza
        $card->refresh();
        $this->assertEquals(5, $card->effective_questions_per_duel);
        $this->assertEquals(8, $card->effective_retries);

        // Questions synced
        $this->assertCount(2, $card->quizQuestions);
        $this->assertEquals('Pregunta actualizada', $card->quizQuestions[0]->question);
        $this->assertCount(3, $card->quizQuestions[0]->answers);
        $this->assertEquals('Segunda pregunta nueva', $card->quizQuestions[1]->question);
    }

    public function test_custom_settings_are_required_when_source_is_card(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cards.store'), [
            'species_id' => $this->species->id,
            'name' => 'Águila Sin Configuración',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'card',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['questions_per_duel', 'retries']);
    }

    public function test_admin_can_update_rarity_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.cards.index'))
            ->put(route('admin.cards.rarity-settings.update'), [
                'settings' => [
                    ['rarity' => 'comun', 'questions_per_duel' => 2, 'retries' => 5],
                    ['rarity' => 'epica', 'questions_per_duel' => 10, 'retries' => 1],
                ],
            ]);

        $response->assertRedirect(route('admin.cards.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('card_rarity_settings', [
            'rarity' => 'comun',
            'questions_per_duel' => 2,
            'retries' => 5,
        ]);

        $this->assertDatabaseHas('card_rarity_settings', [
            'rarity' => 'epica',
            'questions_per_duel' => 10,
            'retries' => 1,
        ]);
    }

    public function test_rarity_settings_update_validates_input(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.cards.rarity-settings.update'), [
                'settings' => [
                    ['rarity' => 'legendaria', 'questions_per_duel' => 0, 'retries' => 101],
                ],
            ]);

        $response->assertSessionHasErrors([
            'settings.0.rarity',
            'settings.0.questions_per_duel',
            'settings.0.retries',
        ]);
    }
}
