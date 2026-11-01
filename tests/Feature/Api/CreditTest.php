<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\Credit;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CreditTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LicenseSeeder::class);

        $category = SpeciesCategory::create([
            'name' => 'Mamíferos',
            'description' => 'Categoría de prueba',
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Jaguar',
            'scientific_name' => 'Panthera onca',
            'slug' => 'jaguar',
            'description' => 'Especie de prueba',
            'is_active' => true,
        ]);
    }

    private function createCard(array $attributes = []): Card
    {
        return Card::create(array_merge([
            'species_id' => $this->species->id,
            'name' => 'Carta '.uniqid(),
            'rarity' => 'comun',
            'artwork_image' => 'cards/1/fondo.png',
            'thumbnail_image' => 'cards/1/miniatura.png',
            'model_file' => 'cards/1/models/jaguar.glb',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'rarity',
            'is_active' => true,
        ], $attributes));
    }

    private function credit(Card $card, string $asset, string $license, array $attributes = []): Credit
    {
        return $card->credits()->create(array_merge([
            'asset' => $asset,
            'role' => 'origin',
            'license_id' => License::where('slug', $license)->value('id'),
            'title' => 'Título '.$asset,
            'author_name' => 'Jane Doe',
            'author_url' => 'https://commons.wikimedia.org/wiki/User:Jane_Doe',
            'source_name' => 'wikimedia_commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Jaguar.jpg',
            'modified' => true,
            'modification_note' => 'Recortada/adaptada para la tarjeta',
        ], $attributes));
    }

    private function credits(array $headers = []): TestResponse
    {
        return $this->getJson(route('api.v1.credits.index'), $headers);
    }

    public function test_works_without_a_token_and_returns_sections(): void
    {
        $card = $this->createCard(['name' => 'Jaguar']);
        $artwork = $this->credit($card, 'artwork', 'cc-by-sa-4.0', ['title' => 'Jaguar resting']);
        $this->credit($card, 'model', 'meshy-free', [
            'title' => null,
            'author_name' => null,
            'author_url' => null,
            'source_name' => 'meshy',
            'source_url' => null,
            'modification_note' => 'Escalado para RA',
            'based_on_asset' => 'artwork',
        ]);

        $this->credits()
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertHeader('ETag')
            ->assertHeader('Last-Modified')
            ->assertExactJson([
                'success' => true,
                'data' => [[
                    'card' => ['id' => $card->id, 'name' => 'Jaguar', 'species_name' => 'Jaguar'],
                    'credits' => [
                        [
                            'id' => $artwork->id,
                            'asset' => 'artwork',
                            'asset_label' => 'Ilustración',
                            'role' => 'origin',
                            'role_label' => 'Origen',
                            'title' => 'Jaguar resting',
                            'author' => ['name' => 'Jane Doe', 'url' => 'https://commons.wikimedia.org/wiki/User:Jane_Doe'],
                            'source' => ['name' => 'wikimedia_commons', 'label' => 'Wikimedia Commons', 'url' => 'https://commons.wikimedia.org/wiki/File:Jaguar.jpg'],
                            'license' => ['short_name' => 'CC BY-SA 4.0', 'name' => 'Creative Commons Atribución-CompartirIgual 4.0', 'url' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
                            'modified' => true,
                            'modification_note' => 'Recortada/adaptada para la tarjeta',
                            'attribution' => '“Jaguar resting” de Jane Doe, vía Wikimedia Commons, CC BY-SA 4.0, con cambios: Recortada/adaptada para la tarjeta',
                            'based_on_asset' => null,
                        ],
                        [
                            'id' => $artwork->id + 1,
                            'asset' => 'model',
                            'asset_label' => 'Modelo 3D',
                            'role' => 'origin',
                            'role_label' => 'Origen',
                            'title' => null,
                            'author' => null,
                            'source' => ['name' => 'meshy', 'label' => 'Meshy', 'url' => null],
                            'license' => ['short_name' => 'CC BY 4.0', 'name' => 'Meshy (plan gratuito) – Creative Commons Atribución 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/'],
                            'modified' => true,
                            'modification_note' => 'Escalado para RA',
                            // Una sola línea: el crédito de la imagen es la fila anterior.
                            'attribution' => 'Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0',
                            'based_on_asset' => 'artwork',
                        ],
                    ],
                ]],
                'meta' => [
                    'generated_at' => $this->credits()->json('meta.generated_at'),
                    'cards' => 1,
                    'credits' => 2,
                ],
            ]);
    }

    public function test_captured_and_uncaptured_active_cards_appear_but_inactive_never(): void
    {
        $captured = $this->createCard(['name' => 'Capturada']);
        $uncaptured = $this->createCard(['name' => 'Sin capturar']);
        $inactive = $this->createCard(['name' => 'Tarjeta secreta', 'is_active' => false]);

        CardCapture::create([
            'user_id' => User::factory()->create()->id,
            'card_id' => $captured->id,
            'captured_at' => now(),
        ]);

        foreach ([$captured, $uncaptured, $inactive] as $card) {
            $this->credit($card, 'artwork', 'cc-by-4.0', ['title' => 'Imagen de '.$card->name]);
        }

        $response = $this->credits()->assertOk();

        $this->assertSame(['Capturada', 'Sin capturar'], array_column($response->json('data.*.card'), 'name'));
        $this->assertStringNotContainsString('Tarjeta secreta', $response->getContent());
        $this->assertStringNotContainsString('"id":'.$inactive->id.',', $response->getContent());
    }

    public function test_own_private_and_optional_credits_of_active_cards_are_sent(): void
    {
        $card = $this->createCard(['name' => 'Jaguar']);
        $this->credit($card, 'artwork', 'proprietary', ['title' => 'Jaguar del Norte', 'author_name' => null, 'source_name' => 'own']);
        $this->credit($card, 'thumbnail', 'pexels-license', ['title' => null, 'author_name' => 'Ana López', 'source_name' => 'pexels', 'modified' => false]);
        $this->credit($card, 'model', 'meshy-paid', ['title' => null, 'author_name' => null, 'source_name' => 'meshy']);

        $response = $this->credits()->assertOk();

        $this->assertSame([
            '“Jaguar del Norte” – obra propia',
            'Ana López, vía Pexels, Licencia de Pexels',
            'Modelo 3D creado con Meshy',
        ], $response->json('data.0.credits.*.attribution'));

        $this->assertSame(3, $response->json('meta.credits'));
    }

    public function test_model_based_on_an_own_artwork_says_so_without_repeating_it(): void
    {
        $card = $this->createCard();
        $artwork = $this->credit($card, 'artwork', 'proprietary', ['title' => 'Ilustración propia', 'author_name' => null, 'source_name' => 'own']);
        $this->credit($card, 'model', 'meshy-free', ['source_name' => 'meshy', 'based_on_asset' => 'artwork']);

        $response = $this->credits()->assertOk();

        $this->assertSame('“Ilustración propia” – obra propia', $response->json('data.0.credits.0.attribution'));
        $this->assertSame('artwork', $response->json('data.0.credits.1.based_on_asset'));
        $this->assertSame(
            'Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0',
            $response->json('data.0.credits.1.attribution')
        );
        // El modelo no repite el crédito de la ilustración.
        $this->assertSame(1, collect($response->json('data.0.credits.*.attribution'))
            ->filter(fn (string $line) => str_contains($line, 'Ilustración propia'))
            ->count());
    }

    public function test_credits_are_ordered_by_asset_then_role_and_carry_their_role(): void
    {
        $card = $this->createCard();
        $this->credit($card, 'thumbnail', 'proprietary', ['title' => 'Jaguar en la selva', 'author_name' => null, 'source_name' => 'own', 'based_on_asset' => 'model']);
        $this->credit($card, 'thumbnail', 'cc0-1.0', ['role' => 'component', 'title' => 'Forest HDRI', 'author_name' => null, 'source_name' => 'poly_haven', 'modified' => false]);
        $this->credit($card, 'artwork', 'ai-adobe-firefly', ['title' => null, 'author_name' => null, 'source_name' => 'ai', 'ai_prompt' => 'jaguar', 'ai_checked_at' => now(), 'modified' => false]);
        $this->credit($card, 'model', 'meshy-free', ['title' => null, 'author_name' => null, 'source_name' => 'meshy', 'based_on_asset' => 'artwork']);
        $this->credit($card, 'model', 'pexels-license', ['role' => 'based_on', 'title' => null, 'author_name' => 'Ana López', 'source_name' => 'pexels', 'modified' => false]);

        $credits = $this->credits()->assertOk()->json('data.0.credits');

        $this->assertSame(
            [['artwork', 'origin'], ['thumbnail', 'origin'], ['thumbnail', 'component'], ['model', 'origin'], ['model', 'based_on']],
            array_map(fn ($credit) => [$credit['asset'], $credit['role']], $credits)
        );

        $this->assertSame([
            'Imagen generada con IA (Adobe Firefly)',
            '“Jaguar en la selva”, a partir del modelo 3D de la tarjeta – obra propia',
            'Incluye: “Forest HDRI”, vía Poly Haven, CC0 1.0',
            'Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0',
            'Basado en: Ana López, vía Pexels, Licencia de Pexels',
        ], array_column($credits, 'attribution'));

        $this->assertSame(['Origen', 'Origen', 'Incluye', 'Origen', 'Basado en'], array_column($credits, 'role_label'));
        $this->assertSame('model', $credits[1]['based_on_asset']);
    }

    public function test_only_assets_that_still_exist_are_sent(): void
    {
        $card = $this->createCard(['thumbnail_image' => null, 'model_file' => '']);
        $this->credit($card, 'artwork', 'cc-by-4.0');
        $this->credit($card, 'thumbnail', 'cc-by-4.0');
        $this->credit($card, 'model', 'meshy-free', ['source_name' => 'meshy']);

        $this->assertSame(['artwork'], array_column($this->credits()->json('data.0.credits'), 'asset'));
    }

    public function test_sections_and_credits_are_ordered(): void
    {
        $second = $this->createCard(['name' => 'B', 'sort_order' => 2]);
        $first = $this->createCard(['name' => 'A', 'sort_order' => 1]);
        $tieLater = $this->createCard(['name' => 'C', 'sort_order' => 2]);

        foreach ([$second, $first, $tieLater] as $card) {
            // Se crean en desorden: modelo, miniatura, ilustración.
            $this->credit($card, 'model', 'meshy-free', ['source_name' => 'meshy']);
            $this->credit($card, 'thumbnail', 'cc0-1.0');
            $this->credit($card, 'artwork', 'cc-by-4.0');
        }

        $response = $this->credits()->assertOk();

        $this->assertSame([$first->id, $second->id, $tieLater->id], $response->json('data.*.card.id'));
        $this->assertSame(['artwork', 'thumbnail', 'model'], $response->json('data.0.credits.*.asset'));
    }

    public function test_internal_fields_never_leak(): void
    {
        $card = $this->createCard();
        $this->credit($card, 'artwork', 'cc-by-4.0', [
            'ai_prompt' => 'PROMPT-SECRETO',
            'ai_checked_at' => now(),
            'notes' => 'NOTA-INTERNA',
            'acquired_at' => '2026-01-15',
        ]);

        $body = $this->credits()->assertOk()->getContent();

        foreach ([
            'NOTA-INTERNA', 'notes', 'show_in_credits', 'acquired_at', '2026-01-15', 'derived_from',
            'PROMPT-SECRETO', 'ai_prompt', 'ai_checked',
            'creditable', 'created_at', 'updated_at', 'cards/1', 'storage', '.glb', '.png',
            'license_id', 'is_proprietary', 'sort_order',
        ] as $hidden) {
            $this->assertStringNotContainsString($hidden, $body, "Se filtró: {$hidden}");
        }
    }

    public function test_empty_catalog_returns_empty_data(): void
    {
        $this->createCard();

        $this->credits()
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.cards', 0)
            ->assertJsonPath('meta.credits', 0);
    }

    public function test_returns_304_for_matching_etag_or_date(): void
    {
        $this->credit($this->createCard(), 'artwork', 'cc-by-4.0');

        $first = $this->credits()->assertOk();

        $this->credits(['If-None-Match' => $first->headers->get('ETag')])
            ->assertStatus(304)
            ->assertContent('');

        $this->credits(['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->assertStatus(304);

        $this->credits(['If-None-Match' => '"otro"'])->assertOk();
    }

    public function test_cache_is_invalidated_after_credit_card_license_and_species_updates(): void
    {
        $card = $this->createCard(['name' => 'Jaguar']);
        $credit = $this->credit($card, 'artwork', 'cc-by-4.0', ['title' => 'Primero']);

        $etag = fn () => $this->credits()->headers->get('ETag');

        $initial = $etag();
        $this->assertSame($initial, $etag());

        $credit->update(['title' => 'Segundo']);
        $this->assertStringContainsString('Segundo', $this->credits()->getContent());

        $card->update(['name' => 'Jaguar renombrado']);
        $this->assertSame('Jaguar renombrado', $this->credits()->json('data.0.card.name'));

        License::where('slug', 'cc-by-4.0')->first()->update(['name' => 'Atribución (nuevo nombre)']);
        $this->assertSame('Atribución (nuevo nombre)', $this->credits()->json('data.0.credits.0.license.name'));

        $this->species->update(['common_name' => 'Yaguareté']);
        $this->assertSame('Yaguareté', $this->credits()->json('data.0.card.species_name'));

        $card->update(['is_active' => false]);
        $this->assertSame([], $this->credits()->json('data'));

        $this->assertNotSame($initial, $etag());
    }

    public function test_unrelated_card_changes_keep_the_cache(): void
    {
        $card = $this->createCard();
        $this->credit($card, 'artwork', 'cc-by-4.0');

        $generatedAt = $this->credits()->json('meta.generated_at');

        $this->travel(5)->seconds();
        $card->update(['astucia' => 99]);

        $this->assertSame($generatedAt, $this->credits()->json('meta.generated_at'));
    }

    public function test_rate_limit_returns_429(): void
    {
        RateLimiter::clear('credits');
        config(['licensing.api.rate_limit' => 3]);

        foreach (range(1, 3) as $request) {
            $this->credits()->assertOk();
        }

        $this->credits()->assertStatus(429);
    }
}
