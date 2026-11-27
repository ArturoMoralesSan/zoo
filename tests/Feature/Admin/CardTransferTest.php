<?php

namespace Tests\Feature\Admin;

use App\Enums\CardTransferStatus;
use App\Enums\ConservationStatus;
use App\Enums\Diet;
use App\Enums\Habitat;
use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\CardTransfer;
use App\Models\Credit;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\SpeciesTag;
use App\Models\User;
use App\Models\ZooZone;
use App\Services\CardTransfer\CardPackage;
use App\Services\CardTransfer\CardTransferSupport;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;
use ZipArchive;

class CardTransferTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const WEBP = 'tests/Fixtures/images/pixel.webp';

    private const GLB = 'tests/Fixtures/glb/valid.glb';

    protected User $admin;

    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Falta la extensión zip.');
        }

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(LicenseSeeder::class);

        foreach (['cards.view', 'cards.edit', 'assets.export', 'assets.import'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'assets.export', 'assets.import']);

        $category = SpeciesCategory::create(['name' => 'Felinos', 'is_active' => true]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Jaguar',
            'scientific_name' => 'Panthera onca',
            'slug' => 'jaguar',
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------------
    // Ayudas
    // ---------------------------------------------------------------------

    private function createCard(): Card
    {
        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Jaguar del Norte',
            'rarity' => 'rara',
            'astucia' => 70,
            'rapidez' => 60,
            'fuerza' => 80,
            'is_active' => true,
            'messages_source' => 'card',
            'win_message' => '¡Lo lograste!',
        ]);

        Storage::disk('public')->put("cards/{$card->id}/miniatura.webp", file_get_contents(base_path(self::WEBP)));
        Storage::disk('public')->put("cards/{$card->id}/models/jaguar.glb", file_get_contents(base_path(self::GLB)));
        Storage::disk('local')->put("cards/{$card->id}/models/originals/jaguar.glb", file_get_contents(base_path(self::GLB)));

        $card->update([
            'thumbnail_image' => "cards/{$card->id}/miniatura.webp",
            'model_file' => "cards/{$card->id}/models/jaguar.glb",
            'model_original_file' => "cards/{$card->id}/models/originals/jaguar.glb",
        ]);

        $question = $card->quizQuestions()->create(['question' => '¿Dónde vive el jaguar?', 'is_active' => true]);
        $question->answers()->createMany([
            ['answer' => 'En la selva', 'is_correct' => true, 'sort_order' => 0],
            ['answer' => 'En el desierto', 'is_correct' => false, 'sort_order' => 1],
        ]);

        $card->qrCodes()->create(['label' => 'Entrada', 'is_active' => true]);

        $card->credits()->create([
            'asset' => 'thumbnail',
            'role' => 'origin',
            'license_id' => License::where('slug', 'cc0-1.0')->value('id'),
            'title' => 'Foto de jaguar',
            'author_name' => 'Ana',
            'source_name' => 'own',
        ]);

        return $card->fresh();
    }

    /**
     * Exporta por el panel (la cola es síncrona en pruebas) y devuelve la ruta del zip.
     */
    private function export(bool $tokens = false, bool $locations = false): string
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.assets.transfers.export'), [
                'include_qr_tokens' => $tokens,
                'include_locations' => $locations,
            ])
            ->assertCreated();

        $transfer = CardTransfer::findOrFail($response->json('data.id'));

        $this->assertSame(CardTransferStatus::Completed, $transfer->status, (string) $transfer->error);

        // Copia fuera del disco falso: el disco se comparte con la importación.
        $copy = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
        copy((string) $transfer->absolutePath(), $copy);

        return $copy;
    }

    private function upload(string $zip): CardTransfer
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.assets.transfers.import'), [
                'package' => new UploadedFile($zip, 'paquete.zip', 'application/zip', null, true),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        return CardTransfer::findOrFail($response->json('data.id'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function confirm(CardTransfer $transfer, array $options = []): CardTransfer
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.assets.transfers.confirm', $transfer), [
                'mode' => 'skip',
                ...$options,
            ])
            ->assertOk();

        return $transfer->fresh();
    }

    /**
     * Rehace el paquete como uno de la versión 1 (sin especies), como los
     * que se exportaron antes de que viajaran las especies.
     */
    private function asVersionOne(string $path): string
    {
        $source = new ZipArchive;
        $source->open($path);
        $manifest = json_decode($source->getFromName(CardPackage::MANIFEST), true);

        $copy = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
        $target = new ZipArchive;
        $target->open($copy, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (array_keys($manifest['files']) as $name) {
            if (str_starts_with($name, 'species/')) {
                unset($manifest['files'][$name]);

                continue;
            }

            $target->addFromString($name, $source->getFromName($name));
        }

        unset($manifest['include_locations'], $manifest['counts']['species'], $manifest['counts']['species_images']);
        $manifest['version'] = 1;
        $target->addFromString(CardPackage::MANIFEST, json_encode($manifest));
        $target->close();
        $source->close();

        return $copy;
    }

    private function deleteSpecies(Species $species): void
    {
        // Una por una, para que también se borren sus créditos.
        $species->images->each->delete();
        $species->locations()->delete();
        $species->tags()->detach();
        $species->delete();
    }

    /**
     * Imágenes, etiqueta y ubicación de la especie del setUp.
     */
    private function dressSpecies(): ZooZone
    {
        $species = $this->species;

        $species->update([
            'habitats' => [Habitat::TropicalRainforest, Habitat::Wetland],
            'diet' => Diet::Carnivore,
            'conservation_status' => ConservationStatus::NearThreatened,
        ]);

        foreach (['main' => 'principal.webp', 'gallery' => 'foto.webp'] as $type => $file) {
            Storage::disk('public')->put("species/{$species->id}/{$file}", file_get_contents(base_path(self::WEBP)));
            $species->images()->create([
                'type' => $type,
                'path' => "species/{$species->id}/{$file}",
                'original_bytes' => 9000,
                'alt_text' => "Jaguar {$type}",
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $species->tags()->attach(SpeciesTag::create(['name' => 'Nocturno', 'slug' => 'nocturno', 'is_active' => true]));

        $zone = ZooZone::create([
            'name' => 'Selva Maya',
            'is_active' => true,
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-99.2, 19.4], [-99.1, 19.4], [-99.1, 19.5], [-99.2, 19.4]]]],
        ]);

        $species->locations()->create([
            'zone_id' => $zone->id,
            'name' => 'Recinto',
            'latitude' => 19.45,
            'longitude' => -99.15,
            'is_active' => true,
        ]);

        return $zone;
    }

    private function deleteCard(Card $card): void
    {
        $card->credits()->delete();
        $card->qrCodes()->delete();
        $card->quizQuestions->each(fn ($question) => $question->answers()->delete());
        $card->quizQuestions()->delete();
        $card->delete();
    }

    // ---------------------------------------------------------------------
    // Pruebas
    // ---------------------------------------------------------------------

    public function test_new_cards_get_a_uuid(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->createCard()->uuid);
    }

    public function test_export_builds_a_package_with_data_and_files(): void
    {
        $card = $this->createCard();
        $zip = new ZipArchive;
        $zip->open($this->export());

        $manifest = json_decode($zip->getFromName(CardPackage::MANIFEST), true);
        $data = json_decode($zip->getFromName(CardPackage::cardJson($card->uuid)), true);

        $this->assertSame(CardPackage::VERSION, $manifest['version']);
        $this->assertSame(1, $manifest['counts']['cards']);
        $this->assertSame('jaguar', $data['species']['slug']);
        $this->assertSame('thumbnail_image.webp', $data['files']['thumbnail_image']);
        $this->assertSame('model_original_file.glb', $data['files']['model_original_file']);
        $this->assertNull($data['qr_codes'][0]['token']);
        $this->assertSame('cc0-1.0', $data['credits'][0]['license_slug']);
        $this->assertNotFalse($zip->getFromName("cards/{$card->uuid}/model_file.glb"));
        $this->assertNotFalse($zip->getFromName(CardPackage::RARITY_SETTINGS));

        $zip->close();
    }

    public function test_export_can_be_downloaded(): void
    {
        $this->createCard();
        $this->export();

        $transfer = CardTransfer::latest('id')->first();

        $this->actingAs($this->admin)
            ->get(route('admin.assets.transfers.download', $transfer))
            ->assertOk()
            ->assertDownload($transfer->original_name);
    }

    public function test_round_trip_recreates_a_deleted_card(): void
    {
        $card = $this->createCard();
        $uuid = $card->uuid;
        $oldToken = $card->qrCodes->first()->token;
        $zip = $this->export();

        $this->deleteCard($card);

        $transfer = $this->upload($zip);

        $this->assertSame(CardTransferStatus::AwaitingConfirmation, $transfer->status, (string) $transfer->error);
        $this->assertSame(1, $transfer->summary['preview']['counts']['new']);

        $transfer = $this->confirm($transfer);

        $this->assertSame(CardTransferStatus::Completed, $transfer->status, (string) $transfer->error);
        $this->assertSame(1, $transfer->summary['result']['created']);

        $imported = Card::where('uuid', $uuid)->with(['quizQuestions.answers', 'qrCodes', 'credits.license'])->firstOrFail();

        $this->assertSame('Jaguar del Norte', $imported->name);
        $this->assertSame($this->species->id, $imported->species_id);
        $this->assertSame('¡Lo lograste!', $imported->win_message);
        Storage::disk('public')->assertExists($imported->thumbnail_image);
        Storage::disk('public')->assertExists($imported->model_file);
        Storage::disk('local')->assertExists($imported->model_original_file);
        $this->assertStringStartsWith("cards/{$imported->id}/", $imported->thumbnail_image);
        $this->assertCount(2, $imported->quizQuestions->first()->answers);
        $this->assertSame('cc0-1.0', $imported->credits->first()->license->slug);

        // Sin tokens en el paquete: QR nuevo, el impreso ya no sirve.
        $this->assertCount(1, $imported->qrCodes);
        $this->assertNotSame($oldToken, $imported->qrCodes->first()->token);
    }

    public function test_qr_tokens_are_kept_when_included(): void
    {
        $card = $this->createCard();
        $token = $card->qrCodes->first()->token;
        $zip = $this->export(tokens: true);

        $this->deleteCard($card);
        $this->confirm($this->upload($zip));

        $this->assertTrue(CardQrCode::where('token', $token)->exists());
    }

    public function test_skip_mode_leaves_existing_cards_untouched(): void
    {
        $card = $this->createCard();
        $zip = $this->export();

        $card->update(['name' => 'Cambiado aquí']);

        $transfer = $this->confirm($this->upload($zip));

        $this->assertSame(1, $transfer->summary['preview']['counts']['existing']);
        $this->assertCount(1, $transfer->summary['result']['skipped']);
        $this->assertSame('Cambiado aquí', $card->fresh()->name);
    }

    public function test_overwrite_mode_updates_cards_and_deactivates_removed_questions(): void
    {
        $card = $this->createCard();
        $oldThumbnail = $card->thumbnail_image;
        $zip = $this->export();

        $card->update(['name' => 'Cambiado aquí']);
        $extra = $card->quizQuestions()->create(['question' => 'Pregunta local', 'is_active' => true]);
        $extra->answers()->create(['answer' => 'Sí', 'is_correct' => true]);
        $original = $card->quizQuestions()->where('question', '¿Dónde vive el jaguar?')->first();
        $answerIds = $original->answers()->pluck('id')->all();

        $transfer = $this->confirm($this->upload($zip), ['mode' => 'overwrite']);

        $card->refresh();

        $this->assertSame(1, $transfer->summary['result']['updated']);
        $this->assertSame('Jaguar del Norte', $card->name);
        $this->assertFalse($extra->fresh()->is_active);
        // Respuestas iguales: se conservan sus id (un duelo en curso las usa).
        $this->assertSame($answerIds, $original->answers()->pluck('id')->all());
        // El archivo anterior se reemplaza y se borra.
        $this->assertNotSame($oldThumbnail, $card->thumbnail_image);
        Storage::disk('public')->assertMissing($oldThumbnail);
        Storage::disk('public')->assertExists($card->thumbnail_image);
        // Sin tokens en el paquete, la tarjeta existente conserva su QR.
        $this->assertCount(1, $card->qrCodes);
    }

    public function test_species_is_matched_by_scientific_name_or_chosen_by_the_user(): void
    {
        $card = $this->createCard();
        $zip = $this->export();
        $this->deleteCard($card);

        // Otro slug, mismo nombre científico (sin distinguir mayúsculas).
        $this->species->update(['slug' => 'jaguar-mx', 'scientific_name' => 'PANTHERA  onca']);

        $transfer = $this->upload($zip);

        $this->assertSame('scientific_name', $transfer->summary['preview']['cards'][0]['species']['match']);
        $this->assertSame([], $transfer->summary['preview']['missing_species']);

        // Paquete viejo (sin especies) y sin coincidencias: la persona elige la especie.
        $zip = $this->asVersionOne($zip);
        $this->species->update(['scientific_name' => 'Otra cosa']);
        $other = Species::create([
            'species_category_id' => $this->species->species_category_id,
            'common_name' => 'Ocelote',
            'scientific_name' => 'Leopardus pardalis',
            'slug' => 'ocelote',
            'is_active' => true,
        ]);

        $transfer = $this->upload($zip);

        $this->assertSame('jaguar', $transfer->summary['preview']['missing_species'][0]['key']);

        $this->confirm($transfer, ['species_map' => [['key' => 'jaguar', 'species_id' => $other->id]]]);

        $this->assertSame($other->id, Card::where('name', 'Jaguar del Norte')->value('species_id'));
    }

    public function test_cards_with_missing_species_are_skipped(): void
    {
        $card = $this->createCard();
        $zip = $this->asVersionOne($this->export());
        $this->deleteCard($card);
        $this->species->update(['slug' => 'otro', 'scientific_name' => 'Otra cosa']);

        $transfer = $this->confirm($this->upload($zip));

        $this->assertSame(0, $transfer->summary['result']['created']);
        $this->assertCount(1, $transfer->summary['result']['skipped']);
    }

    public function test_package_with_unexpected_paths_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString(CardPackage::MANIFEST, json_encode(['format' => CardPackage::FORMAT, 'version' => 1, 'files' => []]));
        $zip->addFromString('../../evil.php', '<?php');
        $zip->close();

        $transfer = $this->upload($path);

        $this->assertSame(CardTransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('no permitido', (string) $transfer->error);
    }

    public function test_tampered_package_is_rejected(): void
    {
        $card = $this->createCard();
        $path = $this->export();

        $zip = new ZipArchive;
        $zip->open($path);
        $entry = CardPackage::cardJson($card->uuid);
        $json = $zip->getFromName($entry);
        // Mismo tamaño, contenido distinto.
        $zip->addFromString($entry, str_replace('Jaguar del Norte', 'Jaguar del Sur!!', $json));
        $zip->close();

        $transfer = $this->upload($path);

        $this->assertSame(CardTransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('dañado', (string) $transfer->error);
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $user = User::factory()->create();
        // Los permisos viejos de tarjetas ya no bastan.
        Permission::findOrCreate('cards.export');
        $user->givePermissionTo(['cards.view', 'cards.export']);

        $this->actingAs($user)->getJson(route('admin.assets.transfers.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.assets.transfers.export'))->assertForbidden();
    }

    public function test_actions_are_disabled_without_the_zip_extension(): void
    {
        $this->mock(CardTransferSupport::class, function ($mock) {
            $mock->shouldReceive('available')->andReturnFalse();
            $mock->shouldReceive('maxUploadBytes')->andReturn(0);
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.assets.transfers.index'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.unavailable_message', CardTransferSupport::UNAVAILABLE_MESSAGE);

        $this->actingAs($this->admin)
            ->postJson(route('admin.assets.transfers.export'))
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertSame(0, CardTransfer::count());
    }

    // ---------------------------------------------------------------------
    // Especies (versión 2)
    // ---------------------------------------------------------------------

    public function test_export_includes_species_without_locations_by_default(): void
    {
        $this->dressSpecies();
        $zip = new ZipArchive;
        $zip->open($this->export());

        $manifest = json_decode($zip->getFromName(CardPackage::MANIFEST), true);
        $data = json_decode($zip->getFromName(CardPackage::speciesJson('jaguar')), true);

        $this->assertFalse($manifest['include_locations']);
        $this->assertSame(1, $manifest['counts']['species']);
        $this->assertSame(2, $manifest['counts']['species_images']);
        $this->assertSame(['tropical_rainforest', 'wetland'], $data['habitats']);
        $this->assertSame('carnivore', $data['diet']);
        $this->assertSame('NT', $data['conservation_status']);
        $this->assertSame('Felinos', $data['category']['name']);
        $this->assertSame([['name' => 'Nocturno', 'slug' => 'nocturno']], $data['tags']);
        $this->assertSame('main.webp', $data['images']['main']['file']);
        $this->assertSame('gallery/01.webp', $data['images']['gallery'][0]['file']);
        $this->assertSame(9000, $data['images']['main']['original_bytes']);
        $this->assertArrayNotHasKey('locations', $data);
        $this->assertNotFalse($zip->getFromName('species/jaguar/gallery/01.webp'));

        $zip->close();
    }

    public function test_locations_are_exported_only_when_requested(): void
    {
        $this->dressSpecies();
        $zip = new ZipArchive;
        $zip->open($this->export(locations: true));

        $data = json_decode($zip->getFromName(CardPackage::speciesJson('jaguar')), true);

        $this->assertSame('Selva Maya', $data['locations'][0]['zone']);
        $this->assertEquals(19.45, $data['locations'][0]['latitude']);

        $zip->close();
    }

    public function test_import_creates_missing_species_with_the_chosen_references(): void
    {
        $this->dressSpecies();
        $card = $this->createCard();
        $uuid = $card->uuid;
        $zip = $this->export(locations: true);

        $this->deleteCard($card);
        $this->deleteSpecies($this->species);
        SpeciesCategory::query()->delete();
        SpeciesTag::query()->delete();
        $zone = ZooZone::firstOrFail();
        $zone->update(['name' => 'Zona renombrada']);

        $transfer = $this->upload($zip);
        $preview = $transfer->summary['preview'];

        $this->assertSame(1, $preview['species']['counts']['new']);
        $this->assertSame('package', $preview['cards'][0]['species']['match']);
        $this->assertSame([], $preview['missing_species']);
        $this->assertSame('felinos', $preview['species']['missing_categories'][0]['key']);
        $this->assertSame('nocturno', $preview['species']['missing_tags'][0]['key']);
        $this->assertSame('selva maya', $preview['species']['missing_zones'][0]['key']);

        $transfer = $this->confirm($transfer, [
            'categories' => [['key' => 'felinos', 'action' => 'create']],
            'tags' => [['key' => 'nocturno', 'action' => 'skip']],
            'zones' => [['key' => 'selva maya', 'zone_id' => $zone->id]],
        ]);

        $this->assertSame(CardTransferStatus::Completed, $transfer->status, (string) $transfer->error);
        $this->assertSame(1, $transfer->summary['result']['species']['created']);
        $this->assertSame(['Felinos'], $transfer->summary['result']['categories_created']);

        $species = Species::where('slug', 'jaguar')->with(['category', 'tags', 'images', 'locations'])->firstOrFail();

        $this->assertSame('Felinos', $species->category->name);
        $this->assertSame(Diet::Carnivore, $species->diet);
        $this->assertCount(0, $species->tags);
        $this->assertCount(2, $species->images);
        $this->assertSame(9000, $species->images->firstWhere('type', 'main')->original_bytes);
        Storage::disk('public')->assertExists($species->images->first()->path);
        $this->assertStringStartsWith("species/{$species->id}/", $species->images->first()->path);
        $this->assertSame($zone->id, $species->locations->first()->zone_id);
        // La tarjeta se asigna sola a la especie recién creada.
        $this->assertSame($species->id, Card::where('uuid', $uuid)->value('species_id'));
    }

    public function test_category_can_map_to_an_existing_one_and_unknown_zones_skip_the_location(): void
    {
        $this->dressSpecies();
        $zip = $this->export(locations: true);

        $this->deleteSpecies($this->species);
        SpeciesCategory::query()->update(['name' => 'Mamíferos']);
        $existing = SpeciesCategory::firstOrFail();
        SpeciesTag::query()->delete();
        ZooZone::query()->delete();

        $transfer = $this->confirm($this->upload($zip), [
            'categories' => [['key' => 'felinos', 'action' => 'existing', 'id' => $existing->id]],
        ]);

        $species = Species::where('slug', 'jaguar')->firstOrFail();

        $this->assertSame($existing->id, $species->species_category_id);
        // Sin decisión, la etiqueta que falta se crea.
        $this->assertSame(['Nocturno'], $transfer->summary['result']['tags_created']);
        $this->assertSame(1, $transfer->summary['result']['locations_skipped']);
        $this->assertCount(0, $species->locations);
    }

    public function test_overwrite_replaces_species_images_and_keeps_locations_not_in_the_package(): void
    {
        $this->dressSpecies();
        $oldMain = $this->species->images()->where('type', 'main')->value('path');
        $zip = $this->export();

        $this->species->update(['common_name' => 'Cambiado aquí']);

        $skipped = $this->confirm($this->upload($zip));
        $this->assertSame('Cambiado aquí', $this->species->fresh()->common_name);
        $this->assertCount(1, $skipped->summary['result']['species']['skipped']);

        $transfer = $this->confirm($this->upload($zip), ['mode' => 'overwrite']);
        $species = $this->species->fresh(['images', 'locations']);

        $this->assertSame(1, $transfer->summary['result']['species']['updated']);
        $this->assertSame('Jaguar', $species->common_name);
        $this->assertCount(2, $species->images);
        $this->assertNotContains($oldMain, $species->images->pluck('path'));
        Storage::disk('public')->assertMissing($oldMain);
        // El paquete no trae ubicaciones: la de este servidor se conserva.
        $this->assertCount(1, $species->locations);
    }

    public function test_species_image_credits_travel_with_their_images(): void
    {
        $this->dressSpecies();
        $main = $this->species->images()->where('type', 'main')->firstOrFail();
        $gallery = $this->species->images()->where('type', 'gallery')->firstOrFail();

        $main->credits()->create([
            'asset' => 'image',
            'role' => 'origin',
            'license_id' => License::where('slug', 'cc-by-4.0')->value('id'),
            'title' => 'Jaguar',
            'author_name' => 'Jane Doe',
            'source_name' => 'wikimedia_commons',
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Jaguar.jpg',
        ]);
        $gallery->credits()->create([
            'asset' => 'image',
            'role' => 'origin',
            'license_id' => License::where('slug', 'proprietary')->value('id'),
            'source_name' => 'own',
        ]);

        $zip = $this->export();

        $this->deleteSpecies($this->species);
        $this->assertSame(0, Credit::count());

        $transfer = $this->upload($zip);
        $this->assertSame(CardPackage::VERSION, $transfer->summary['preview']['manifest']['version']);
        $this->assertSame([], $transfer->summary['preview']['licenses']['missing']);

        $this->confirm($transfer);

        $species = Species::where('slug', 'jaguar')->with('images.credits.license')->firstOrFail();
        $newMain = $species->images->firstWhere('type', 'main');
        $newGallery = $species->images->firstWhere('type', 'gallery');

        $this->assertCount(1, $newMain->credits);
        $this->assertSame('cc-by-4.0', $newMain->credits->first()->license->slug);
        $this->assertSame('Jane Doe', $newMain->credits->first()->author_name);
        $this->assertSame('image', $newMain->credits->first()->asset->value);
        $this->assertSame('proprietary', $newGallery->credits->first()->license->slug);
    }

    public function test_species_image_focal_points_travel_with_their_images(): void
    {
        $this->dressSpecies();
        $this->species->images()->where('type', 'main')->update(['focal_x' => 0.25, 'focal_y' => 0.75]);

        $zip = $this->export();
        $this->deleteSpecies($this->species);
        $this->confirm($this->upload($zip));

        $species = Species::where('slug', 'jaguar')->with('images')->firstOrFail();

        $this->assertSame(['x' => 0.25, 'y' => 0.75], $species->images->firstWhere('type', 'main')?->focal());
        $this->assertNull($species->images->firstWhere('type', 'gallery')?->focal());
    }

    public function test_overwriting_a_species_replaces_its_image_credits(): void
    {
        $this->dressSpecies();
        $main = $this->species->images()->where('type', 'main')->firstOrFail();
        $main->credits()->create([
            'asset' => 'image',
            'role' => 'origin',
            'license_id' => License::where('slug', 'proprietary')->value('id'),
            'source_name' => 'own',
        ]);

        $zip = $this->export();
        $this->confirm($this->upload($zip), ['mode' => 'overwrite']);

        // Las imágenes se recrean: un solo crédito, en la imagen nueva.
        $this->assertSame(1, Credit::count());
        $this->assertNotSame($main->id, Credit::first()->creditable_id);
        $this->assertNull(SpeciesImage::find($main->id));
    }

    public function test_species_image_credits_cannot_claim_another_asset(): void
    {
        $this->dressSpecies();
        $this->species->images()->where('type', 'main')->firstOrFail()->credits()->create([
            'asset' => 'image',
            'role' => 'origin',
            'license_id' => License::where('slug', 'proprietary')->value('id'),
            'source_name' => 'own',
        ]);

        $zip = $this->rewriteSpeciesJson($this->export(), function (array $data) {
            $data['images']['main']['credits'][0]['asset'] = 'artwork';

            return $data;
        });

        $preview = $this->upload($zip)->summary['preview'];

        $this->assertSame(1, $preview['species']['counts']['invalid']);
    }

    public function test_version_two_packages_without_credits_still_import(): void
    {
        $this->dressSpecies();
        $zip = $this->rewriteSpeciesJson($this->export(), function (array $data) {
            unset($data['images']['main']['credits'], $data['images']['gallery'][0]['credits']);

            return $data;
        }, version: 2);

        $this->deleteSpecies($this->species);

        $transfer = $this->upload($zip);
        $this->assertSame(2, $transfer->summary['preview']['manifest']['version']);

        $transfer = $this->confirm($transfer);

        $this->assertSame(1, $transfer->summary['result']['species']['created']);
        $this->assertSame(0, Credit::count());
    }

    /**
     * Cambia el species.json del jaguar (y su huella en el manifiesto).
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function rewriteSpeciesJson(string $path, callable $change, ?int $version = null): string
    {
        $source = new ZipArchive;
        $source->open($path);
        $manifest = json_decode($source->getFromName(CardPackage::MANIFEST), true);
        $entry = CardPackage::speciesJson('jaguar');

        $copy = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
        $target = new ZipArchive;
        $target->open($copy, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (array_keys($manifest['files']) as $name) {
            $contents = $source->getFromName($name);

            if ($name === $entry) {
                $contents = CardPackage::encode($change(json_decode($contents, true)));
                $manifest['files'][$name] = ['sha256' => hash('sha256', $contents), 'bytes' => strlen($contents)];
            }

            $target->addFromString($name, $contents);
        }

        if ($version !== null) {
            $manifest['version'] = $version;
        }

        $target->addFromString(CardPackage::MANIFEST, json_encode($manifest));
        $target->close();
        $source->close();

        return $copy;
    }

    public function test_version_one_packages_still_import(): void
    {
        $card = $this->createCard();
        $zip = $this->asVersionOne($this->export());
        $this->deleteCard($card);

        $transfer = $this->upload($zip);

        $this->assertSame(1, $transfer->summary['preview']['manifest']['version']);
        $this->assertSame(0, $transfer->summary['preview']['species']['counts']['total']);

        $transfer = $this->confirm($transfer);

        $this->assertSame(1, $transfer->summary['result']['created']);
    }

    public function test_species_with_invalid_data_is_skipped(): void
    {
        $this->dressSpecies();
        $path = $this->export();

        // Dieta fuera del catálogo; se recalcula el manifest para que el paquete sea coherente.
        $zip = new ZipArchive;
        $zip->open($path);
        $entry = CardPackage::speciesJson('jaguar');
        $data = json_decode($zip->getFromName($entry), true);
        $data['diet'] = 'pizza';
        $json = CardPackage::encode($data);
        $zip->addFromString($entry, $json);
        $manifest = json_decode($zip->getFromName(CardPackage::MANIFEST), true);
        $manifest['files'][$entry] = ['sha256' => hash('sha256', $json), 'bytes' => strlen($json)];
        $zip->addFromString(CardPackage::MANIFEST, json_encode($manifest));
        $zip->close();

        $transfer = $this->upload($path);

        $this->assertSame(1, $transfer->summary['preview']['species']['counts']['invalid']);

        $transfer = $this->confirm($transfer, ['mode' => 'overwrite']);

        $this->assertSame(0, $transfer->summary['result']['species']['updated']);
        $this->assertStringContainsString('Datos inválidos', $transfer->summary['result']['species']['skipped'][0]['reason']);
    }

    public function test_migration_moves_card_transfer_permissions_to_assets(): void
    {
        $migration = require database_path('migrations/2026_11_21_110000_rename_card_transfer_permissions_to_assets.php');
        // Estado anterior: solo existen los permisos de tarjetas.
        $migration->down();

        $role = Role::findOrCreate('admin');
        $role->givePermissionTo('cards.export');
        $user = User::factory()->create();
        $user->givePermissionTo('cards.import');

        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('assets.export'));
        $this->assertTrue($user->fresh()->hasPermissionTo('assets.import'));
        $this->assertFalse(Permission::where('name', 'cards.export')->exists());
    }
}
