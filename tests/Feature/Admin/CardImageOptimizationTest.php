<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use App\Services\ImageEncoding\ImageProfile;
use App\Services\ImageEncoding\NullWebpEncoder;
use App\Services\ImageEncoding\TemporaryFile;
use App\Services\ImageEncoding\WebpEncoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CardImageOptimizationTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const WEBP = 'tests/Fixtures/images/pixel.webp';

    private const PNG = 'tests/Fixtures/images/pixel.png';

    protected User $admin;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Sin Imagick ni GD salvo que la prueba ponga un codificador.
        $this->app->instance(WebpEncoder::class, new NullWebpEncoder);

        Permission::findOrCreate('cards.view');
        Permission::findOrCreate('cards.edit');

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.edit']);

        $category = SpeciesCategory::create([
            'name' => 'Aves '.uniqid(),
            'is_active' => true,
        ]);

        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Garza '.uniqid(),
            'scientific_name' => 'Ardea alba '.uniqid(),
            'slug' => 'garza-'.uniqid(),
            'is_active' => true,
        ]);

        $this->card = Card::create([
            'species_id' => $species->id,
            'name' => 'Garza Blanca',
            'rarity' => 'comun',
            'is_active' => false,
            'generated_image' => $this->storeOriginal('card.png', self::PNG),
        ]);
    }

    public function test_pending_lists_images_that_are_not_webp(): void
    {
        $this->card->update([
            'thumbnail_image' => $this->storeOriginal('thumb.webp', self::WEBP),
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cards.images.pending'))
            ->assertOk()
            ->assertJsonPath('data.server_encoder', null)
            ->assertJsonCount(1, 'data.images')
            ->assertJsonPath('data.images.0.image', 'generated')
            ->assertJsonPath('data.images.0.path', $this->card->generated_image);
    }

    public function test_browser_webp_replaces_the_image_and_deletes_the_original(): void
    {
        $original = $this->card->generated_image;

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $original,
                'file' => $this->upload('card.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $path = $this->card->refresh()->generated_image;

        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame($path, $response->json('data.path'));
        // El peso del original queda registrado para mostrar el ahorro.
        $this->assertSame(
            ['generated' => filesize(base_path(self::PNG))],
            $this->card->image_original_bytes
        );
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_changed_image_is_not_touched(): void
    {
        $original = $this->card->generated_image;

        $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => 'cards/otra.png',
                'file' => $this->upload('card.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame($original, $this->card->refresh()->generated_image);
        Storage::disk('public')->assertExists($original);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_result_that_is_not_lighter_keeps_the_original(): void
    {
        // Original del mismo peso que el WebP enviado.
        $this->card->update([
            'generated_image' => $this->storeOriginal('card.png', self::WEBP),
        ]);
        $original = $this->card->generated_image;

        $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $original,
                'file' => $this->upload('card.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame($original, $this->card->refresh()->generated_image);
        Storage::disk('public')->assertExists($original);
    }

    public function test_only_webp_files_are_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $this->card->generated_image,
                'file' => $this->upload('card.png', self::PNG),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_without_file_requires_a_server_encoder(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $this->card->generated_image,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        Storage::disk('public')->assertExists($this->card->generated_image);
    }

    public function test_server_encoder_optimizes_without_a_file(): void
    {
        $this->useFakeEncoder();
        $original = $this->card->generated_image;

        $this->actingAs($this->admin)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $original,
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertStringEndsWith('.webp', $this->card->refresh()->generated_image);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_optimizing_requires_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->post(route('admin.cards.images.optimize', [$this->card, 'generated']), [
                'expected_path' => $this->card->generated_image,
                'file' => $this->upload('card.webp', self::WEBP),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson(route('admin.cards.images.pending'))
            ->assertForbidden();
    }

    public function test_command_dry_run_changes_nothing(): void
    {
        $this->useFakeEncoder();
        $original = $this->card->generated_image;

        $this->artisan('cards:images:optimize', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($original, $this->card->refresh()->generated_image);
        Storage::disk('public')->assertExists($original);
    }

    public function test_command_converts_with_the_server_encoder(): void
    {
        $this->useFakeEncoder();
        $original = $this->card->generated_image;

        $this->artisan('cards:images:optimize')->assertSuccessful();

        $this->assertStringEndsWith('.webp', $this->card->refresh()->generated_image);
        Storage::disk('public')->assertMissing($original);
    }

    public function test_command_without_encoder_points_to_the_panel(): void
    {
        $this->artisan('cards:images:optimize')->assertFailed();

        Storage::disk('public')->assertExists($this->card->generated_image);
    }

    public function test_saving_a_card_converts_png_uploads_when_the_server_can(): void
    {
        $this->useFakeEncoder();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $this->card), [
                'species_id' => $this->card->species_id,
                'name' => $this->card->name,
                'rarity' => 'comun',
                'astucia' => 50,
                'rapidez' => 50,
                'fuerza' => 50,
                'duel_settings_source' => 'rarity',
                'is_active' => false,
                'fade_color' => '#4f6b5a',
                'title_font_family' => 'cinzel',
                'title_font_weight' => 800,
                'title_font_size' => '6xl',
                'messages_source' => 'default',
                'generated_image' => $this->upload('card.png', self::PNG),
            ])
            ->assertSessionHasNoErrors();

        $path = $this->card->refresh()->generated_image;

        // CardController escribe en storage/app/public real, no en el disco falso.
        $this->assertStringEndsWith('.webp', $path);
        $this->assertFileExists(storage_path('app/public/'.$path));

        File::delete(storage_path('app/public/'.$path));
        @rmdir(storage_path('app/public/cards/'.$this->card->id));
    }

    private function storeOriginal(string $name, string $fixture): string
    {
        $path = 'cards/1/'.uniqid().'-'.$name;

        Storage::disk('public')->put($path, file_get_contents(base_path($fixture)));

        return $path;
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
