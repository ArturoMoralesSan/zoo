<?php

namespace Tests\Feature\Admin;

use App\Models\SpeciesCategory;
use App\Models\SpeciesTag;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class SpeciesAiFillTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $editor;

    protected SpeciesCategory $felinos;

    protected SpeciesTag $nocturno;

    protected SpeciesTag $solitario;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->withoutVite();

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'gemini-test',
            // El SSR de Inertia también usa Http y lo frenaría preventStrayRequests.
            'inertia.ssr.enabled' => false,
        ]);

        foreach (['species.view', 'species.create', 'species.edit', 'species.delete'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $this->editor = User::factory()->create();
        $this->editor->givePermissionTo('species.edit');

        $this->felinos = SpeciesCategory::create(['name' => 'Felinos', 'is_active' => true]);
        SpeciesCategory::create(['name' => 'Inactiva', 'is_active' => false]);

        $this->nocturno = SpeciesTag::create(['name' => 'Nocturno', 'slug' => 'nocturno', 'is_active' => true]);
        $this->solitario = SpeciesTag::create(['name' => 'Solitario', 'slug' => 'solitario', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function geminiReturns(array $data): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => json_encode($data)]]],
            ]],
        ])]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function fill(array $payload = []): TestResponse
    {
        return $this->actingAs($this->editor)->postJson(route('admin.species.ai-fill'), [
            'common_name' => 'Jaguar',
            'scientific_name' => 'Panthera onca',
            'fields' => ['description', 'diet'],
            ...$payload,
        ]);
    }

    public function test_create_and_edit_pages_report_configuration(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(['species.view', 'species.create', 'species.edit', 'species.delete']);

        $this->actingAs($admin)
            ->get(route('admin.species.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/species/Create', false)
                ->where('aiEnabled', true));

        config(['services.gemini.key' => null]);

        $this->actingAs($admin)
            ->get(route('admin.species.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('aiEnabled', false));
    }

    public function test_returns_only_the_requested_fields_without_saving(): void
    {
        $this->geminiReturns([
            'recognized' => true,
            'description' => '  El felino más grande de América.  ',
            'diet' => 'carnivore',
            'origin' => 'No pedido',
        ]);

        $this->fill()
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => ['suggestions' => [
                    'description' => 'El felino más grande de América.',
                    'diet' => 'carnivore',
                ]],
            ]);

        $this->assertDatabaseCount('species', 0);
    }

    public function test_schema_and_prompt_carry_only_requested_fields_and_active_catalogs(): void
    {
        $this->geminiReturns(['recognized' => true]);

        $this->fill(['fields' => ['habitats', 'species_category_id', 'tags']])->assertOk();

        Http::assertSent(function (Request $request) {
            $schema = $request['generationConfig']['responseJsonSchema'];
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return $request->hasHeader('x-goog-api-key', 'test-key')
                && array_keys($schema['properties']) === ['recognized', 'habitats', 'category', 'tags']
                && $schema['properties']['category']['enum'] === ['Felinos']
                && $schema['properties']['tags']['items']['enum'] === ['Nocturno', 'Solitario']
                && str_contains($prompt, 'Panthera onca')
                && str_contains($prompt, 'Jaguar');
        });
    }

    public function test_maps_names_to_ids_and_drops_invalid_values(): void
    {
        $this->geminiReturns([
            'recognized' => true,
            'diet' => 'vegano',
            'habitats' => ['tropical_rainforest', 'luna', 'tropical_rainforest', 'wetland'],
            'conservation_status' => 'NT',
            'category' => 'Inactiva',
            'tags' => ['Nocturno', 'Inventada', 'Solitario'],
        ]);

        $this->fill(['fields' => ['diet', 'habitats', 'conservation_status', 'species_category_id', 'tags']])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => ['suggestions' => [
                    'habitats' => ['tropical_rainforest', 'wetland'],
                    'conservation_status' => 'NT',
                    'tags' => [$this->nocturno->id, $this->solitario->id],
                ]],
            ]);
    }

    public function test_maps_the_category_name_to_its_id(): void
    {
        $this->geminiReturns(['recognized' => true, 'category' => 'Felinos']);

        $this->fill(['fields' => ['species_category_id']])
            ->assertJsonPath('data.suggestions.species_category_id', $this->felinos->id);
    }

    public function test_nothing_valid_returns_an_empty_object(): void
    {
        $this->geminiReturns(['recognized' => true, 'tags' => []]);

        $response = $this->fill(['fields' => ['tags']])->assertOk();

        $this->assertSame('{"success":true,"data":{"suggestions":{"tags":[]}}}', $response->getContent());
    }

    public function test_unknown_species_gives_a_friendly_error(): void
    {
        $this->geminiReturns(['recognized' => false]);

        $response = $this->fill(['scientific_name' => 'Pantherus inventadus'])->assertStatus(422);

        $this->assertStringContainsString('no reconoce esta especie', $response->json('message'));
    }

    public function test_blocked_answer_speaks_about_species(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'SAFETY']],
        ])]);

        $this->fill()
            ->assertStatus(422)
            ->assertJsonPath('message', 'La IA no pudo completar los datos de esta especie.');
    }

    public function test_missing_key_calls_nothing(): void
    {
        config(['services.gemini.key' => '']);

        $this->fill()->assertStatus(503)->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_requires_create_or_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('species.view');

        $this->actingAs($viewer)
            ->postJson(route('admin.species.ai-fill'), [
                'common_name' => 'Jaguar',
                'scientific_name' => 'Panthera onca',
                'fields' => ['description'],
            ])
            ->assertForbidden();

        $creator = User::factory()->create();
        $creator->givePermissionTo('species.create');
        $this->geminiReturns(['recognized' => true]);

        $this->actingAs($creator)
            ->postJson(route('admin.species.ai-fill'), [
                'common_name' => 'Jaguar',
                'scientific_name' => 'Panthera onca',
                'fields' => ['description'],
            ])
            ->assertOk();
    }

    public function test_validates_names_and_fields(): void
    {
        $this->fill(['common_name' => '', 'scientific_name' => null, 'fields' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['common_name', 'scientific_name', 'fields']);

        $this->fill(['fields' => ['slug']])->assertJsonValidationErrors(['fields.0']);

        Http::assertNothingSent();
    }
}
