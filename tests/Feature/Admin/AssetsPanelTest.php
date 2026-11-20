<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\Link;
use App\Models\Menu;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\User;
use App\Services\ImageEncoding\NullWebpEncoder;
use App\Services\ImageEncoding\WebpEncoder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class AssetsPanelTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const WEBP = 'tests/Fixtures/images/pixel.webp';

    private const PNG = 'tests/Fixtures/images/pixel.png';

    protected function setUp(): void
    {
        parent::setUp();

        // La página no depende de un build de Vite.
        $this->withoutVite();

        Storage::fake('public');
        Storage::fake('local');
        $this->app->instance(WebpEncoder::class, new NullWebpEncoder);

        foreach (['assets.view', 'assets.optimize', 'cards.view'] as $permission) {
            Permission::findOrCreate($permission);
        }
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function store(string $path, string $fixture): string
    {
        Storage::disk('public')->put($path, file_get_contents(base_path($fixture)));

        return $path;
    }

    public function test_page_requires_the_view_permission(): void
    {
        $this->actingAs($this->userWith('cards.view'))
            ->get(route('admin.assets.index'))
            ->assertForbidden();

        $this->actingAs($this->userWith('assets.view'))
            ->get(route('admin.assets.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/assets/Index')
                ->where('canOptimize', false));
    }

    public function test_summary_adds_up_sizes_savings_and_pending_images(): void
    {
        $category = SpeciesCategory::create(['name' => 'Aves', 'is_active' => true]);
        $species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Garza',
            'scientific_name' => 'Ardea alba',
            'slug' => 'garza',
            'is_active' => true,
        ]);

        $webpBytes = filesize(base_path(self::WEBP));
        $pngBytes = filesize(base_path(self::PNG));

        // Una imagen ya optimizada (con su peso original) y una pendiente.
        SpeciesImage::create([
            'species_id' => $species->id,
            'type' => 'main',
            'path' => $this->store('species/1/main.webp', self::WEBP),
            'original_bytes' => 5000,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        SpeciesImage::create([
            'species_id' => $species->id,
            'type' => 'gallery',
            'path' => $this->store('species/1/foto.png', self::PNG),
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Card::create([
            'species_id' => $species->id,
            'name' => 'Garza Blanca',
            'rarity' => 'comun',
            'is_active' => false,
            'thumbnail_image' => $this->store('cards/1/thumb.webp', self::WEBP),
            'image_original_bytes' => ['thumbnail' => 9000],
            'model_file' => $this->store('cards/1/model.glb', self::WEBP),
            'model_final_height' => 1.2,
        ]);

        $this->actingAs($this->userWith('assets.view', 'assets.optimize'))
            ->get(route('admin.assets.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canOptimize', true)
                ->where('summary.server_encoder', null)
                ->where('summary.groups.0.key', 'card_images')
                ->where('summary.groups.0.files', 1)
                ->where('summary.groups.0.original_bytes', 9000)
                ->where('summary.groups.0.pending', 0)
                ->where('summary.groups.1.key', 'species_images')
                ->where('summary.groups.1.files', 2)
                ->where('summary.groups.1.bytes', $webpBytes + $pngBytes)
                // Sin peso original registrado cuenta el actual.
                ->where('summary.groups.1.original_bytes', 5000 + $pngBytes)
                ->where('summary.groups.1.pending', 1)
                ->where('summary.groups.2.key', 'card_models')
                ->where('summary.groups.2.pending', null)
                ->where('summary.models.0.card_name', 'Garza Blanca')
                ->where('summary.models.0.original_bytes', null)
                ->has('summary.heaviest', 4)
                ->where('summary.heaviest.0.bytes', max($webpBytes, $pngBytes)));
    }

    public function test_cards_index_links_to_resources_only_with_permission(): void
    {
        Permission::findOrCreate('cards.create');
        Permission::findOrCreate('cards.edit');
        Permission::findOrCreate('cards.delete');
        $cardPermissions = ['cards.view', 'cards.create', 'cards.edit', 'cards.delete'];

        $this->actingAs($this->userWith(...$cardPermissions))
            ->get(route('admin.cards.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canViewAssets', false));

        $this->actingAs($this->userWith('assets.view', ...$cardPermissions))
            ->get(route('admin.cards.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canViewAssets', true));
    }

    public function test_migration_gives_admin_the_permissions_and_adds_the_link(): void
    {
        $admin = Role::findOrCreate('admin');
        $menu = Menu::create(['name' => 'Administración', 'order' => 6, 'is_submenu' => true]);

        $migration = require database_path('migrations/2026_11_21_100000_add_assets_panel_permissions_and_link.php');
        $migration->up();

        $this->assertTrue($admin->fresh()->hasPermissionTo('assets.view'));
        $this->assertTrue($admin->fresh()->hasPermissionTo('assets.optimize'));

        $link = Link::query()->where('route', 'admin.assets.index')->firstOrFail();
        $this->assertSame($menu->id, $link->menu_id);
        $this->assertSame('HardDrive', $link->icon);

        // Volver a correrla no duplica el enlace.
        $migration->up();
        $this->assertSame(1, Link::query()->where('route', 'admin.assets.index')->count());
    }
}
