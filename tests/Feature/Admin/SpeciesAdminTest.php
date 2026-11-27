<?php

namespace Tests\Feature\Admin;

use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\User;
use App\Services\ImageEncoding\NullWebpEncoder;
use App\Services\ImageEncoding\WebpEncoder;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class SpeciesAdminTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected string $storagePath;

    protected SpeciesCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // moveUploadedFile escribe con storage_path(): nunca en el storage real.
        $this->storagePath = sys_get_temp_dir().'/zoo-species-test-'.uniqid();
        $this->app->useStoragePath($this->storagePath);
        Storage::fake('public');
        // Sin compresión en el servidor: las pruebas de galería miran los archivos tal cual.
        $this->app->instance(WebpEncoder::class, new NullWebpEncoder);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("species.{$action}");
        }

        $this->category = SpeciesCategory::create(['name' => 'Mamíferos', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        // Las rutas de especies exigen los cuatro permisos (ver Gotchas en AGENTS.md).
        $user->givePermissionTo(['species.view', 'species.create', 'species.edit', 'species.delete']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'species_category_id' => $this->category->id,
            'common_name' => 'Jaguar',
            'scientific_name' => 'Panthera onca',
            'habitats' => ['wetland', 'tropical_rainforest'],
            'diet' => 'carnivore',
            'conservation_status' => 'NT',
            'is_active' => true,
            // El formulario siempre envía la ubicación, aunque vaya vacía.
            'zone_id' => null,
            'location_name' => null,
            'latitude' => null,
            'longitude' => null,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function species(array $attributes = []): Species
    {
        static $sequence = 0;
        $sequence++;

        return Species::create([
            'species_category_id' => $this->category->id,
            'common_name' => "Especie {$sequence}",
            'scientific_name' => "Genus species{$sequence}",
            'slug' => "especie-{$sequence}",
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function galleryImage(Species $species, int $sortOrder): SpeciesImage
    {
        $path = "species/{$species->id}/gallery-{$sortOrder}.jpg";
        Storage::disk('public')->put($path, 'x');

        return SpeciesImage::create([
            'species_id' => $species->id,
            'type' => 'gallery',
            'path' => $path,
            'alt_text' => $species->common_name,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    public function test_store_saves_catalog_codes(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.species.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.species.index'));

        $species = Species::firstOrFail();

        $this->assertSame(Diet::Carnivore, $species->diet);
        $this->assertSame(ConservationStatus::NearThreatened, $species->conservation_status);
        $this->assertSame('Selva tropical, Humedal', $species->habitatLabel());
    }

    public function test_rejects_values_outside_the_catalogs(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.species.store'), $this->payload([
                'habitats' => ['Selva'],
                'diet' => 'Carnívoro',
                'conservation_status' => 'Vulnerable',
            ]))
            ->assertSessionHasErrors(['habitats.0', 'diet', 'conservation_status']);
    }

    public function test_rejects_a_php_file_as_card_image_or_3d_model(): void
    {
        $php = UploadedFile::fake()->createWithContent('shell.php', '<?php echo "x";');

        $this->actingAs($this->admin())
            ->post(route('admin.species.store'), $this->payload([
                'card_image' => $php,
                'model_file' => $php,
            ]))
            ->assertSessionHasErrors(['card_image', 'model_file']);

        $this->assertSame(0, Species::count());
    }

    public function test_card_image_is_saved_with_the_extension_of_its_content(): void
    {
        /*
         * Una imagen real con nombre .html pasa la regla image, pero no se sirve como
         * página. Archivo real: UploadedFile::fake() deduce el tipo por el nombre.
         */
        $path = $this->storagePath.'/avatar-upload';
        File::ensureDirectoryExists($this->storagePath);
        imagepng(imagecreatetruecolor(4, 4), $path);

        $this->actingAs($this->admin())
            ->post(route('admin.species.store'), $this->payload([
                'card_image' => new UploadedFile($path, 'avatar.html', null, null, true),
            ]))
            ->assertSessionHasNoErrors();

        $path = SpeciesImage::where('type', 'card')->value('path');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
    }

    public function test_store_orders_new_gallery_images_and_keeps_alt_text(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.species.store'), $this->payload([
                'gallery_images' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.png'),
                ],
                // La segunda foto va primero; la primera sin texto alternativo.
                'gallery' => [
                    ['file_index' => 1, 'alt_text' => 'Jaguar descansando'],
                    ['file_index' => 0, 'alt_text' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors()
            // Fotos nuevas sin créditos: se abre su sección Créditos, con aviso porque está activa.
            ->assertRedirect(route('admin.species.credits.index', Species::firstOrFail()))
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash(
                'toast.message',
                'Especie creada y activa, pero falta la licencia de 2 imágenes: la app las muestra sin sus créditos. Regístralos aquí.'
            );

        $images = SpeciesImage::where('type', 'gallery')->orderBy('sort_order')->get();

        $this->assertCount(2, $images);
        $this->assertStringEndsWith('.png', $images[0]->path);
        $this->assertSame('Jaguar descansando', $images[0]->alt_text);
        $this->assertSame('Jaguar', $images[1]->alt_text);
    }

    public function test_update_reorders_deletes_and_adds_gallery_images_on_save(): void
    {
        $species = $this->species(['common_name' => 'Jaguar']);
        $first = $this->galleryImage($species, 0);
        $second = $this->galleryImage($species, 1);
        $third = $this->galleryImage($species, 2);

        $this->actingAs($this->admin())
            ->put(route('admin.species.update', $species), $this->payload([
                'gallery_images' => [UploadedFile::fake()->image('nueva.jpg')],
                'gallery' => [
                    ['file_index' => 0, 'alt_text' => 'Foto nueva'],
                    ['id' => $third->id, 'alt_text' => 'Tercera'],
                    ['id' => $first->id, 'alt_text' => ''],
                ],
                'deleted_gallery_ids' => [$second->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.species.index'));

        $gallery = $species->images()->where('type', 'gallery')->get();

        $this->assertSame(
            ['Foto nueva', 'Tercera', 'Jaguar'],
            $gallery->pluck('alt_text')->all(),
        );
        $this->assertSame([0, 1, 2], $gallery->pluck('sort_order')->all());
        $this->assertModelMissing($second);
        Storage::disk('public')->assertMissing($second->path);
        Storage::disk('public')->assertExists($first->path);
    }

    public function test_update_keeps_images_the_form_does_not_mention(): void
    {
        $species = $this->species();
        $first = $this->galleryImage($species, 0);
        $second = $this->galleryImage($species, 1);

        $this->actingAs($this->admin())
            ->put(route('admin.species.update', $species), $this->payload([
                'gallery' => [['id' => $second->id, 'alt_text' => 'Segunda']],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.species.index'))
            // Sus fotos no tienen créditos y la especie está activa.
            ->assertInertiaFlash('toast.type', 'warning')
            ->assertInertiaFlash(
                'toast.message',
                'Especie actualizada correctamente. Está activa sin la licencia de 2 imágenes: la app las muestra sin sus créditos.'
            );

        $this->assertSame(
            [$second->id, $first->id],
            $species->images()->where('type', 'gallery')->pluck('id')->all(),
        );
    }

    public function test_replacing_an_image_keeps_its_credits_and_asks_to_review_them(): void
    {
        $this->seed(LicenseSeeder::class);

        $species = $this->species();
        $main = SpeciesImage::create([
            'species_id' => $species->id,
            'type' => 'main',
            'path' => "species/{$species->id}/main.jpg",
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $gallery = $this->galleryImage($species, 0);

        $credit = $main->credits()->create([
            'asset' => 'image',
            'role' => 'origin',
            'source_name' => 'own',
            'license_id' => License::where('slug', 'proprietary')->value('id'),
        ]);

        $response = $this->actingAs($this->admin())
            ->put(route('admin.species.update', $species), $this->payload([
                'main_image' => UploadedFile::fake()->image('nueva.png', 40, 40),
                'gallery' => [['id' => $gallery->id, 'alt_text' => 'Galería']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNotSame("species/{$species->id}/main.jpg", $main->fresh()->path);
        $this->assertNotNull($credit->fresh());
        $response->assertInertiaFlash('toast.type', 'warning');
        // La foto de la galería no tiene créditos y la especie está activa.
        $response->assertInertiaFlash(
            'toast.message',
            'Especie actualizada. La imagen principal cambió: revisa sus créditos. Está activa sin la licencia de 1 imagen: la app las muestra sin sus créditos.'
        );
    }

    public function test_inactive_species_without_credits_saves_without_warning(): void
    {
        $species = $this->species(['is_active' => false]);
        $gallery = $this->galleryImage($species, 0);

        $this->actingAs($this->admin())
            ->put(route('admin.species.update', $species), $this->payload([
                'is_active' => false,
                'gallery' => [['id' => $gallery->id, 'alt_text' => 'Foto']],
            ]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', 'Especie actualizada correctamente.');
    }

    public function test_edit_lists_the_images_without_credits(): void
    {
        $this->withoutVite();
        $species = $this->species();
        $gallery = $this->galleryImage($species, 0);

        $this->actingAs($this->admin())
            ->get(route('admin.species.edit', $species))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('uncreditedImageIds', [$gallery->id]));
    }

    public function test_update_cannot_touch_another_species_gallery(): void
    {
        $species = $this->species();
        $other = $this->species();
        $foreign = $this->galleryImage($other, 0);

        $this->actingAs($this->admin())
            ->put(route('admin.species.update', $species), $this->payload([
                'gallery' => [['id' => $foreign->id, 'alt_text' => 'x']],
                'deleted_gallery_ids' => [$foreign->id],
            ]))
            ->assertSessionHasErrors(['gallery.0.id', 'deleted_gallery_ids.0']);

        $this->assertModelExists($foreign);
    }

    public function test_api_keeps_spanish_labels_and_adds_codes(): void
    {
        $species = $this->species([
            'habitats' => [Habitat::Mangrove, Habitat::TropicalRainforest],
            'diet' => Diet::Herbivore,
            'conservation_status' => ConservationStatus::LeastConcern,
            'is_active' => true,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/species/{$species->id}")
            ->assertOk()
            ->assertJsonPath('data.species.habitat', 'Selva tropical, Manglar')
            ->assertJsonPath('data.species.diet', 'Herbívoro')
            ->assertJsonPath('data.species.conservation_status', 'Preocupación menor')
            ->assertJsonPath('data.species.habitats.0.code', 'tropical_rainforest')
            ->assertJsonPath('data.species.diet_code', 'herbivore')
            ->assertJsonPath('data.species.conservation_status_code', 'LC');
    }

    public function test_migration_converts_free_text_to_codes(): void
    {
        $migration = require database_path('migrations/2026_11_19_120000_use_catalogs_for_species_classification.php');
        $migration->down();

        $id = DB::table('species')->insertGetId([
            'species_category_id' => $this->category->id,
            'common_name' => 'Águila real',
            'scientific_name' => 'Aquila chrysaetos',
            'slug' => 'aguila-real',
            'habitat' => 'Montañas y zonas abiertas',
            'diet' => 'Carnivora',
            'conservation_status' => 'preocupacion menor',
            'is_active' => true,
        ]);

        $unknown = DB::table('species')->insertGetId([
            'species_category_id' => $this->category->id,
            'common_name' => 'Otro',
            'scientific_name' => 'Otro otro',
            'slug' => 'otro',
            'habitat' => 'Ríos, lugar raro',
            'diet' => 'Algo',
            'conservation_status' => 'En peligro de extinción',
            'is_active' => true,
        ]);

        $migration->up();

        $species = Species::findOrFail($id);
        $this->assertSame([Habitat::Grassland, Habitat::Mountain], $species->sortedHabitats()->all());
        $this->assertSame(Diet::Carnivore, $species->diet);
        $this->assertSame(ConservationStatus::LeastConcern, $species->conservation_status);

        $other = Species::findOrFail($unknown);
        $this->assertSame([Habitat::Freshwater], $other->sortedHabitats()->all());
        $this->assertNull($other->diet);
        $this->assertSame(ConservationStatus::Endangered, $other->conservation_status);
    }

    public function test_typescript_catalog_matches_the_enums(): void
    {
        $source = File::get(resource_path('js/lib/speciesCatalogs.ts'));
        preg_match_all("/value:\s*'([^']+)',\s*label:\s*'([^']+)'/", $source, $matches, PREG_SET_ORDER);

        $expected = [];

        foreach ([ConservationStatus::cases(), Diet::cases(), Habitat::cases()] as $cases) {
            foreach ($cases as $case) {
                $expected[] = [$case->value, $case->label()];
            }
        }

        $this->assertSame(
            $expected,
            array_map(fn (array $match) => [$match[1], $match[2]], $matches),
        );
    }
}
