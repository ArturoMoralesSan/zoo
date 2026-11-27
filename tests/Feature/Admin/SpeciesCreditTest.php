<?php

namespace Tests\Feature\Admin;

use App\Models\Credit;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Créditos de las imágenes de una especie (principal, miniatura, galería).
 */
class SpeciesCreditTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const COMMONS_URL = 'https://commons.wikimedia.org/wiki/File:Jaguar.jpg';

    protected User $admin;

    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(LicenseSeeder::class);

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate('species.'.$action);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['species.view', 'species.create', 'species.edit', 'species.delete']);

        $this->species = $this->createSpecies('Jaguar', 'jaguar');
    }

    private function createSpecies(string $name, string $slug): Species
    {
        $category = SpeciesCategory::firstOrCreate(
            ['name' => 'Mamíferos'],
            ['description' => 'Categoría de prueba', 'is_active' => true]
        );

        return Species::create([
            'species_category_id' => $category->id,
            'common_name' => $name,
            'scientific_name' => 'Panthera '.$slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function image(string $type = 'main', array $attributes = [], ?Species $species = null): SpeciesImage
    {
        return ($species ?? $this->species)->images()->create(array_merge([
            'type' => $type,
            'path' => 'species/1/'.$type.'-'.uniqid().'.webp',
            'alt_text' => 'Jaguar',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function license(string $slug): int
    {
        return License::where('slug', $slug)->value('id');
    }

    private function ccBy(array $overrides = []): array
    {
        return array_merge([
            'asset' => 'image',
            'role' => 'origin',
            'source_name' => 'wikimedia_commons',
            'source_url' => self::COMMONS_URL,
            'license_id' => $this->license('cc-by-4.0'),
            'title' => 'Jaguar resting',
            'author_name' => 'Jane Doe',
        ], $overrides);
    }

    private function store(SpeciesImage $image, array $data, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin)
            ->post(route('admin.species.images.credits.store', [$this->species, $image]), $data);
    }

    public function test_index_lists_images_in_order_with_status_and_app_preview(): void
    {
        $gallery2 = $this->image('gallery', ['sort_order' => 2]);
        $gallery1 = $this->image('gallery', ['sort_order' => 1]);
        $main = $this->image('main');
        $this->image('card');
        $main->credits()->create([...$this->ccBy(), 'license_id' => $this->license('cc-by-4.0')]);

        $this->actingAs($this->admin)
            ->get(route('admin.species.credits.index', $this->species))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/species-credits/Index')
                ->has('images', 3)
                ->where('images.0.id', $main->id)
                ->where('images.0.label', 'Imagen principal')
                ->where('images.0.status', 'complete')
                ->where('images.1.id', $gallery1->id)
                ->where('images.1.label', 'Galería 1')
                ->where('images.1.status', 'missing')
                ->where('images.2.id', $gallery2->id)
                ->where('images.2.label', 'Galería 2')
                ->has('appPreview', 1)
                ->where('appPreview.0.image_label', 'Imagen principal'));
    }

    public function test_admin_can_add_edit_and_delete_credits(): void
    {
        $image = $this->image('gallery');

        $this->store($image, $this->ccBy())
            ->assertRedirect(route('admin.species.credits.index', $this->species).'#image-'.$image->id);

        $credit = $image->credits()->sole();
        $this->assertSame('image', $credit->asset->value);
        $this->assertSame('species_image', $credit->creditable_type);

        $this->actingAs($this->admin)
            ->get(route('admin.species.images.credits.edit', [$this->species, $image, $credit]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/species-credits/Edit')
                ->where('credit.id', $credit->id)
                ->where('image.id', $image->id)
                ->where('assetSources.0.assets.origin', ['image']));

        $this->actingAs($this->admin)
            ->put(route('admin.species.images.credits.update', [$this->species, $image, $credit]), $this->ccBy(['title' => 'Jaguar dormido']))
            ->assertRedirect();
        $this->assertSame('Jaguar dormido', $credit->fresh()->title);

        $this->actingAs($this->admin)
            ->delete(route('admin.species.images.credits.destroy', [$this->species, $image, $credit]))
            ->assertRedirect();
        $this->assertNull($credit->fresh());
    }

    public function test_permissions_are_checked_per_action(): void
    {
        $image = $this->image();
        $credit = $image->credits()->create($this->ccBy());

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('species.view');

        $this->actingAs($viewer)->get(route('admin.species.credits.index', $this->species))->assertOk();
        $this->store($image, $this->ccBy(), $viewer)->assertForbidden();

        $editor = User::factory()->create();
        $editor->givePermissionTo(['species.view', 'species.edit']);

        $this->actingAs($editor)
            ->get(route('admin.species.images.credits.create', [$this->species, $image]))
            ->assertOk();
        $this->actingAs($editor)
            ->delete(route('admin.species.images.credits.destroy', [$this->species, $image, $credit]))
            ->assertForbidden();
    }

    public function test_image_and_credit_must_belong_to_the_url(): void
    {
        $other = $this->createSpecies('Ocelote', 'ocelote');
        $foreignImage = $this->image('main', [], $other);
        $image = $this->image();
        $foreignCredit = $foreignImage->credits()->create($this->ccBy());

        $this->actingAs($this->admin)
            ->get(route('admin.species.images.credits.create', [$this->species, $foreignImage]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('admin.species.images.credits.edit', [$this->species, $image, $foreignCredit]))
            ->assertNotFound();
    }

    public function test_only_the_image_asset_is_accepted_and_based_on_asset_is_ignored(): void
    {
        $image = $this->image();

        $this->store($image, $this->ccBy(['asset' => 'artwork']))->assertSessionHasErrors('asset');

        $this->store($image, $this->ccBy(['based_on_asset' => 'artwork']))->assertSessionHasNoErrors();
        $this->assertNull($image->credits()->sole()->based_on_asset);
    }

    public function test_card_images_and_images_without_file_cannot_be_credited(): void
    {
        $this->store($this->image('card'), $this->ccBy())->assertSessionHasErrors('asset');
        $this->store($this->image('main', ['path' => '']), $this->ccBy())->assertSessionHasErrors('asset');
    }

    public function test_an_image_has_only_one_origin_but_many_other_credits(): void
    {
        $image = $this->image();
        $this->store($image, $this->ccBy())->assertSessionHasNoErrors();

        $this->store($image, $this->ccBy())->assertSessionHasErrors('role');
        $this->store($image, $this->ccBy(['role' => 'based_on']))->assertSessionHasNoErrors();

        $this->assertSame(2, $image->credits()->count());
    }

    public function test_sources_and_licenses_follow_the_same_rules_as_cards(): void
    {
        $image = $this->image();

        // Meshy solo genera modelos: no es origen de una imagen.
        $this->store($image, $this->ccBy([
            'source_name' => 'meshy',
            'license_id' => $this->license('meshy-free'),
        ]))->assertSessionHasErrors('source_name');

        // CC BY exige autor.
        $this->store($image, $this->ccBy(['author_name' => null]))->assertSessionHasErrors('author_name');
    }

    public function test_overview_lists_species_images_and_filters_by_owner(): void
    {
        Permission::findOrCreate('cards.view');
        $this->admin->givePermissionTo('cards.view');

        $main = $this->image('main');
        $credit = $main->credits()->create($this->ccBy());
        $gallery = $this->image('gallery');

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index', ['owner' => 'species']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/credits/Index')
                ->has('credits.data', 2)
                ->where('credits.data.0.owner.type', 'species')
                ->where('credits.data.0.owner.name', 'Jaguar')
                ->where('credits.data.0.asset_label', 'Imagen principal')
                ->where('credits.data.0.edit_url', route('admin.species.images.credits.edit', [$this->species, $main, $credit]))
                ->where('credits.data.1.missing', true)
                ->where('credits.data.1.asset_label', 'Galería 1')
                ->where('credits.data.1.edit_url', route('admin.species.credits.index', $this->species).'#image-'.$gallery->id)
                ->where('missingCount', 1));

        $this->actingAs($this->admin)
            ->get(route('admin.credits.index', ['owner' => 'card']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('credits.data', 0));
    }

    public function test_species_editors_can_use_the_commons_lookup(): void
    {
        $editor = User::factory()->create();
        $editor->givePermissionTo(['species.view', 'species.edit']);

        $this->actingAs($editor)
            ->postJson(route('admin.credits.commons-lookup'), ['url' => ''])
            ->assertUnprocessable();
    }

    public function test_deleting_a_gallery_image_removes_its_credits(): void
    {
        $image = $this->image('gallery');
        $credit = $image->credits()->create($this->ccBy());

        $this->actingAs($this->admin)
            ->delete(route('admin.species.images.destroy', [$this->species, $image]))
            ->assertRedirect();

        $this->assertNull(SpeciesImage::find($image->id));
        $this->assertNull(Credit::find($credit->id));
    }
}
