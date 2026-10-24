<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
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

    private function createCard(array $attributes = []): Card
    {
        return Card::create(array_merge([
            'species_id' => $this->species->id,
            'name' => 'Carta ' . uniqid(),
            'rarity' => 'comun',
            'description' => 'Descripción que no debe enviarse',
            'model_name' => 'Jaguar 3D',
            'model_file' => 'cards/1/models/jaguar.glb',
            'model_url' => 'https://example.com/models/jaguar.glb',
            'astucia' => 75,
            'rapidez' => 85,
            'fuerza' => 90,
            'duel_settings_source' => 'card',
            'questions_per_duel' => 4,
            'retries' => 3,
            'is_active' => true,
        ], $attributes));
    }

    private function capture(Card $card): CardCapture
    {
        return CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $card->id,
            'captured_at' => now(),
        ]);
    }

    private function cardFromIndex(Card $card): ?array
    {
        return collect(
            $this->getJson(route('api.cards.index'))->assertOk()->json('data')
        )->firstWhere('id', $card->id);
    }

    public function test_cards_index_requires_authentication(): void
    {
        $this->getJson(route('api.cards.index'))->assertUnauthorized();
    }

    public function test_cards_index_returns_hub_shape_without_model_when_not_owned(): void
    {
        Sanctum::actingAs($this->user);

        $card = $this->createCard();

        $data = $this->cardFromIndex($card);

        $this->assertNotNull($data);
        $this->assertSame([
            'id', 'name', 'card_image', 'rarity', 'species_name', 'category_name',
            'sort_order', 'stats', 'encountered', 'captured_at', 'model_url',
        ], array_keys($data));

        $this->assertFalse($data['encountered']);
        $this->assertNull($data['captured_at']);
        $this->assertNull($data['model_url']);
        $this->assertSame($this->species->common_name, $data['species_name']);
        $this->assertSame($this->category->name, $data['category_name']);
        $this->assertSame(['astucia' => 75, 'rapidez' => 85, 'fuerza' => 90], $data['stats']);
    }

    public function test_cards_index_returns_uploaded_model_url_when_owned(): void
    {
        Sanctum::actingAs($this->user);

        $card = $this->createCard();
        $this->capture($card);

        $data = $this->cardFromIndex($card);

        $this->assertTrue($data['encountered']);
        $this->assertNotNull($data['captured_at']);
        $this->assertSame(
            Storage::disk('public')->url('cards/1/models/jaguar.glb'),
            $data['model_url']
        );
    }

    public function test_cards_index_falls_back_to_external_model_url(): void
    {
        Sanctum::actingAs($this->user);

        $card = $this->createCard(['model_file' => null]);
        $this->capture($card);

        $this->assertSame(
            'https://example.com/models/jaguar.glb',
            $this->cardFromIndex($card)['model_url']
        );
    }

    public function test_cards_index_excludes_inactive_cards(): void
    {
        Sanctum::actingAs($this->user);

        $card = $this->createCard(['is_active' => false]);

        $this->assertNull($this->cardFromIndex($card));
    }

    public function test_cards_index_returns_304_when_etag_matches(): void
    {
        Sanctum::actingAs($this->user);

        $this->createCard();

        $etag = $this->getJson(route('api.cards.index'))
            ->assertOk()
            ->headers
            ->get('ETag');

        $this->assertNotEmpty($etag);

        $this->getJson(route('api.cards.index'), ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_removed_card_endpoints_no_longer_exist(): void
    {
        Sanctum::actingAs($this->user);

        $card = $this->createCard();

        $this->getJson("/api/cards/{$card->id}")->assertNotFound();
        $this->getJson('/api/my-cards')->assertNotFound();
        $this->getJson("/api/my-cards/{$card->id}")->assertNotFound();
        $this->getJson("/api/cards/{$card->id}/quiz")->assertNotFound();
        $this->postJson("/api/cards/{$card->id}/capture")->assertNotFound();
        $this->postJson("/api/cards/{$card->id}/quiz/submit")->assertNotFound();
    }
}
