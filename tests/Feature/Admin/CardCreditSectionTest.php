<?php

namespace Tests\Feature\Admin;

use App\Enums\CreditAsset;
use App\Models\Card;
use App\Models\Credit;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Sección "Créditos" de la tarjeta: varios créditos por recurso (origen,
 * basado en, incluye), imágenes de IA y renders de modelos.
 */
class CardCreditSectionTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const COMMONS_URL = 'https://commons.wikimedia.org/wiki/File:Jaguar.jpg';

    protected User $admin;

    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LicenseSeeder::class);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate('cards.'.$action);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.create', 'cards.edit', 'cards.delete']);

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

    // ---------------------------------------------------------------------
    // Ayudas
    // ---------------------------------------------------------------------

    private function license(string $slug): int
    {
        return License::where('slug', $slug)->value('id');
    }

    private function createCard(array $attributes = []): Card
    {
        return Card::create(array_merge([
            'species_id' => $this->species->id,
            'name' => 'Jaguar del Norte',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'rarity',
            'is_active' => false,
            'artwork_image' => 'cards/1/fondo.png',
            'thumbnail_image' => 'cards/1/miniatura.png',
            'model_file' => 'cards/1/models/jaguar.glb',
        ], $attributes));
    }

    private function cardPayload(array $overrides = []): array
    {
        return array_merge([
            'fade_color' => '#4f6b5a',
            'accent_color' => null,
            'title_font_family' => 'cinzel',
            'title_font_weight' => 800,
            'title_font_size' => '6xl',
            'messages_source' => 'default',
            'species_id' => $this->species->id,
            'name' => 'Jaguar del Norte',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'rarity',
            'is_active' => false,
        ], $overrides);
    }

    /**
     * Crédito CC BY 4.0 completo de Wikimedia Commons.
     */
    private function ccBy(array $overrides = []): array
    {
        return array_merge([
            'asset' => 'artwork',
            'role' => 'origin',
            'source_name' => 'wikimedia_commons',
            'source_url' => self::COMMONS_URL,
            'license_id' => $this->license('cc-by-4.0'),
            'title' => 'Jaguar resting',
            'author_name' => 'Jane Doe',
            'modified' => '1',
            'modification_note' => 'Recortada/adaptada para la tarjeta',
        ], $overrides);
    }

    private function own(array $overrides = []): array
    {
        return array_merge([
            'asset' => 'artwork',
            'role' => 'origin',
            'source_name' => 'own',
            'license_id' => $this->license('proprietary'),
        ], $overrides);
    }

    private function store(Card $card, array $data): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.cards.credits.store', $card), $data);
    }

    private function addCredit(Card $card, string $asset, string $role, string $license, array $attributes = []): Credit
    {
        return $card->credits()->create(array_merge([
            'asset' => $asset,
            'role' => $role,
            'license_id' => $this->license($license),
            'source_name' => 'own',
        ], $attributes));
    }

    // ---------------------------------------------------------------------
    // Pantallas y permisos
    // ---------------------------------------------------------------------

    public function test_index_shows_each_asset_with_its_credits_status_and_app_preview(): void
    {
        $card = $this->createCard(['model_file' => null]);
        $this->addCredit($card, 'artwork', 'origin', 'cc-by-4.0', ['title' => 'Jaguar', 'author_name' => 'Jane Doe', 'source_name' => 'wikimedia_commons']);
        $this->addCredit($card, 'artwork', 'component', 'cc0-1.0', ['title' => 'Forest HDRI', 'source_name' => 'poly_haven']);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.credits.index', $card))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/card-credits/Index')
                ->where('assets.0.value', 'artwork')
                ->where('assets.0.status', 'complete')
                ->has('assets.0.credits', 2)
                ->where('assets.0.credits.1.role_label', 'Incluye')
                ->where('assets.1.status', 'missing')
                ->where('assets.2.status', 'no_file')
                ->where('appPreview.1.attribution', 'Incluye: “Forest HDRI”, vía Poly Haven, CC0 1.0'));
    }

    public function test_create_preselects_asset_and_role_and_lists_sources_per_role(): void
    {
        $card = $this->createCard();

        $this->actingAs($this->admin)
            ->get(route('admin.cards.credits.create', [$card, 'asset' => 'thumbnail', 'role' => 'based_on']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/card-credits/Create')
                ->where('preset.asset', 'thumbnail')
                ->where('preset.role', 'based_on')
                ->where('assets.0.has_file', true)
                // Sketchfab: origen solo de modelos; "Basado en" de cualquier recurso.
                ->where('assetSources.6.value', 'sketchfab')
                ->where('assetSources.6.assets.origin', ['model'])
                ->where('assetSources.6.assets.based_on', ['artwork', 'thumbnail', 'model']));
    }

    public function test_permissions_are_checked_per_action(): void
    {
        $card = $this->createCard();
        $credit = $this->addCredit($card, 'artwork', 'origin', 'proprietary');

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)->get(route('admin.cards.credits.index', $card))->assertOk();
        $this->actingAs($viewer)->get(route('admin.cards.credits.create', $card))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.cards.credits.store', $card), $this->own())->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.cards.credits.destroy', [$card, $credit]))->assertForbidden();
    }

    public function test_credit_cannot_be_reached_through_another_card(): void
    {
        $card = $this->createCard();
        $other = $this->createCard(['name' => 'Otra']);
        $credit = $this->addCredit($other, 'artwork', 'origin', 'proprietary');

        $this->actingAs($this->admin)
            ->get(route('admin.cards.credits.edit', [$card, $credit]))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Guardar, editar y eliminar
    // ---------------------------------------------------------------------

    public function test_admin_can_add_edit_and_delete_credits(): void
    {
        $card = $this->createCard();

        $this->store($card, $this->ccBy())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.credits.index', $card).'#credit-artwork');

        $credit = $card->credits()->firstOrFail();

        $this->assertSame(
            '“Jaguar resting” de Jane Doe, vía Wikimedia Commons, CC BY 4.0, con cambios: Recortada/adaptada para la tarjeta',
            $credit->attribution
        );

        $this->actingAs($this->admin)
            ->put(route('admin.cards.credits.update', [$card, $credit]), $this->ccBy(['title' => 'Jaguar dormido']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Jaguar dormido', $credit->fresh()->title);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.credits.destroy', [$card, $credit]))
            ->assertRedirect(route('admin.cards.credits.index', $card).'#credit-artwork');

        $this->assertSame(0, Credit::count());
    }

    public function test_an_asset_has_only_one_origin_but_many_other_credits(): void
    {
        $card = $this->createCard();
        $origin = $this->addCredit($card, 'artwork', 'origin', 'proprietary');

        $this->store($card, $this->own())
            ->assertSessionHasErrors('role');

        // Editar el origen existente sí se permite.
        $this->actingAs($this->admin)
            ->put(route('admin.cards.credits.update', [$card, $origin]), $this->own(['title' => 'Nuevo título']))
            ->assertSessionHasNoErrors();

        // Varios "Incluye" para el mismo recurso.
        foreach (['Forest HDRI', 'Textura de hojas'] as $title) {
            $this->store($card, [
                'asset' => 'artwork',
                'role' => 'component',
                'source_name' => 'poly_haven',
                'license_id' => $this->license('cc0-1.0'),
                'title' => $title,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $card->credits()->count());
    }

    public function test_credits_need_the_asset_file(): void
    {
        $card = $this->createCard(['thumbnail_image' => null]);

        $this->store($card, $this->own(['asset' => 'thumbnail']))
            ->assertSessionHasErrors('asset');
    }

    // ---------------------------------------------------------------------
    // Licencias y orígenes
    // ---------------------------------------------------------------------

    public function test_attribution_license_requires_author_and_source_url(): void
    {
        $card = $this->createCard();

        $this->store($card, $this->ccBy(['author_name' => null, 'source_url' => null]))
            ->assertSessionHasErrors([
                'author_name' => 'La licencia elegida exige indicar el autor.',
                'source_url' => 'La licencia elegida exige indicar la URL de origen.',
            ]);
    }

    public function test_non_commercial_and_no_derivatives_licenses_are_rejected_unless_allowed(): void
    {
        $card = $this->createCard();

        $other = ['source_name' => 'other', 'source_url' => 'https://www.flickr.com/photos/jane/1'];

        $this->store($card, $this->ccBy([...$other, 'license_id' => $this->license('cc-by-nc-4.0')]))
            ->assertSessionHasErrors([
                'license_id' => 'La licencia CC BY-NC 4.0 no se puede usar: prohíbe el uso comercial y el juego es comercial.',
            ]);

        $this->store($card, $this->ccBy([...$other, 'license_id' => $this->license('cc-by-nd-4.0')]))
            ->assertSessionHasErrors('license_id');

        config(['licensing.allow_restricted' => true]);

        $this->store($card, $this->ccBy([...$other, 'license_id' => $this->license('cc-by-nc-4.0')]))
            ->assertSessionHasNoErrors();
    }

    public function test_share_alike_requires_confirmation(): void
    {
        $card = $this->createCard();

        $credit = $this->ccBy(['license_id' => $this->license('cc-by-sa-4.0')]);

        $this->store($card, $credit)->assertSessionHasErrors('share_alike_ack');
        $this->store($card, [...$credit, 'share_alike_ack' => '1'])->assertSessionHasNoErrors();
    }

    public function test_wikipedia_file_pages_are_rejected_and_commons_pages_accepted(): void
    {
        $card = $this->createCard();

        $this->store($card, $this->ccBy(['source_url' => 'https://es.wikipedia.org/wiki/Archivo:Jaguar.jpg']))
            ->assertSessionHasErrors('source_url');

        $this->assertStringContainsString('fair use', session('errors')->first('source_url'));

        $this->store($card, $this->ccBy())->assertSessionHasNoErrors();
    }

    public function test_each_platform_only_accepts_its_own_licenses(): void
    {
        $card = $this->createCard();

        config(['licensing.allow_restricted' => true]);

        foreach ([
            ['pexels', 'cc-by-4.0'],
            ['unsplash', 'pexels-license'],
            ['pixabay', 'cc0-1.0'],
            ['other', 'unsplash-license'],
            ['wikimedia_commons', 'cc-by-nc-4.0'],
            ['own', 'cc0-1.0'],
            ['other', 'ai-openai'],
        ] as [$source, $license]) {
            $this->store($card, $this->ccBy(['source_name' => $source, 'license_id' => $this->license($license)]))
                ->assertSessionHasErrors('license_id');
        }

        $this->store($card, ['asset' => 'artwork', 'role' => 'origin', 'source_name' => 'pixabay', 'license_id' => $this->license('pixabay-license')])
            ->assertSessionHasNoErrors();
    }

    public function test_sources_depend_on_the_asset_and_the_role(): void
    {
        $card = $this->createCard();

        // Como origen: Meshy solo modelos, Pexels solo imágenes, IA solo imágenes.
        $this->store($card, ['asset' => 'artwork', 'role' => 'origin', 'source_name' => 'meshy', 'license_id' => $this->license('meshy-free')])
            ->assertSessionHasErrors('source_name');
        $this->store($card, ['asset' => 'model', 'role' => 'origin', 'source_name' => 'pexels', 'license_id' => $this->license('pexels-license')])
            ->assertSessionHasErrors('source_name');
        $this->store($card, ['asset' => 'model', 'role' => 'origin', 'source_name' => 'ai', 'license_id' => $this->license('ai-openai'), 'ai_checked' => '1'])
            ->assertSessionHasErrors('source_name');

        // Como "Basado en", una imagen puede venir de un modelo de Sketchfab (render)…
        $this->store($card, [
            'asset' => 'thumbnail',
            'role' => 'based_on',
            'source_name' => 'sketchfab',
            'license_id' => $this->license('cc-by-4.0'),
            'title' => 'Jaguar low poly',
            'author_name' => 'ana3d',
            'source_url' => 'https://sketchfab.com/3d-models/jaguar-123',
        ])->assertSessionHasNoErrors();

        // …y un modelo, de una foto de Pexels (la que se dio a Meshy).
        $this->store($card, [
            'asset' => 'model',
            'role' => 'based_on',
            'source_name' => 'pexels',
            'license_id' => $this->license('pexels-license'),
        ])->assertSessionHasNoErrors();
    }

    public function test_3d_download_sites_use_their_licenses(): void
    {
        $card = $this->createCard();

        $model = fn (string $source, string $license) => $this->store($card, [
            'asset' => 'model',
            'role' => 'component',
            'source_name' => $source,
            'license_id' => $this->license($license),
            'title' => 'Jaguar low poly',
            'author_name' => 'ana3d',
            'source_url' => 'https://sketchfab.com/3d-models/jaguar-123',
        ]);

        $model('sketchfab', 'cc-by-4.0')->assertSessionHasNoErrors();
        $model('sketchfab', 'cc-by-nc-4.0')->assertSessionHasErrors('license_id');
        $model('poly_pizza', 'cc-by-3.0')->assertSessionHasNoErrors();
        $model('poly_haven', 'cc-by-4.0')->assertSessionHasErrors('license_id');
        $model('poly_haven', 'cc0-1.0')->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------------
    // Imágenes generadas con IA
    // ---------------------------------------------------------------------

    public function test_ai_images_require_the_review_and_keep_the_prompt(): void
    {
        $card = $this->createCard();

        $ai = [
            'asset' => 'artwork',
            'role' => 'origin',
            'source_name' => 'ai',
            'license_id' => $this->license('ai-adobe-firefly'),
            'ai_prompt' => 'Jaguar en la selva, ilustración',
            'modified' => '1',
            'modification_note' => 'Retocada a mano',
        ];

        $this->store($card, $ai)->assertSessionHasErrors([
            'ai_checked' => 'Confirma la revisión de la imagen generada con IA.',
        ]);

        $this->store($card, [...$ai, 'ai_checked' => '1'])->assertSessionHasNoErrors();

        $credit = $card->credits()->firstOrFail();

        $this->assertSame('Jaguar en la selva, ilustración', $credit->ai_prompt);
        $this->assertNotNull($credit->ai_checked_at);
        $this->assertSame('Imagen generada con IA (Adobe Firefly)', $credit->attribution);

        // Al editar se conserva la fecha de la primera revisión.
        $checkedAt = $credit->ai_checked_at;
        $this->travel(1)->days();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.credits.update', [$card, $credit]), [...$ai, 'ai_checked' => '1', 'title' => 'Jaguar'])
            ->assertSessionHasNoErrors();

        $this->assertEquals($checkedAt, $credit->fresh()->ai_checked_at);
    }

    public function test_ai_licenses_are_only_for_the_ai_source(): void
    {
        $card = $this->createCard();

        $this->store($card, ['asset' => 'artwork', 'role' => 'origin', 'source_name' => 'ai', 'license_id' => $this->license('proprietary'), 'ai_checked' => '1'])
            ->assertSessionHasErrors('license_id');
    }

    // ---------------------------------------------------------------------
    // Recursos hechos a partir de otros
    // ---------------------------------------------------------------------

    public function test_thumbnail_rendered_from_the_card_model(): void
    {
        $card = $this->createCard();

        $this->store($card, $this->own([
            'asset' => 'thumbnail',
            'title' => 'Jaguar en la selva',
            'based_on_asset' => 'model',
        ]))->assertSessionHasNoErrors();

        $card->load('credits');
        $render = $card->credit('thumbnail');

        $this->assertSame('model', $render->based_on_asset->value);
        $this->assertSame('“Jaguar en la selva”, a partir del modelo 3D de la tarjeta – obra propia', $render->attribution);

        // El render no está completo mientras el modelo no tenga licencia.
        $this->assertFalse($card->hasCompleteCredits(CreditAsset::Thumbnail));

        $this->actingAs($this->admin)
            ->get(route('admin.cards.credits.index', $card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assets.1.status', 'missing')
                ->where('assets.1.alerts.0.message', 'La miniatura se hizo a partir del modelo 3D, que aún no tiene licencia.'));

        $this->addCredit($card, 'model', 'origin', 'meshy-free', ['source_name' => 'meshy']);

        $this->assertTrue($card->fresh()->hasCompleteCredits(CreditAsset::Thumbnail));
    }

    public function test_share_alike_propagates_along_the_chain(): void
    {
        $card = $this->createCard();
        $this->addCredit($card, 'artwork', 'origin', 'cc-by-sa-4.0', ['source_name' => 'wikimedia_commons', 'author_name' => 'Jane Doe']);
        $this->addCredit($card, 'model', 'origin', 'meshy-free', ['source_name' => 'meshy', 'based_on_asset' => 'artwork']);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.credits.index', $card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assets.2.alerts.0.type', 'warning')
                ->where('assets.2.alerts.0.message', 'Se hizo a partir de la ilustración (CC BY-SA 4.0): también debe compartirse con esa licencia.'));
    }

    public function test_based_on_asset_must_exist_and_cannot_loop(): void
    {
        $card = $this->createCard(['model_file' => null]);

        $this->store($card, $this->own(['asset' => 'thumbnail', 'based_on_asset' => 'model']))
            ->assertSessionHasErrors('based_on_asset');

        $this->store($card, $this->own(['based_on_asset' => 'artwork']))
            ->assertSessionHasErrors('based_on_asset');

        $this->addCredit($card, 'thumbnail', 'origin', 'proprietary', ['based_on_asset' => 'artwork']);

        $this->store($card, $this->own(['based_on_asset' => 'thumbnail']))
            ->assertSessionHasErrors('based_on_asset');
    }

    public function test_based_on_asset_is_ignored_for_other_roles(): void
    {
        $card = $this->createCard();

        $this->store($card, [
            'asset' => 'artwork',
            'role' => 'component',
            'source_name' => 'poly_haven',
            'license_id' => $this->license('cc0-1.0'),
            'based_on_asset' => 'model',
        ])->assertSessionHasNoErrors();

        $this->assertNull($card->credits()->first()->based_on_asset);
    }

    public function test_render_of_an_outside_model_continues_to_its_based_on_credit(): void
    {
        $card = $this->createCard();

        $this->store($card, $this->own(['asset' => 'thumbnail', 'then' => 'add_based_on_model']))
            ->assertRedirect(route('admin.cards.credits.create', [$card, 'asset' => 'thumbnail', 'role' => 'based_on']));
    }

    // ---------------------------------------------------------------------
    // Tarjeta: activación, archivos y eliminación
    // ---------------------------------------------------------------------

    public function test_activation_requires_complete_credits_for_every_uploaded_asset(): void
    {
        $card = $this->createCard(['model_file' => null]);
        $this->addCredit($card, 'artwork', 'origin', 'proprietary');
        $this->addCredit($card, 'thumbnail', 'origin', 'proprietary', ['based_on_asset' => 'artwork']);
        // Un "Incluye" no sustituye al origen.
        $this->addCredit($card, 'artwork', 'component', 'cc0-1.0', ['source_name' => 'poly_haven']);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload(['is_active' => true]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($card->fresh()->is_active);
    }

    public function test_activation_is_blocked_while_an_origin_is_missing(): void
    {
        $card = $this->createCard(['model_file' => null]);
        $this->addCredit($card, 'artwork', 'origin', 'proprietary');
        $this->addCredit($card, 'thumbnail', 'component', 'cc0-1.0', ['source_name' => 'poly_haven']);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload(['is_active' => true]))
            ->assertSessionHasErrors([
                'is_active' => 'No se puede activar la tarjeta: falta la licencia de Miniatura. Regístrala en la sección Créditos de la tarjeta.',
            ]);
    }

    public function test_active_card_with_missing_credits_can_still_be_edited(): void
    {
        $card = $this->createCard(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload(['name' => 'Renombrada', 'is_active' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renombrada', $card->fresh()->name);
    }

    public function test_new_card_with_files_is_created_inactive_and_opens_its_credits(): void
    {
        $image = fn () => UploadedFile::fake()->createWithContent('fondo.png', base64_decode(self::PNG));

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload(['is_active' => true, 'artwork_image' => $image()]))
            ->assertSessionHasErrors('is_active');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload(['artwork_image' => $image()]))
            ->assertSessionHasNoErrors();

        $card = Card::firstOrFail();

        $response->assertRedirect(route('admin.cards.credits.index', $card));

        Storage::disk('public')->delete($card->artwork_image);
        @unlink(storage_path('app/public/'.$card->artwork_image));
    }

    public function test_removing_an_asset_removes_its_credits_and_links(): void
    {
        $card = $this->createCard();
        $this->addCredit($card, 'artwork', 'origin', 'proprietary');
        $this->addCredit($card, 'artwork', 'component', 'cc0-1.0', ['source_name' => 'poly_haven']);
        $this->addCredit($card, 'thumbnail', 'origin', 'proprietary', ['based_on_asset' => 'artwork']);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.image.destroy', [$card, 'artwork']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['thumbnail'], $card->credits()->pluck('asset')->map->value->all());
        $this->assertNull($card->credits()->first()->based_on_asset);
    }

    public function test_removing_the_model_and_deleting_the_card_remove_credits(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->createCard();
        $this->addCredit($card, 'model', 'origin', 'meshy-free', ['source_name' => 'meshy']);
        $this->addCredit($card, 'artwork', 'origin', 'proprietary');

        $this->actingAs($this->admin)->delete(route('admin.cards.model.destroy', $card));
        $this->assertSame(['artwork'], $card->credits()->pluck('asset')->map->value->all());

        $this->actingAs($this->admin)->delete(route('admin.cards.destroy', $card));
        $this->assertSame(0, Credit::count());
    }

    public function test_card_pages_show_the_credit_summary(): void
    {
        $card = $this->createCard(['model_file' => null]);
        $this->addCredit($card, 'artwork', 'origin', 'proprietary', ['title' => 'Jaguar']);
        $this->addCredit($card, 'artwork', 'component', 'cc0-1.0', ['source_name' => 'poly_haven']);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cards.data.0.missing_credits', ['thumbnail'])
                ->missing('cards.data.0.credits'));

        foreach (['admin.cards.edit', 'admin.cards.show'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route, $card))
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('creditSummary.0.asset', 'artwork')
                    ->where('creditSummary.0.complete', true)
                    ->where('creditSummary.0.origin', '“Jaguar” – obra propia')
                    ->where('creditSummary.0.extra_count', 1)
                    ->where('creditSummary.1.complete', false)
                    ->missing('card.credits'));
        }
    }

    // ---------------------------------------------------------------------
    // Vista general y migración de datos
    // ---------------------------------------------------------------------

    public function test_overview_lists_every_credit_and_missing_origins(): void
    {
        $card = $this->createCard(['model_file' => null]);
        $origin = $this->addCredit($card, 'artwork', 'origin', 'proprietary');
        $this->addCredit($card, 'artwork', 'component', 'cc0-1.0', ['source_name' => 'poly_haven']);

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('credits.data', 3)
                ->where('credits.data.0.role', 'origin')
                ->where('credits.data.0.edit_url', route('admin.cards.credits.edit', [$card, $origin]))
                ->where('credits.data.1.role_label', 'Incluye')
                ->where('credits.data.2.missing', true)
                ->where('credits.data.2.edit_url', route('admin.cards.credits.index', $card).'#credit-thumbnail')
                ->where('missingCount', 1));

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index', ['role' => 'component']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('credits.data', 1));
    }

    public function test_data_migration_copies_old_credits_as_origins(): void
    {
        // La tabla anterior ya se eliminó: se recrea vacía para la prueba.
        (require database_path('migrations/2026_10_31_150000_drop_asset_credits_table.php'))->down();

        $card = $this->createCard();
        $now = now();

        $base = [
            'creditable_type' => 'card',
            'creditable_id' => $card->id,
            'license_id' => $this->license('proprietary'),
            'source_name' => 'own',
            'modified' => true,
            'modification_note' => 'Recortada',
            'show_in_credits' => false,
            'notes' => 'Nota',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $artworkId = DB::table('asset_credits')->insertGetId([...$base, 'asset' => 'artwork', 'title' => 'Ilustración']);
        DB::table('asset_credits')->insert([...$base, 'asset' => 'model', 'derived_from_id' => $artworkId]);

        (require database_path('migrations/2026_10_31_140001_copy_asset_credits_to_credits_table.php'))->up();

        $card->load('credits');

        $this->assertSame(2, $card->credits->count());
        $this->assertSame('origin', $card->credit('artwork')->role->value);
        $this->assertSame('Ilustración', $card->credit('artwork')->title);
        $this->assertSame('Nota', $card->credit('artwork')->notes);
        $this->assertNull($card->credit('artwork')->based_on_asset);
        $this->assertSame('artwork', $card->credit('model')->based_on_asset->value);
    }
}
