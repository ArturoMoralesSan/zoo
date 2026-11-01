<?php

namespace Tests\Feature\Admin;

use App\Models\AssetCredit;
use App\Models\Card;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CardCreditsTest extends TestCase
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

    private function license(string $slug): int
    {
        return License::where('slug', $slug)->value('id');
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
        ], $attributes));
    }

    /**
     * Crédito CC BY 4.0 completo de Wikimedia Commons.
     */
    private function ccByCredit(array $overrides = []): array
    {
        return array_merge([
            'source_name' => 'wikimedia_commons',
            'source_url' => self::COMMONS_URL,
            'license_id' => $this->license('cc-by-4.0'),
            'title' => 'Jaguar resting',
            'author_name' => 'Jane Doe',
            'modified' => true,
            'modification_note' => 'Recortada/adaptada para la tarjeta',
        ], $overrides);
    }

    private function ownCredit(): array
    {
        return [
            'source_name' => 'own',
            'license_id' => $this->license('proprietary'),
        ];
    }

    private function updateCredits(Card $card, array $credits, array $overrides = [])
    {
        return $this->actingAs($this->admin)->put(
            route('admin.cards.update', $card),
            $this->cardPayload([
                'name' => $card->name,
                'is_active' => $card->is_active,
                'credits' => $credits,
                ...$overrides,
            ])
        );
    }

    private function glbUpload(string $fixture): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'glb');
        copy(base_path('tests/Fixtures/glb/'.$fixture), $path);

        return new UploadedFile($path, 'jaguar.glb', 'model/gltf-binary', null, true);
    }

    private function modelPayload(): array
    {
        return [
            'model_original_file' => $this->glbUpload('input/plain.glb'),
            'model_file' => $this->glbUpload('valid.glb'),
            'model_target_height' => '1.5',
            'model_original_height' => '2',
            'model_final_height' => '1.5',
            'model_final_width' => '0.375',
            'model_final_depth' => '0.75',
        ];
    }

    private function cardWithModel(array $attributes = []): Card
    {
        $card = $this->createCard($attributes);

        $model = 'cards/'.$card->id.'/models/actual.glb';
        $original = 'cards/'.$card->id.'/models/originals/original.glb';
        Storage::disk('public')->put($model, 'escalado');
        Storage::disk('local')->put($original, 'original');

        $card->update([
            'model_file' => $model,
            'model_original_file' => $original,
            'model_target_height' => 1,
            'model_original_height' => 2,
            'model_final_height' => 1,
            'model_final_width' => 0.25,
            'model_final_depth' => 0.5,
        ]);

        return $card->fresh();
    }

    // ---------------------------------------------------------------------
    // Validación
    // ---------------------------------------------------------------------

    public function test_attribution_license_requires_author_and_source_url(): void
    {
        $card = $this->createCard();

        $this->updateCredits($card, ['artwork' => $this->ccByCredit([
            'author_name' => null,
            'source_url' => null,
        ])])->assertSessionHasErrors([
            'credits.artwork.author_name' => 'La licencia elegida exige indicar el autor.',
            'credits.artwork.source_url' => 'La licencia elegida exige indicar la URL de origen.',
        ]);

        $this->assertSame(0, AssetCredit::count());
    }

    public function test_non_commercial_and_no_derivatives_licenses_are_rejected(): void
    {
        $card = $this->createCard();

        $this->updateCredits($card, ['artwork' => $this->ccByCredit([
            'license_id' => $this->license('cc-by-nc-4.0'),
        ])])->assertSessionHasErrors([
            'credits.artwork.license_id' => 'La licencia CC BY-NC 4.0 no se puede usar: prohíbe el uso comercial y el juego es comercial.',
        ]);

        $this->updateCredits($card, ['artwork' => $this->ccByCredit([
            'license_id' => $this->license('cc-by-nd-4.0'),
        ])])->assertSessionHasErrors([
            'credits.artwork.license_id' => 'La licencia CC BY-ND 4.0 no se puede usar: prohíbe modificar la obra y la tarjeta recorta y adapta la imagen.',
        ]);
    }

    public function test_restricted_licenses_are_accepted_when_allowed_by_config(): void
    {
        config(['licensing.allow_restricted' => true]);

        $card = $this->createCard();

        // Commons no aloja archivos NC: el origen es "Otro".
        $this->updateCredits($card, ['artwork' => $this->ccByCredit([
            'source_name' => 'other',
            'source_url' => 'https://www.flickr.com/photos/jane/123',
            'license_id' => $this->license('cc-by-nc-4.0'),
        ])])->assertSessionHasNoErrors();

        $this->assertSame('cc-by-nc-4.0', $card->credits()->first()->license->slug);
    }

    public function test_share_alike_requires_confirmation(): void
    {
        $card = $this->createCard();

        $credit = $this->ccByCredit(['license_id' => $this->license('cc-by-sa-4.0')]);

        $this->updateCredits($card, ['artwork' => $credit])
            ->assertSessionHasErrors('credits.artwork.share_alike_ack');

        $this->updateCredits($card, ['artwork' => [...$credit, 'share_alike_ack' => '1']])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $card->credits()->count());
    }

    public function test_wikipedia_file_pages_are_rejected_and_commons_pages_accepted(): void
    {
        $card = $this->createCard();

        foreach ([
            'https://en.wikipedia.org/wiki/File:Jaguar.jpg',
            'https://es.wikipedia.org/wiki/Archivo:Jaguar.jpg',
        ] as $url) {
            $this->updateCredits($card, ['artwork' => $this->ccByCredit(['source_url' => $url])])
                ->assertSessionHasErrors('credits.artwork.source_url');

            $this->assertStringContainsString(
                'fair use',
                session('errors')->first('credits.artwork.source_url')
            );
        }

        $this->updateCredits($card, ['artwork' => $this->ccByCredit(['source_url' => 'https://example.com/jaguar.jpg'])])
            ->assertSessionHasErrors('credits.artwork.source_url');

        $this->updateCredits($card, ['artwork' => $this->ccByCredit()])
            ->assertSessionHasNoErrors();
    }

    public function test_meshy_source_and_meshy_licenses_go_together(): void
    {
        $card = $this->cardWithModel();

        $this->updateCredits($card, ['model' => [
            'source_name' => 'meshy',
            'license_id' => $this->license('cc-by-4.0'),
            'author_name' => 'Jane Doe',
            'source_url' => 'https://www.meshy.ai/',
        ]])->assertSessionHasErrors('credits.model.license_id');

        $this->updateCredits($card, ['model' => [
            'source_name' => 'other',
            'license_id' => $this->license('meshy-free'),
        ]])->assertSessionHasErrors('credits.model.license_id');

        // La plantilla de Meshy ya atribuye: no exige autor ni URL.
        $this->updateCredits($card, ['model' => [
            'source_name' => 'meshy',
            'license_id' => $this->license('meshy-free'),
            'modified' => '1',
            'modification_note' => 'Escalado para RA',
        ]])->assertSessionHasNoErrors();

        $this->assertSame('Modelo 3D creado con Meshy – CC BY 4.0', $card->credits()->first()->attribution);
    }

    public function test_own_source_requires_a_proprietary_license(): void
    {
        $card = $this->createCard();

        $this->updateCredits($card, ['artwork' => [
            'source_name' => 'own',
            'license_id' => $this->license('cc0-1.0'),
        ]])->assertSessionHasErrors('credits.artwork.license_id');

        $this->updateCredits($card, ['artwork' => [
            'source_name' => 'own',
            'license_id' => $this->license('proprietary-commissioned'),
        ]])->assertSessionHasNoErrors();
    }

    public function test_new_upload_requires_a_credit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'artwork_image' => UploadedFile::fake()->createWithContent('fondo.png', base64_decode(self::PNG)),
                ...$this->modelPayload(),
            ]))
            ->assertSessionHasErrors([
                'credits.artwork.license_id',
                'credits.model.license_id',
            ]);

        $this->assertSame(0, Card::count());
    }

    public function test_new_model_upload_saves_its_credit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'is_active' => true,
                ...$this->modelPayload(),
                'credits' => ['model' => [
                    'source_name' => 'meshy',
                    'license_id' => $this->license('meshy-free'),
                    'modified' => '1',
                    'modification_note' => 'Escalado para RA',
                ]],
            ]))
            ->assertSessionHasNoErrors();

        $credit = Card::firstOrFail()->credit('model');

        $this->assertNotNull($credit);
        $this->assertSame('Escalado para RA', $credit->modification_note);
        $this->assertSame('Modelo 3D creado con Meshy – CC BY 4.0', $credit->attribution);
    }

    public function test_rescaling_the_model_keeps_its_credit_without_requiring_it(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel(['is_active' => true]);
        $card->credits()->create([
            'asset' => 'model',
            'license_id' => $this->license('meshy-free'),
            'source_name' => 'meshy',
        ]);

        $payload = collect($this->modelPayload())->except('model_original_file')->all();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                'is_active' => true,
                ...$payload,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotSame($card->model_file, $card->fresh()->model_file);
        $this->assertSame('meshy-free', $card->fresh()->credit('model')->license->slug);
    }

    public function test_rescaling_a_model_without_credit_does_not_require_one(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel(['artwork_image' => null]);

        $payload = collect($this->modelPayload())->except('model_original_file')->all();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                ...$payload,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AssetCredit::count());
    }

    // ---------------------------------------------------------------------
    // Activación
    // ---------------------------------------------------------------------

    public function test_inactive_card_cannot_be_activated_while_credits_are_missing(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel(['thumbnail_image' => 'cards/1/miniatura.png']);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                'is_active' => true,
                'credits' => ['artwork' => $this->ccByCredit()],
            ]))
            ->assertSessionHasErrors([
                'is_active' => 'No se puede activar la tarjeta: falta la licencia de Miniatura y Modelo 3D.',
            ]);

        $this->assertFalse($card->fresh()->is_active);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                'is_active' => true,
                'credits' => [
                    'artwork' => $this->ccByCredit(),
                    'thumbnail' => $this->ccByCredit(),
                    'model' => $this->ownCredit(),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($card->fresh()->is_active);
        $this->assertSame([], $card->fresh()->missingCredits());
    }

    public function test_card_cannot_be_created_active_without_credits(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'is_active' => true,
                'artwork_image' => UploadedFile::fake()->createWithContent('fondo.png', base64_decode(self::PNG)),
            ]))
            ->assertSessionHasErrors('is_active');

        // Sin recursos subidos no hace falta ningún crédito.
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload(['is_active' => true]))
            ->assertSessionHasNoErrors();
    }

    public function test_active_card_with_missing_credits_can_still_be_edited(): void
    {
        $card = $this->createCard(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => 'Jaguar renombrado',
                'is_active' => true,
                // Secciones vacías del formulario: se ignoran.
                'credits' => ['artwork' => ['license_id' => '', 'source_name' => '']],
            ]))
            ->assertSessionHasNoErrors();

        $card->refresh();

        $this->assertTrue($card->is_active);
        $this->assertSame('Jaguar renombrado', $card->name);
        $this->assertSame(['artwork'], array_map(fn ($asset) => $asset->value, $card->missingCredits()));
    }

    // ---------------------------------------------------------------------
    // Guardado
    // ---------------------------------------------------------------------

    public function test_each_platform_only_accepts_its_own_licenses(): void
    {
        $card = $this->createCard();

        $invalid = [
            // Pexels, Unsplash y Pixabay solo usan su licencia.
            ['source_name' => 'pexels', 'license_id' => $this->license('cc-by-4.0')],
            ['source_name' => 'unsplash', 'license_id' => $this->license('pexels-license')],
            ['source_name' => 'pixabay', 'license_id' => $this->license('cc0-1.0')],
            // Las licencias de plataforma no se usan con "Otro".
            ['source_name' => 'other', 'license_id' => $this->license('unsplash-license')],
            // Commons no aloja archivos NC ni ND.
            ['source_name' => 'wikimedia_commons', 'license_id' => $this->license('cc-by-nc-4.0')],
        ];

        config(['licensing.allow_restricted' => true]);

        foreach ($invalid as $credit) {
            $this->updateCredits($card, ['artwork' => [...$this->ccByCredit(), ...$credit]])
                ->assertSessionHasErrors('credits.artwork.license_id');
        }

        $this->updateCredits($card, ['artwork' => [
            'source_name' => 'pixabay',
            'license_id' => $this->license('pixabay-license'),
        ]])->assertSessionHasNoErrors();

        $this->assertSame('pixabay-license', $card->credits()->first()->license->slug);
    }

    public function test_sources_must_match_the_asset_type(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        // Meshy solo genera modelos.
        $this->updateCredits($card, ['artwork' => [
            'source_name' => 'meshy',
            'license_id' => $this->license('meshy-free'),
        ]])->assertSessionHasErrors([
            'credits.artwork.source_name' => 'Meshy solo ofrece modelos 3D: elige el origen de la imagen.',
        ]);

        // Los bancos de imágenes no ofrecen modelos.
        $this->updateCredits($card, ['model' => [
            'source_name' => 'pexels',
            'license_id' => $this->license('pexels-license'),
        ]])->assertSessionHasErrors('credits.model.source_name');

        $this->updateCredits($card, ['model' => [
            'source_name' => 'other',
            'license_id' => $this->license('cc-by-4.0'),
            'title' => 'Jaguar low poly',
            'author_name' => 'ana3d',
            'source_url' => 'https://sketchfab.com/3d-models/jaguar',
        ]])->assertSessionHasNoErrors();
    }

    public function test_3d_download_sites_use_their_licenses(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $model = fn (string $source, string $license, array $extra = []) => $this->updateCredits($card, ['model' => [
            'source_name' => $source,
            'license_id' => $this->license($license),
            'title' => 'Jaguar low poly',
            'author_name' => 'ana3d',
            'source_url' => 'https://sketchfab.com/3d-models/jaguar-123',
            ...$extra,
        ]]);

        // Sketchfab: las Creative Commons del autor, sin NC ni ND.
        $model('sketchfab', 'cc-by-4.0')->assertSessionHasNoErrors();
        $this->assertSame(
            '“Jaguar low poly” de ana3d, vía Sketchfab, CC BY 4.0',
            $card->fresh()->credit('model')->attribution
        );
        $model('sketchfab', 'cc-by-nc-4.0')->assertSessionHasErrors('credits.model.license_id');
        $model('sketchfab', 'pexels-license')->assertSessionHasErrors('credits.model.license_id');

        // Poly Pizza: CC0 o CC BY. Poly Haven: solo CC0.
        $model('poly_pizza', 'cc-by-3.0')->assertSessionHasNoErrors();
        $model('poly_pizza', 'cc-by-sa-4.0', ['share_alike_ack' => '1'])->assertSessionHasErrors('credits.model.license_id');
        $model('poly_haven', 'cc0-1.0')->assertSessionHasNoErrors();
        $model('poly_haven', 'cc-by-4.0')->assertSessionHasErrors('credits.model.license_id');
    }

    public function test_3d_sources_are_only_for_models(): void
    {
        $card = $this->createCard();

        foreach (['sketchfab', 'poly_pizza', 'poly_haven'] as $source) {
            $this->updateCredits($card, ['artwork' => [
                'source_name' => $source,
                'license_id' => $this->license('cc0-1.0'),
            ]])->assertSessionHasErrors('credits.artwork.source_name');
        }
    }

    public function test_form_receives_the_licenses_of_each_source(): void
    {
        $card = $this->createCard();

        $this->actingAs($this->admin)
            ->get(route('admin.cards.edit', $card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assetSources.1.value', 'pexels')
                ->where('assetSources.1.licenses', ['pexels-license'])
                ->where('assetSources.1.assets', ['artwork', 'thumbnail'])
                ->where('assetSources.5.value', 'meshy')
                ->where('assetSources.5.assets', ['model'])
                ->where('assetSources.8.value', 'poly_haven')
                ->where('assetSources.8.licenses', ['cc0-1.0']));
    }

    public function test_model_can_be_linked_to_the_artwork_credit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $model = [
            'source_name' => 'meshy',
            'license_id' => $this->license('meshy-free'),
            'based_on_artwork' => '1',
        ];

        $this->updateCredits($card, ['model' => $model])
            ->assertSessionHasErrors('credits.model.based_on_artwork');

        $this->updateCredits($card, [
            'artwork' => $this->ccByCredit(),
            'model' => $model,
        ])->assertSessionHasNoErrors();

        $card->load('credits');

        $this->assertSame($card->credit('artwork')->id, $card->credit('model')->derived_from_id);
        $this->assertSame(
            'Modelo 3D creado con Meshy a partir de la ilustración de la tarjeta – CC BY 4.0',
            $card->credit('model')->attribution
        );
    }

    // ---------------------------------------------------------------------
    // Eliminación
    // ---------------------------------------------------------------------

    public function test_removing_an_image_removes_its_credit(): void
    {
        $card = $this->createCard(['thumbnail_image' => 'cards/1/miniatura.png']);
        $card->credits()->create(['asset' => 'artwork', 'license_id' => $this->license('proprietary')]);
        $card->credits()->create(['asset' => 'thumbnail', 'license_id' => $this->license('proprietary')]);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.image.destroy', [$card, 'artwork']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['thumbnail'], $card->credits()->pluck('asset')->map->value->all());
    }

    public function test_removing_the_model_removes_its_credit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        $artwork = $card->credits()->create(['asset' => 'artwork', 'license_id' => $this->license('proprietary')]);
        $card->credits()->create([
            'asset' => 'model',
            'license_id' => $this->license('meshy-free'),
            'derived_from_id' => $artwork->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.model.destroy', $card))
            ->assertSessionHasNoErrors();

        $this->assertSame(['artwork'], $card->credits()->pluck('asset')->map->value->all());
    }

    public function test_deleting_a_card_removes_its_credits(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        $card->credits()->create(['asset' => 'artwork', 'license_id' => $this->license('proprietary')]);
        $card->credits()->create(['asset' => 'model', 'license_id' => $this->license('meshy-free')]);

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.destroy', $card))
            ->assertRedirect(route('admin.cards.index'));

        $this->assertSame(0, AssetCredit::count());
    }

    // ---------------------------------------------------------------------
    // Panel
    // ---------------------------------------------------------------------

    public function test_card_pages_report_missing_credits(): void
    {
        $card = $this->createCard(['thumbnail_image' => 'cards/1/miniatura.png']);
        $card->credits()->create(['asset' => 'artwork', 'license_id' => $this->license('proprietary')]);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cards.data.0.missing_credits', ['thumbnail'])
                ->missing('cards.data.0.credits'));

        $this->actingAs($this->admin)
            ->get(route('admin.cards.edit', $card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('credits.artwork.license_id', $this->license('proprietary'))
                ->where('credits.artwork.attribution', 'Obra propia')
                ->has('licenses', License::count())
                ->where('allowRestrictedLicenses', false));

        $this->actingAs($this->admin)
            ->get(route('admin.cards.show', $card))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('missingCredits', ['thumbnail'])
                ->has('credits.artwork'));
    }

    public function test_credits_overview_lists_assets_and_filters_missing_ones(): void
    {
        $card = $this->createCard(['thumbnail_image' => 'cards/1/miniatura.png']);
        $card->credits()->create([
            'asset' => 'artwork',
            'license_id' => $this->license('cc-by-4.0'),
            'source_name' => 'wikimedia_commons',
            'author_name' => 'Jane Doe',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/credits/Index')
                ->has('credits.data', 2)
                ->where('credits.data.0.asset', 'artwork')
                ->where('credits.data.0.license_short_name', 'CC BY 4.0')
                ->where('credits.data.0.edit_url', route('admin.cards.edit', $card).'#credit-artwork')
                ->where('missingCount', 1));

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index', ['missing' => 1]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('credits.data', 1)
                ->where('credits.data.0.asset', 'thumbnail')
                ->where('credits.data.0.missing', true));

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index', ['source' => 'pexels']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('credits.data', 0));
    }

    public function test_credits_overview_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.credits.index'))
            ->assertForbidden();
    }
}
