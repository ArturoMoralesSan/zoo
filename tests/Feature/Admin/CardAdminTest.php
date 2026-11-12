<?php

namespace Tests\Feature\Admin;

use App\Models\Card;
use App\Models\CardRaritySetting;
use App\Models\DuelMessageSetting;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use Database\Seeders\LicenseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class CardAdminTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected SpeciesCategory $category;

    protected Species $species;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permissions exist
        Permission::findOrCreate('cards.view');
        Permission::findOrCreate('cards.create');
        Permission::findOrCreate('cards.edit');
        Permission::findOrCreate('cards.delete');

        $this->seed(LicenseSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['cards.view', 'cards.create', 'cards.edit', 'cards.delete']);

        $this->category = SpeciesCategory::create([
            'name' => 'Aves ' . uniqid(),
            'description' => 'Categoría de aves',
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $this->category->id,
            'common_name' => 'Águila Real ' . uniqid(),
            'scientific_name' => 'Aquila chrysaetos ' . uniqid(),
            'slug' => 'aguila-real-' . uniqid(),
            'description' => 'Especie de prueba',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_store_card_with_stats_and_duel_settings(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cards.store'), [
            ...$this->designPayload(),
            'species_id' => $this->species->id,
            'name' => 'Águila Legendaria',
            'rarity' => 'epica',
            'astucia' => 85,
            'rapidez' => 95,
            'fuerza' => 90,
            'questions_per_duel' => 5,
            'duel_settings_source' => 'card',
            'retries' => 4,
            'cooldown_minutes' => 90,
            'is_active' => true,
            'sort_order' => 1,
            // Las preguntas ya no se gestionan desde el formulario de la tarjeta
            'questions' => [
                ['question' => 'Ignorada', 'answers' => []],
            ],
        ]);

        $response->assertRedirect(route('admin.cards.index'));

        $this->assertDatabaseHas('cards', [
            'name' => 'Águila Legendaria',
            'astucia' => 85,
            'rapidez' => 95,
            'fuerza' => 90,
            'questions_per_duel' => 5,
            'duel_settings_source' => 'card',
            'retries' => 4,
            'cooldown_minutes' => 90,
        ]);

        $card = Card::where('name', 'Águila Legendaria')->first();
        $this->assertCount(0, $card->quizQuestions);
    }

    public function test_admin_can_update_card_to_use_rarity_settings_without_touching_questions(): void
    {
        $card = Card::create([
            'species_id' => $this->species->id,
            'name' => 'Águila Juvenil',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'questions_per_duel' => 3,
            'duel_settings_source' => 'card',
            'retries' => 3,
            'is_active' => true,
        ]);

        $q1 = $card->quizQuestions()->create([
            'question' => 'Pregunta antigua',
            'is_active' => true,
        ]);
        $q1->answers()->createMany([
            ['answer' => 'Opcion 1', 'is_correct' => true, 'sort_order' => 0],
            ['answer' => 'Opcion 2', 'is_correct' => false, 'sort_order' => 1],
            ['answer' => 'Opcion 3', 'is_correct' => false, 'sort_order' => 2],
        ]);
        $answerIds = $q1->answers()->pluck('id')->all();

        CardRaritySetting::updateOrCreate(
            ['rarity' => 'rara'],
            ['questions_per_duel' => 5, 'retries' => 8]
        );

        $response = $this->actingAs($this->admin)->put(route('admin.cards.update', $card), [
            ...$this->designPayload(),
            'species_id' => $this->species->id,
            'name' => 'Águila Adulta',
            'rarity' => 'rara',
            'astucia' => 70,
            'rapidez' => 80,
            'fuerza' => 85,
            'duel_settings_source' => 'rarity',
            'is_active' => true,
            'sort_order' => 0,
            'questions' => [],
        ]);

        $response->assertRedirect(route('admin.cards.index'));

        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'name' => 'Águila Adulta',
            'astucia' => 70,
            'rapidez' => 80,
            'fuerza' => 85,
            'duel_settings_source' => 'rarity',
            'questions_per_duel' => null,
            'retries' => null,
            'cooldown_minutes' => null,
        ]);

        // Los valores efectivos provienen de la rareza
        $card->refresh();
        $this->assertEquals(5, $card->effective_questions_per_duel);
        $this->assertEquals(8, $card->effective_retries);

        // Las preguntas existentes no se modifican al guardar la tarjeta
        $this->assertCount(1, $card->quizQuestions);
        $this->assertEquals('Pregunta antigua', $card->quizQuestions[0]->question);
        $this->assertEqualsCanonicalizing($answerIds, $card->quizQuestions[0]->answers->pluck('id')->all());
    }

    public function test_custom_settings_are_required_when_source_is_card(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.cards.store'), [
            'species_id' => $this->species->id,
            'name' => 'Águila Sin Configuración',
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'card',
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors(['questions_per_duel', 'retries', 'cooldown_minutes']);
    }

    public function test_admin_can_update_rarity_settings(): void
    {
        $response = $this->actingAs($this->admin)
            ->from(route('admin.cards.index'))
            ->put(route('admin.cards.rarity-settings.update'), [
                'settings' => [
                    ['rarity' => 'comun', 'questions_per_duel' => 2, 'retries' => 5, 'cooldown_minutes' => 45],
                    ['rarity' => 'epica', 'questions_per_duel' => 10, 'retries' => 1, 'cooldown_minutes' => 120],
                ],
            ]);

        $response->assertRedirect(route('admin.cards.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('card_rarity_settings', [
            'rarity' => 'comun',
            'questions_per_duel' => 2,
            'retries' => 5,
            'cooldown_minutes' => 45,
        ]);

        $this->assertDatabaseHas('card_rarity_settings', [
            'rarity' => 'epica',
            'questions_per_duel' => 10,
            'retries' => 1,
            'cooldown_minutes' => 120,
        ]);
    }

    public function test_reveal_setting_is_saved_without_touching_other_rarity_settings(): void
    {
        CardRaritySetting::updateOrCreate(['rarity' => 'epica'], [
            'questions_per_duel' => 4,
            'retries' => 2,
            'cooldown_minutes' => 90,
            'reveal_answers_on_lockout' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.rarity-settings.update'), [
                'settings' => [
                    [
                        'rarity' => 'epica',
                        'reveal_answers_on_lockout' => true,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('card_rarity_settings', [
            'rarity' => 'epica',
            'questions_per_duel' => 4,
            'retries' => 2,
            'cooldown_minutes' => 90,
            'reveal_answers_on_lockout' => true,
        ]);
    }

    public function test_default_duel_messages_can_be_updated(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.cards.duel-messages.update'), [
                'win_message' => '¡{tarjeta} es tuya!',
                'retry_message' => '   ',
                'locked_message' => null,
            ])
            ->assertSessionHasNoErrors();

        $setting = DuelMessageSetting::current();

        $this->assertSame('¡{tarjeta} es tuya!', $setting->win_message);
        // Un mensaje vacío usa el texto integrado.
        $this->assertNull($setting->retry_message);
        $this->assertSame(DuelMessageSetting::BUILT_IN['retry_message'], $setting->effective()['retry_message']);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.duel-messages.update'), [
                'win_message' => str_repeat('a', 256),
            ])
            ->assertSessionHasErrors('win_message');
    }

    public function test_rarity_settings_update_validates_input(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('admin.cards.rarity-settings.update'), [
                'settings' => [
                    ['rarity' => 'legendaria', 'questions_per_duel' => 0, 'retries' => 101, 'cooldown_minutes' => 0],
                ],
            ]);

        $response->assertSessionHasErrors([
            'settings.0.rarity',
            'settings.0.questions_per_duel',
            'settings.0.retries',
            'settings.0.cooldown_minutes',
        ]);
    }

    /**
     * PNG y GIF de 1x1 (las pruebas no dependen de la extensión GD).
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private function fakeImage(string $name, string $base64 = self::PNG): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode($base64));
    }

    private function designPayload(): array
    {
        return [
            'fade_color' => '#4f6b5a',
            'accent_color' => null,
            'title_font_family' => 'cinzel',
            'title_font_weight' => 800,
            'title_font_size' => '6xl',
            'messages_source' => 'default',
        ];
    }

    private function cardPayload(array $overrides = []): array
    {
        return array_merge([
            ...$this->designPayload(),
            'species_id' => $this->species->id,
            'name' => 'Águila Modelo ' . uniqid(),
            'rarity' => 'comun',
            'astucia' => 50,
            'rapidez' => 50,
            'fuerza' => 50,
            'duel_settings_source' => 'rarity',
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_can_upload_a_glb_model(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $payload = $this->cardPayload($this->modelPayload());

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors()
            // Con archivos, la tarjeta nueva abre su sección Créditos.
            ->assertRedirectContains('/credits')
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlashMissing('model_warnings');

        $card = Card::where('name', $payload['name'])->firstOrFail();

        $this->assertStringStartsWith('cards/'.$card->id.'/models/', $card->model_file);
        $this->assertStringEndsWith('.glb', $card->model_file);
        Storage::disk('public')->assertExists($card->model_file);

        $this->assertStringStartsWith('cards/'.$card->id.'/models/originals/', $card->model_original_file);
        Storage::disk('local')->assertExists($card->model_original_file);
        Storage::disk('public')->assertMissing($card->model_original_file);

        $this->assertSame(1.5, $card->model_target_height);
        $this->assertSame(2.0, $card->model_original_height);
        $this->assertEqualsWithDelta(1.5, $card->model_final_height, 0.0001);
        $this->assertEqualsWithDelta(0.375, $card->model_final_width, 0.0001);
        $this->assertEqualsWithDelta(0.75, $card->model_final_depth, 0.0001);
    }

    public function test_admin_can_upload_card_images(): void
    {
        $payload = $this->cardPayload([
            'thumbnail_image' => $this->fakeImage('miniatura.jpg'),
            'artwork_image' => $this->fakeImage('fondo.png'),
            'generated_image' => $this->fakeImage('card.png'),
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors()
            // Con archivos, la tarjeta nueva abre su sección Créditos.
            ->assertRedirectContains('/credits');

        $card = Card::where('name', $payload['name'])->firstOrFail();

        $paths = [];

        foreach (['thumbnail_image', 'artwork_image', 'generated_image'] as $field) {
            $this->assertNotNull($card->{$field});
            $paths[] = storage_path('app/public/' . $card->{$field});
        }

        // Al actualizar, la imagen generada reemplaza a la anterior.
        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                'is_active' => false,
                'generated_image' => $this->fakeImage('card.png'),
            ]))
            ->assertSessionHasNoErrors();

        $card->refresh();

        $this->assertFileDoesNotExist($paths[2]);
        $paths[2] = storage_path('app/public/' . $card->generated_image);

        foreach ($paths as $path) {
            $this->assertFileExists($path);
        }

        // Eliminar la miniatura desde el formulario.
        $this->actingAs($this->admin)
            ->delete(route('admin.cards.image.destroy', [$card, 'thumbnail']))
            ->assertSessionHasNoErrors();

        $this->assertNull($card->refresh()->thumbnail_image);
        $this->assertFileDoesNotExist($paths[0]);

        File::delete($paths);
        @rmdir(dirname($paths[1]));
    }

    public function test_generated_card_image_must_be_webp_or_png(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'generated_image' => $this->fakeImage('card.gif', self::GIF),
            ]))
            ->assertSessionHasErrors('generated_image');
    }

    public function test_admin_can_store_card_design(): void
    {
        $payload = $this->cardPayload([
            'fade_color' => '#F2E8D5',
            'accent_color' => 'black',
            'title_font_family' => 'montserrat-alternates',
            'title_font_weight' => 300,
            'title_font_size' => '5xl',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cards', [
            'name' => $payload['name'],
            'fade_color' => '#f2e8d5',
            'accent_color' => 'black',
            'title_font_family' => 'montserrat-alternates',
            'title_font_weight' => 300,
            'title_font_size' => '5xl',
        ]);
    }

    public function test_card_can_have_its_own_duel_messages(): void
    {
        $payload = $this->cardPayload([
            'messages_source' => 'card',
            'win_message' => '¡{tarjeta} es tuya!',
            'retry_message' => '   ',
            'locked_message' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors();

        $card = Card::where('name', $payload['name'])->firstOrFail();

        $this->assertSame('card', $card->messages_source);
        $this->assertSame('¡{tarjeta} es tuya!', $card->win_message);
        // Un mensaje vacío usa el predeterminado.
        $this->assertNull($card->retry_message);
        $this->assertNull($card->locked_message);

        // Al volver a los mensajes predeterminados, los propios se borran.
        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->cardPayload([
                'name' => $card->name,
                'messages_source' => 'default',
                'win_message' => 'Ignorado',
            ]))
            ->assertSessionHasNoErrors();

        $card->refresh();

        $this->assertSame('default', $card->messages_source);
        $this->assertNull($card->win_message);
    }

    public function test_duel_messages_source_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'messages_source' => 'otro',
                'win_message' => str_repeat('a', 256),
            ]))
            ->assertSessionHasErrors(['messages_source', 'win_message']);
    }

    public function test_card_design_is_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'fade_color' => 'verde',
                'accent_color' => 'red',
                'title_font_family' => 'comic-sans',
                'title_font_size' => '9xl',
            ]))
            ->assertSessionHasErrors([
                'fade_color',
                'accent_color',
                'title_font_family',
                'title_font_size',
            ]);

        // Signika no tiene peso 900.
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'title_font_family' => 'signika',
                'title_font_weight' => 900,
            ]))
            ->assertSessionHasErrors('title_font_weight');
    }

    public function test_gltf_models_are_rejected(): void
    {
        $payload = $this->cardPayload([
            'model_file' => UploadedFile::fake()->create('aguila.gltf', 100, 'model/gltf+json'),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasErrors('model_file');

        $this->assertDatabaseMissing('cards', ['name' => $payload['name']]);
    }

    public function test_removed_card_fields_are_ignored(): void
    {
        $payload = $this->cardPayload([
            'description' => 'Ya no existe',
            'edition' => 'Navidad',
            'model_name' => 'Modelo',
            'model_url' => 'https://example.com/modelo.glb',
            'model_format' => 'gltf',
            'model_description' => 'Ya no existe',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.index'));

        $this->assertDatabaseHas('cards', ['name' => $payload['name']]);
    }

    /*
    |--------------------------------------------------------------------------
    | Tamaño real de los modelos 3D
    |--------------------------------------------------------------------------
    */

    /**
     * Copia temporal de un GLB de tests/Fixtures/glb como archivo subido.
     */
    private function glbUpload(string $fixture, string $name = 'aguila.glb'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'glb');
        copy(base_path('tests/Fixtures/glb/'.$fixture), $path);

        return new UploadedFile($path, $name, 'model/gltf-binary', null, true);
    }

    /**
     * Original (2 m de alto) y archivo escalado a 1.5 m, como los envía el navegador.
     */
    private function modelPayload(array $overrides = []): array
    {
        return array_merge([
            // Con archivos nuevos se crea inactiva: los créditos se registran después.
            'is_active' => false,
            'model_original_file' => $this->glbUpload('input/plain.glb'),
            'model_file' => $this->glbUpload('valid.glb'),
            'model_target_height' => '1.5',
            'model_original_height' => '2',
            'model_final_height' => '1.5',
            'model_final_width' => '0.375',
            'model_final_depth' => '0.75',
        ], $overrides);
    }

    /**
     * Tarjeta con modelo ya guardado en los discos falsos.
     */
    private function cardWithModel(bool $legacy = false): Card
    {
        $card = Card::create($this->cardPayload());

        $model = 'cards/'.$card->id.'/models/actual.glb';
        Storage::disk('public')->put($model, 'escalado');

        $attributes = ['model_file' => $model];

        if (! $legacy) {
            $original = 'cards/'.$card->id.'/models/originals/original.glb';
            Storage::disk('local')->put($original, 'original');

            $attributes += [
                'model_original_file' => $original,
                'model_target_height' => 1,
                'model_original_height' => 2,
                'model_final_height' => 1,
                'model_final_width' => 0.25,
                'model_final_depth' => 0.5,
            ];
        }

        $card->update($attributes);

        return $card->fresh();
    }

    private function updatePayload(Card $card, array $overrides = []): array
    {
        return $this->cardPayload([
            'name' => $card->name,
            ...$overrides,
        ]);
    }

    public function test_card_can_be_created_without_a_model(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $payload = $this->cardPayload();

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.index'));

        $card = Card::where('name', $payload['name'])->firstOrFail();

        $this->assertNull($card->model_file);
        $this->assertNull($card->model_original_file);
        $this->assertNull($card->model_final_height);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_invalid_glb_is_rejected_without_writing_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $payload = $this->cardPayload($this->modelPayload([
            'model_file' => $this->glbUpload('bad-magic.glb'),
        ]));

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasErrors('model_file');

        $this->assertDatabaseMissing('cards', ['name' => $payload['name']]);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_file_that_does_not_match_the_height_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $payload = $this->cardPayload($this->modelPayload([
            'model_target_height' => '2',
        ]));

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasErrors(['model_file' => 'El modelo mide 1.500 m de alto, pero se pidió 2.000 m.']);

        $this->assertDatabaseMissing('cards', ['name' => $payload['name']]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_compressed_resized_file_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload($this->modelPayload([
                'model_file' => $this->glbUpload('draco.glb'),
            ])))
            ->assertSessionHasErrors('model_file');
    }

    public function test_model_height_must_be_within_range(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        foreach (['0', '0.001', '150', 'alto'] as $height) {
            $this->actingAs($this->admin)
                ->post(route('admin.cards.store'), $this->cardPayload($this->modelPayload([
                    'model_target_height' => $height,
                ])))
                ->assertSessionHasErrors('model_target_height');
        }
    }

    public function test_decimal_heights_are_accepted(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // 1.51 m está dentro de la tolerancia del 1 % del archivo de 1.5 m.
        foreach (['1.5', '1.51'] as $height) {
            $payload = $this->cardPayload($this->modelPayload([
                'model_target_height' => $height,
            ]));

            $this->actingAs($this->admin)
                ->post(route('admin.cards.store'), $payload)
                ->assertSessionHasNoErrors();

            $card = Card::where('name', $payload['name'])->firstOrFail();

            $this->assertSame((float) $height, $card->model_target_height);
        }
    }

    public function test_model_fields_must_be_sent_together(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        // Original sin archivo escalado ni alto.
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'model_original_file' => $this->glbUpload('input/plain.glb'),
            ]))
            ->assertSessionHasErrors(['model_file', 'model_target_height']);

        // Archivo escalado sin original al crear.
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload(
                collect($this->modelPayload())->except('model_original_file')->all()
            ))
            ->assertSessionHasErrors('model_original_file');

        // Alto sin archivo escalado al editar.
        $card = $this->cardWithModel();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload($card, [
                'model_target_height' => '1.5',
            ]))
            ->assertSessionHasErrors('model_file');
    }

    public function test_size_only_edit_replaces_the_resized_file_and_keeps_the_original(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        $oldModel = $card->model_file;
        $original = $card->model_original_file;

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload(
                $card,
                collect($this->modelPayload())->except('model_original_file')->all()
            ))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.index'));

        $card->refresh();

        $this->assertNotSame($oldModel, $card->model_file);
        Storage::disk('public')->assertMissing($oldModel);
        Storage::disk('public')->assertExists($card->model_file);

        $this->assertSame($original, $card->model_original_file);
        Storage::disk('local')->assertExists($original);

        $this->assertSame(1.5, $card->model_target_height);
        $this->assertEqualsWithDelta(1.5, $card->model_final_height, 0.0001);
    }

    public function test_legacy_card_keeps_its_current_file_as_the_original(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel(legacy: true);
        $legacyModel = $card->model_file;

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload(
                $card,
                collect($this->modelPayload())->except('model_original_file')->all()
            ))
            ->assertSessionHasNoErrors();

        $card->refresh();

        $this->assertNotNull($card->model_original_file);
        $this->assertSame('escalado', Storage::disk('local')->get($card->model_original_file));
        Storage::disk('public')->assertMissing($legacyModel);
        Storage::disk('public')->assertExists($card->model_file);
    }

    public function test_new_model_replaces_both_files_after_saving(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        $oldModel = $card->model_file;
        $oldOriginal = $card->model_original_file;

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload($card, $this->modelPayload()))
            ->assertSessionHasNoErrors();

        $card->refresh();

        Storage::disk('public')->assertMissing($oldModel);
        Storage::disk('local')->assertMissing($oldOriginal);
        Storage::disk('public')->assertExists($card->model_file);
        Storage::disk('local')->assertExists($card->model_original_file);
    }

    /**
     * El editor siempre sube la imagen generada junto con el modelo. En una
     * petición real la imagen ya se movió fuera de /tmp cuando se arma el aviso
     * del peso; las subidas falsas de las pruebas no fallan por eso, así que
     * aquí solo se cubre el resultado.
     */
    public function test_saving_a_model_with_a_new_card_image_shows_the_model_size(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload($card, $this->modelPayload([
                'generated_image' => $this->fakeImage('card.png'),
            ])))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.cards.index'));

        $this->assertStringContainsString(
            'Modelo 3D:',
            session(SessionKey::FLASH_DATA)['toast']['message'] ?? ''
        );

        // moveUploadedFile() escribe directo en storage/, fuera del disco falso.
        $image = storage_path('app/public/'.$card->fresh()->generated_image);
        $this->assertFileExists($image);
        File::delete($image);
    }

    public function test_update_without_model_fields_does_not_touch_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        $before = $card->only(['model_file', 'model_original_file', 'model_target_height', 'model_final_height']);

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload($card, ['astucia' => 80]))
            ->assertSessionHasNoErrors();

        $card->refresh();

        $this->assertSame($before, $card->only(array_keys($before)));
        Storage::disk('public')->assertExists($card->model_file);
        Storage::disk('local')->assertExists($card->model_original_file);
    }

    public function test_resized_file_requires_an_original_when_the_card_has_no_model(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = Card::create($this->cardPayload());

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload(
                $card,
                collect($this->modelPayload())->except('model_original_file')->all()
            ))
            ->assertSessionHasErrors('model_file');
    }

    public function test_failure_while_saving_leaves_no_orphan_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        Card::updating(function (Card $card) {
            if ($card->isDirty('model_file')) {
                throw new \RuntimeException('Fallo simulado');
            }
        });

        $payload = $this->cardPayload($this->modelPayload());

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertServerError();

        $this->assertDatabaseMissing('cards', ['name' => $payload['name']]);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_failure_while_updating_keeps_the_previous_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        Card::updating(function (Card $card) {
            if ($card->isDirty('model_file')) {
                throw new \RuntimeException('Fallo simulado');
            }
        });

        $this->actingAs($this->admin)
            ->put(route('admin.cards.update', $card), $this->updatePayload($card, $this->modelPayload()))
            ->assertServerError();

        $this->assertSame([$card->model_file], Storage::disk('public')->allFiles());
        $this->assertSame([$card->model_original_file], Storage::disk('local')->allFiles());
    }

    public function test_warnings_are_returned_on_save(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        config(['glb.supported_extensions' => ['KHR_materials_unlit']]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload($this->modelPayload([
                'model_file' => $this->glbUpload('unsupported-extension.glb'),
            ])));

        $response
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('model_warnings.0');

        $this->assertStringContainsString(
            'KHR_materials_clearcoat',
            session()->get(SessionKey::FLASH_DATA)['model_warnings'][0]
        );
    }

    public function test_skinned_model_is_saved_with_browser_dimensions_and_a_warning(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $payload = $this->cardPayload($this->modelPayload([
            'model_original_file' => $this->glbUpload('input/skinned.glb'),
            'model_file' => $this->glbUpload('skinned.glb'),
            'model_final_width' => '0.4',
        ]));

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('model_warnings');

        $card = Card::where('name', $payload['name'])->firstOrFail();

        $this->assertSame(0.4, $card->model_final_width);
    }

    public function test_destroy_model_removes_both_files_and_dimensions(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.model.destroy', $card))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success');

        Storage::disk('public')->assertMissing($card->model_file);
        Storage::disk('local')->assertMissing($card->model_original_file);

        $card->refresh();

        $this->assertNull($card->model_file);
        $this->assertNull($card->model_original_file);
        $this->assertNull($card->model_target_height);
        $this->assertNull($card->model_final_height);
    }

    public function test_destroy_card_removes_the_private_original(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $this->actingAs($this->admin)
            ->delete(route('admin.cards.destroy', $card))
            ->assertRedirect(route('admin.cards.index'));

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_original_model_download_requires_permission(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();

        $this->get(route('admin.cards.model.original', $card))
            ->assertRedirect(route('login'));

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('cards.view');

        $this->actingAs($viewer)
            ->get(route('admin.cards.model.original', $card))
            ->assertForbidden();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cards.model.original', $card))
            ->assertOk()
            ->assertHeader('Content-Type', 'model/gltf-binary');

        $this->assertSame('original', $response->streamedContent());
    }

    public function test_original_model_download_uses_the_current_file_for_legacy_cards(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel(legacy: true);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cards.model.original', $card))
            ->assertOk();

        $this->assertSame('escalado', $response->streamedContent());

        $card->update(['model_file' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.cards.model.original', $card))
            ->assertNotFound();
    }

    public function test_forms_receive_the_glb_config(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.cards.create'))
            ->assertInertia(fn ($page) => $page
                ->where('glbConfig.minHeight', 0.01)
                ->where('glbConfig.maxHeight', 100)
                ->has('glbConfig.supportedExtensions')
                ->where('glbConfig.textures.maxSize', 1024)
                ->where('glbConfig.textures.quality', 85));
    }

    public function test_edit_and_show_receive_the_file_sizes(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $card = $this->cardWithModel();
        Storage::disk('public')->put('cards/'.$card->id.'/miniatura.webp', 'webp');
        $card->update([
            'thumbnail_image' => 'cards/'.$card->id.'/miniatura.webp',
            'image_original_bytes' => ['thumbnail' => 5000],
        ]);

        foreach (['admin.cards.edit', 'admin.cards.show'] as $route) {
            $this->actingAs($this->admin)
                ->get(route($route, $card))
                ->assertInertia(fn ($page) => $page
                    ->where('fileSizes.model.current', strlen('escalado'))
                    ->where('fileSizes.model.original', strlen('original'))
                    ->where('fileSizes.thumbnail.current', strlen('webp'))
                    ->where('fileSizes.thumbnail.original', 5000)
                    ->where('fileSizes.generated.current', null)
                    ->where('fileSizes.generated.original', null));
        }
    }

    public function test_uploaded_images_record_their_size_before_compression(): void
    {
        $payload = $this->cardPayload([
            'is_active' => false,
            'thumbnail_image' => $this->fakeImage('miniatura.png'),
            'generated_image' => $this->fakeImage('card.png'),
            // El navegador envía el peso antes de comprimir; la miniatura usa el recibido.
            'image_original_bytes' => ['generated' => 6000000],
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $payload)
            ->assertSessionHasNoErrors();

        $card = Card::where('name', $payload['name'])->firstOrFail();

        // CardController escribe en storage/app/public real: borrar solo estos archivos.
        $files = [
            storage_path('app/public/'.$card->thumbnail_image),
            storage_path('app/public/'.$card->generated_image),
        ];

        $this->assertSame(
            ['generated' => 6000000, 'thumbnail' => strlen(base64_decode(self::PNG))],
            $card->image_original_bytes
        );

        // Al eliminar una imagen se olvida su peso original.
        $this->actingAs($this->admin)
            ->delete(route('admin.cards.image.destroy', [$card, 'thumbnail']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['generated' => 6000000], $card->refresh()->image_original_bytes);

        File::delete($files);
        @rmdir(dirname($files[1]));
    }

    public function test_image_original_bytes_are_validated(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload([
                'image_original_bytes' => ['otra' => 10, 'generated' => -1],
            ]))
            ->assertSessionHasErrors(['image_original_bytes', 'image_original_bytes.generated']);
    }

    public function test_saved_model_toast_shows_its_size(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.cards.store'), $this->cardPayload($this->modelPayload()))
            ->assertSessionHasNoErrors();

        $message = session()->get(SessionKey::FLASH_DATA)['toast']['message'];

        $this->assertMatchesRegularExpression('/Modelo 3D: \d+\.\d{2} MB → \d+\.\d{2} MB\./', $message);
    }
}
