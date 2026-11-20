<?php

namespace Tests\Feature\Admin;

use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\User;
use App\Services\ImageEncoding\ImageProfile;
use App\Services\ImageEncoding\NullWebpEncoder;
use App\Services\ImageEncoding\TemporaryFile;
use App\Services\ImageEncoding\WebpEncoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class SpeciesImageOptimizationTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const WEBP = 'tests/Fixtures/images/pixel.webp';

    private const PNG = 'tests/Fixtures/images/pixel.png';

    protected string $storagePath;

    protected Species $species;

    protected SpeciesImage $main;

    protected function setUp(): void
    {
        parent::setUp();

        // SpeciesController escribe con storage_path(): nunca en el storage real.
        $this->storagePath = sys_get_temp_dir().'/zoo-species-images-'.uniqid();
        $this->app->useStoragePath($this->storagePath);
        Storage::fake('public');

        // Sin Imagick ni GD salvo que la prueba ponga un codificador.
        $this->app->instance(WebpEncoder::class, new NullWebpEncoder);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("species.{$action}");
        }

        $category = SpeciesCategory::create(['name' => 'Aves', 'is_active' => true]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Garza',
            'scientific_name' => 'Ardea alba',
            'slug' => 'garza',
            'is_active' => true,
        ]);

        $this->main = $this->storeImage('main', self::PNG);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['species.view', 'species.create', 'species.edit', 'species.delete']);

        return $user;
    }

    public function test_pending_lists_species_images_that_are_not_webp(): void
    {
        $this->storeImage('gallery', self::WEBP);

        $this->actingAs($this->admin())
            ->getJson(route('admin.species.images.pending'))
            ->assertOk()
            ->assertJsonPath('data.server_encoder', null)
            ->assertJsonPath('data.profiles.thumbnail.maxWidth', 512)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.images.0.image_id', $this->main->id)
            ->assertJsonPath('data.images.0.image', 'main')
            ->assertJsonPath('data.images.0.species_name', 'Garza');
    }

    public function test_browser_webp_replaces_the_image_and_deletes_the_original(): void
    {
        $original = $this->main->path;

        $this->actingAs($this->admin())
            ->post(route('admin.species.images.optimize', $this->main), [
                'expected_path' => $original,
                'file' => $this->upload('main.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->main->refresh();

        $this->assertStringEndsWith('.webp', $this->main->path);
        $this->assertSame(filesize(base_path(self::PNG)), $this->main->original_bytes);
        Storage::disk('public')->assertExists($this->main->path);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_changed_image_is_not_touched(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.species.images.optimize', $this->main), [
                'expected_path' => 'species/otra.png',
                'file' => $this->upload('main.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertStatus(409);

        Storage::disk('public')->assertExists($this->main->path);
    }

    public function test_server_encoder_optimizes_without_a_file(): void
    {
        $this->useFakeEncoder();
        $original = $this->main->path;

        $this->actingAs($this->admin())
            ->post(route('admin.species.images.optimize', $this->main), [
                'expected_path' => $original,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.webp', $this->main->refresh()->path);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_optimizing_requires_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('species.view');

        $this->actingAs($viewer)
            ->getJson(route('admin.species.images.pending'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.species.images.optimize', $this->main), [
                'expected_path' => $this->main->path,
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_command_dry_run_changes_nothing(): void
    {
        $this->artisan('species:images:optimize', ['--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('public')->assertExists($this->main->path);
    }

    public function test_command_converts_with_the_server_encoder(): void
    {
        $this->useFakeEncoder();

        $this->artisan('species:images:optimize')->assertSuccessful();

        $this->assertStringEndsWith('.webp', $this->main->refresh()->path);
    }

    public function test_command_without_encoder_points_to_the_panel(): void
    {
        $this->artisan('species:images:optimize')->assertFailed();
    }

    public function test_saving_converts_png_uploads_and_records_the_original_weight(): void
    {
        $this->useFakeEncoder();

        $this->actingAs($this->admin())
            ->put(route('admin.species.update', $this->species), [
                'species_category_id' => $this->species->species_category_id,
                'common_name' => 'Garza',
                'scientific_name' => 'Ardea alba',
                'is_active' => true,
                'zone_id' => null,
                'latitude' => null,
                'longitude' => null,
                'thumbnail_image' => $this->upload('thumb.png', self::PNG),
                'image_original_bytes' => ['thumbnail' => 4_000_000],
                'gallery_images' => [$this->upload('foto.png', self::PNG)],
                'gallery' => [['file_index' => 0, 'alt_text' => '']],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.species.index'));

        $thumbnail = $this->species->images()->where('type', 'thumbnail')->firstOrFail();
        $gallery = $this->species->images()->where('type', 'gallery')->firstOrFail();

        $this->assertStringEndsWith('.webp', $thumbnail->path);
        $this->assertSame(4_000_000, $thumbnail->original_bytes);
        $this->assertStringEndsWith('.webp', $gallery->path);
        // Sin peso del navegador, el del archivo recibido.
        $this->assertSame(filesize(base_path(self::PNG)), $gallery->original_bytes);
    }

    public function test_edit_page_includes_current_weights(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.species.edit', $this->species))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('species.images.0.bytes', filesize(base_path(self::PNG)))
                ->where('species.images.0.original_bytes', null));
    }

    private function storeImage(string $type, string $fixture): SpeciesImage
    {
        $path = "species/{$this->species->id}/".uniqid().'-'.basename($fixture);

        Storage::disk('public')->put($path, file_get_contents(base_path($fixture)));

        return SpeciesImage::create([
            'species_id' => $this->species->id,
            'type' => $type,
            'path' => $path,
            'alt_text' => 'Garza',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function upload(string $name, string $fixture): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(base_path($fixture)));
    }

    /**
     * Codificador del servidor de prueba: devuelve el WebP de 1 px.
     */
    private function useFakeEncoder(): void
    {
        $this->app->instance(WebpEncoder::class, new class implements WebpEncoder
        {
            public function name(): ?string
            {
                return 'fake';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function encode(string $source, ImageProfile $profile): string
            {
                $temporary = TemporaryFile::create();

                copy(base_path('tests/Fixtures/images/pixel.webp'), $temporary);

                return $temporary;
            }
        });
    }
}
