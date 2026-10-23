<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected SpeciesCategory $category;
    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->category = SpeciesCategory::create([
            'name' => 'Mamíferos ' . uniqid(),
            'description' => 'Categoría de prueba',
            'retry_limit' => 5,
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $this->category->id,
            'common_name' => 'Jaguar ' . uniqid(),
            'scientific_name' => 'Panthera onca ' . uniqid(),
            'slug' => 'jaguar-' . uniqid(),
            'description' => 'Especie de prueba',
            'is_active' => true,
        ]);
    }

    public function test_cards_index_returns_encountered_false_and_omits_models_when_user_does_not_own_card(): void
    {
        Sanctum::actingAs($this->user);

        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Carta No Obtenida',
            'rarity' => 'comun',
            'model_name' => 'Jaguar 3D',
            'model_file' => 'cards/1/models/jaguar.glb',
            'model_url' => 'https://example.com/models/jaguar.glb',
            'astucia' => 75,
            'rapidez' => 85,
            'fuerza' => 90,
            'questions_per_duel' => 4,
            'retry_type' => 'card',
            'retries' => 3,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.cards.index'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = collect($response->json('data'))->firstWhere('id', $card->id);

        $this->assertNotNull($data);
        $this->assertFalse($data['encountered']);
        $this->assertArrayNotHasKey('model_file', $data);
        $this->assertArrayNotHasKey('model_url', $data);
        $this->assertEquals(75, $data['astucia']);
        $this->assertEquals(85, $data['rapidez']);
        $this->assertEquals(90, $data['fuerza']);
        $this->assertEquals(4, $data['questions_per_duel']);
        $this->assertEquals(3, $data['retries']);
    }

    public function test_cards_index_returns_encountered_true_and_sends_models_when_user_owns_card(): void
    {
        Sanctum::actingAs($this->user);

        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Carta Obtenida',
            'rarity' => 'epica',
            'model_name' => 'Jaguar Especial',
            'model_file' => 'cards/2/models/jaguar_epic.glb',
            'model_url' => 'https://example.com/models/jaguar_epic.glb',
            'astucia' => 88,
            'rapidez' => 92,
            'fuerza' => 96,
            'questions_per_duel' => 5,
            'retry_type' => 'card',
            'retries' => 4,
            'is_active' => true,
        ]);

        CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $card->id,
            'captured_at' => now(),
        ]);

        $response = $this->getJson(route('api.cards.index'));

        $response->assertStatus(200);

        $data = collect($response->json('data'))->firstWhere('id', $card->id);

        $this->assertNotNull($data);
        $this->assertTrue($data['encountered']);
        $this->assertEquals('cards/2/models/jaguar_epic.glb', $data['model_file']);
        $this->assertEquals('https://example.com/models/jaguar_epic.glb', $data['model_url']);
        $this->assertEquals(88, $data['astucia']);
        $this->assertEquals(92, $data['rapidez']);
        $this->assertEquals(96, $data['fuerza']);
        $this->assertEquals(5, $data['questions_per_duel']);
        $this->assertEquals(4, $data['retries']);
    }

    public function test_cards_retries_resolves_category_limit_when_retry_type_is_category(): void
    {
        Sanctum::actingAs($this->user);

        $this->category->update(['retry_limit' => 7]);

        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Carta Por Categoria',
            'rarity' => 'comun',
            'astucia' => 60,
            'rapidez' => 60,
            'fuerza' => 60,
            'questions_per_duel' => 3,
            'retry_type' => 'category',
            'retries' => null,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.cards.index'));

        $response->assertStatus(200);

        $data = collect($response->json('data'))->firstWhere('id', $card->id);

        $this->assertNotNull($data);
        $this->assertEquals(7, $data['retries']);
    }

    public function test_cards_show_returns_403_and_no_data_when_user_does_not_own_card(): void
    {
        Sanctum::actingAs($this->user);

        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Carta Bloqueada',
            'rarity' => 'rara',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.cards.show', $card));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertArrayNotHasKey('data', $response->json());
    }

    public function test_cards_show_returns_data_when_user_owns_card(): void
    {
        Sanctum::actingAs($this->user);

        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Carta Desbloqueada',
            'rarity' => 'rara',
            'model_name' => 'Jaguar 3D',
            'model_file' => 'cards/3/models/jaguar.glb',
            'model_url' => 'https://example.com/models/jaguar.glb',
            'astucia' => 80,
            'rapidez' => 85,
            'fuerza' => 90,
            'questions_per_duel' => 3,
            'retry_type' => 'card',
            'retries' => 2,
            'is_active' => true,
        ]);

        CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $card->id,
            'captured_at' => now(),
        ]);

        $response = $this->getJson(route('api.cards.show', $card));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $card->id,
                    'name' => 'Carta Desbloqueada',
                    'encountered' => true,
                    'model_file' => 'cards/3/models/jaguar.glb',
                    'model_url' => 'https://example.com/models/jaguar.glb',
                    'astucia' => 80,
                    'rapidez' => 85,
                    'fuerza' => 90,
                    'questions_per_duel' => 3,
                    'retries' => 2,
                ],
            ]);
    }
}
