<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\DuelMessageSetting;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\User;
use App\Services\CardPack\CardPackBuilder;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Paquete sin conexión: copias cifradas, catálogo y llaves.
 * Ver docs/offline-packs-backend.md.
 */
class CardPackTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $user;

    protected Species $species;

    protected Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml los apaga; aquí se encienden sobre un disco falso.
        $this->disk = Storage::fake('public');
        config([
            'cards.packs.enabled' => true,
            'cards.packs.secret' => 'secreto-de-prueba',
            'glb.disks.resized' => 'public',
        ]);

        $this->user = User::factory()->create();

        $category = SpeciesCategory::create([
            'name' => 'Felinos '.uniqid(),
            'is_active' => true,
        ]);

        $this->species = Species::create([
            'species_category_id' => $category->id,
            'common_name' => 'Jaguar '.uniqid(),
            'scientific_name' => 'Panthera onca '.uniqid(),
            'slug' => 'jaguar-'.uniqid(),
            'is_active' => true,
        ]);

        DuelMessageSetting::current();

        Sanctum::actingAs($this->user);
    }

    /**
     * Tarjeta con sus tres archivos guardados en el disco, como los deja el editor.
     */
    private function createCard(array $attributes = []): Card
    {
        $files = [
            'model_file' => 'cards/models/'.uniqid('', true).'.glb',
            'generated_image' => 'cards/'.uniqid('', true).'.webp',
            'thumbnail_image' => 'cards/'.uniqid('', true).'.webp',
        ];

        foreach ($files as $column => $path) {
            $this->disk->put($path, "contenido de {$column} ".uniqid());
        }

        return Card::create(array_merge([
            'species_id' => $this->species->id,
            'name' => 'Jaguar',
            'rarity' => 'rara',
            'astucia' => 60,
            'rapidez' => 70,
            'fuerza' => 80,
            'duel_settings_source' => 'card',
            'questions_per_duel' => 1,
            'retries' => 3,
            'is_active' => true,
        ], $files, $attributes));
    }

    private function binFor(Card $card, string $column): string
    {
        $builder = app(CardPackBuilder::class);

        return $builder->binPath($card->id, CardPackBuilder::fileId($card->{$column}));
    }

    private function catalogFiles(): array
    {
        return $this->getJson(route('api.cards.pack'))->assertOk()->json('data.files');
    }

    public function test_saving_a_card_encrypts_each_file_separately(): void
    {
        $card = $this->createCard();
        $builder = app(CardPackBuilder::class);

        foreach (['model_file', 'generated_image', 'thumbnail_image'] as $column) {
            $sealed = $this->disk->get($this->binFor($card, $column));

            $this->assertNotSame($this->disk->get($card->{$column}), $sealed);
            $this->assertSame(
                $this->disk->get($card->{$column}),
                $builder->decrypt($sealed, $builder->key($card->{$column}))
            );
        }

        $this->assertCount(3, $this->disk->files($builder->directory($card->id)));
    }

    public function test_catalog_lists_one_entry_per_file_of_active_cards_without_keys(): void
    {
        $card = $this->createCard();
        $this->createCard(['is_active' => false]);

        $response = $this->getJson(route('api.cards.pack'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.files');

        $files = collect($response->json('data.files'));

        $this->assertEqualsCanonicalizing(['model', 'card_image', 'thumbnail'], $files->pluck('kind')->all());
        $this->assertSame([$card->id], $files->pluck('card_id')->unique()->values()->all());
        $this->assertSame($files->sum('bytes'), $response->json('data.total_bytes'));
        $this->assertStringNotContainsString('key', $response->getContent());

        $model = $files->firstWhere('kind', 'model');
        $bin = $this->binFor($card, 'model_file');

        $this->assertSame(CardPackBuilder::fileId($card->model_file), $model['id']);
        $this->assertStringEndsWith('/storage/'.$bin, $model['url']);
        $this->assertSame(hash('sha256', $this->disk->get($bin)), $model['sha256']);
    }

    public function test_catalog_answers_304_when_nothing_changed(): void
    {
        $this->createCard();

        $etag = $this->getJson(route('api.cards.pack'))->assertOk()->headers->get('ETag');

        $this->assertNotNull($etag);

        $this->getJson(route('api.cards.pack'), ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_only_owned_cards_get_their_keys(): void
    {
        $owned = $this->createCard();
        $unowned = $this->createCard();

        CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $owned->id,
            'captured_at' => now(),
        ]);

        $cards = collect($this->getJson(route('api.cards.index'))->assertOk()->json('data'));
        $pack = $cards->firstWhere('id', $owned->id)['pack'];

        $this->assertArrayNotHasKey('pack', $cards->firstWhere('id', $unowned->id));
        $this->assertSame(['model', 'card_image', 'thumbnail'], array_keys($pack));

        $builder = app(CardPackBuilder::class);

        $this->assertSame(
            $this->disk->get($owned->generated_image),
            $builder->decrypt(
                $this->disk->get($this->binFor($owned, 'generated_image')),
                base64_decode($pack['card_image']['key'])
            )
        );
    }

    public function test_duel_start_sends_model_and_thumbnail_keys_but_not_the_card_image(): void
    {
        $card = $this->createCard();

        $question = $card->quizQuestions()->create(['question' => '¿Qué come?', 'is_active' => true]);
        $question->answers()->createMany([
            ['answer' => 'Carne', 'is_correct' => true, 'sort_order' => 0],
            ['answer' => 'Pasto', 'is_correct' => false, 'sort_order' => 1],
        ]);
        $qrCode = $card->qrCodes()->create(['label' => 'Entrada', 'is_active' => true]);

        $pack = $this->postJson(route('api.duels.store'), ['code' => $qrCode->token])
            ->assertOk()
            ->json('data.card.pack');

        $this->assertSame(['model', 'thumbnail'], array_keys($pack));
        $this->assertSame(CardPackBuilder::fileId($card->model_file), $pack['model']['id']);
    }

    public function test_a_new_thumbnail_replaces_only_its_own_file(): void
    {
        $card = $this->createCard();

        $oldThumbnail = $this->binFor($card, 'thumbnail_image');
        $modelBin = $this->binFor($card, 'model_file');
        $modelSealed = $this->disk->get($modelBin);
        $before = collect($this->catalogFiles())->keyBy('kind');

        $newPath = 'cards/'.uniqid('', true).'.webp';
        $this->disk->put($newPath, 'miniatura nueva');
        $card->update(['thumbnail_image' => $newPath]);

        $after = collect($this->catalogFiles())->keyBy('kind');

        $this->disk->assertMissing($oldThumbnail);
        $this->disk->assertExists($this->binFor($card, 'thumbnail_image'));
        $this->assertSame($modelSealed, $this->disk->get($modelBin));

        $this->assertNotSame($before['thumbnail']['id'], $after['thumbnail']['id']);
        $this->assertSame($before['model'], $after['model']);
        $this->assertSame($before['card_image'], $after['card_image']);
    }

    public function test_a_new_card_design_changes_only_the_card_image_entry(): void
    {
        $card = $this->createCard();
        $before = collect($this->catalogFiles())->keyBy('kind');

        $newPath = 'cards/'.uniqid('', true).'.webp';
        $this->disk->put($newPath, 'diseño nuevo con otra fuente y color');
        $card->update(['generated_image' => $newPath, 'fade_color' => '#000000']);

        $after = collect($this->catalogFiles())->keyBy('kind');

        $this->assertSame(CardPackBuilder::fileId($newPath), $after['card_image']['id']);
        $this->assertSame($before['model'], $after['model']);
        $this->assertSame($before['thumbnail'], $after['thumbnail']);
    }

    public function test_removing_an_image_removes_its_file(): void
    {
        $card = $this->createCard();
        $bin = $this->binFor($card, 'thumbnail_image');

        $card->update(['thumbnail_image' => null]);

        $this->disk->assertMissing($bin);
        $this->assertNotContains('thumbnail', array_column($this->catalogFiles(), 'kind'));
    }

    public function test_deleting_a_card_removes_its_folder(): void
    {
        $card = $this->createCard();
        $directory = app(CardPackBuilder::class)->directory($card->id);

        $card->delete();

        $this->assertSame([], $this->disk->allFiles($directory));
    }

    public function test_nothing_is_written_when_packs_are_disabled(): void
    {
        config(['cards.packs.enabled' => false]);

        $card = $this->createCard();

        $this->assertSame([], $this->disk->allFiles(app(CardPackBuilder::class)->directory($card->id)));
    }

    public function test_build_command_with_force_reencrypts_keeping_the_same_ids(): void
    {
        $card = $this->createCard();
        $before = collect($this->catalogFiles())->keyBy('kind');

        $this->artisan('cards:packs:build', ['--card' => [$card->id], '--force' => true])
            ->assertSuccessful();

        $after = collect($this->catalogFiles())->keyBy('kind');

        $this->assertSame($before['model']['id'], $after['model']['id']);
        $this->assertNotSame($before['model']['sha256'], $after['model']['sha256']);
    }
}
