<?php

namespace Tests\Feature\Admin;

use App\Enums\CardTransferStatus;
use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\CardTransfer;
use App\Models\License;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use App\Services\CardTransfer\CardPackage;
use App\Services\CardTransfer\CardTransferSupport;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
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

        foreach (['view', 'edit', 'export', 'import'] as $action) {
            Permission::findOrCreate('cards.'.$action);
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.export', 'cards.import']);

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
    private function export(bool $tokens = false): string
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.cards.transfers.export'), ['include_qr_tokens' => $tokens])
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
            ->post(route('admin.cards.transfers.import'), [
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
            ->postJson(route('admin.cards.transfers.confirm', $transfer), [
                'mode' => 'skip',
                ...$options,
            ])
            ->assertOk();

        return $transfer->fresh();
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
            ->get(route('admin.cards.transfers.download', $transfer))
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

        // Sin coincidencias: la persona elige la especie.
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
        $zip = $this->export();
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
        $user->givePermissionTo('cards.view');

        $this->actingAs($user)->getJson(route('admin.cards.transfers.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.cards.transfers.export'))->assertForbidden();
    }

    public function test_actions_are_disabled_without_the_zip_extension(): void
    {
        $this->mock(CardTransferSupport::class, function ($mock) {
            $mock->shouldReceive('available')->andReturnFalse();
            $mock->shouldReceive('maxUploadBytes')->andReturn(0);
        });

        $this->actingAs($this->admin)
            ->getJson(route('admin.cards.transfers.index'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.unavailable_message', CardTransferSupport::UNAVAILABLE_MESSAGE);

        $this->actingAs($this->admin)
            ->postJson(route('admin.cards.transfers.export'))
            ->assertStatus(503)
            ->assertJsonPath('success', false);

        $this->assertSame(0, CardTransfer::count());
    }
}
