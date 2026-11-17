<?php

namespace Tests\Feature\Api;

use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQuizState;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\QuizAnswer;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesLocation;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use App\Models\ZooZone;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class ZooDeckValidationTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    private const INSIDE = ['latitude' => 19.45, 'longitude' => -99.15];

    private const OUTSIDE = ['latitude' => 25.0, 'longitude' => -99.15];

    protected User $user;

    protected ZooZone $zone;

    protected SpeciesCategory $category;

    protected Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->user = User::factory()->create();

        $this->zone = ZooZone::create([
            'name' => 'Selva',
            'is_active' => true,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-99.20, 19.40],
                    [-99.10, 19.40],
                    [-99.10, 19.50],
                    [-99.20, 19.50],
                    [-99.20, 19.40],
                ]],
            ],
        ]);

        $this->category = SpeciesCategory::create([
            'name' => 'Mamíferos '.uniqid(),
            'is_active' => true,
        ]);

        $this->card = $this->createCard('Mapache');

        Sanctum::actingAs($this->user);
    }

    private function enforce(bool $enabled = true): void
    {
        config(['app.zoodeck_enforce_validations' => $enabled]);
    }

    /**
     * Especie con su ubicación dentro de la zona y una tarjeta con QR.
     */
    private function createCard(string $name, int $questions = 2, bool $qr = true): Card
    {
        $species = Species::create([
            'species_category_id' => $this->category->id,
            'common_name' => $name,
            'scientific_name' => $name.' '.uniqid(),
            'slug' => Str::slug($name).'-'.uniqid(),
            'is_active' => true,
        ]);

        SpeciesLocation::create([
            'species_id' => $species->id,
            'zone_id' => $this->zone->id,
            'name' => $name,
            'latitude' => 19.45,
            'longitude' => -99.15,
            'is_active' => true,
        ]);

        $card = Card::create([
            'species_id' => $species->id,
            'name' => $name,
            'rarity' => 'rara',
            'duel_settings_source' => 'card',
            'questions_per_duel' => 1,
            'retries' => 2,
            'cooldown_minutes' => 60,
            'is_active' => true,
        ]);

        for ($i = 0; $i < $questions; $i++) {
            $question = $card->quizQuestions()->create([
                'question' => "Pregunta {$i}",
                'is_active' => true,
            ]);

            $question->answers()->createMany([
                ['answer' => 'Correcta', 'is_correct' => true, 'sort_order' => 0],
                ['answer' => 'Incorrecta', 'is_correct' => false, 'sort_order' => 1],
            ]);
        }

        if ($qr) {
            $card->qrCodes()->create([
                'label' => 'Placa',
                'is_active' => true,
            ]);
        }

        return $card;
    }

    private function buyTicket(string $status = 'paid', ?string $paidAt = 'now'): void
    {
        $type = TicketType::create(['name' => 'General', 'price' => 50]);

        $order = TicketOrder::create([
            'folio' => 'ZOO-'.uniqid(),
            'user_id' => $this->user->id,
            'source' => 'app',
            'total' => 50,
            'status' => $status,
            'paid_at' => $paidAt ? now()->modify($paidAt) : null,
        ]);

        $item = $order->items()->create([
            'ticket_type_id' => $type->id,
            'quantity' => 1,
            'unit_price' => 50,
            'subtotal' => 50,
        ]);

        Ticket::create([
            'ticket_order_id' => $order->id,
            'ticket_order_item_id' => $item->id,
            'ticket_type_id' => $type->id,
            'qr_token' => (string) Str::uuid(),
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, float>  $coordinates
     */
    private function scan(?Card $card = null, array $coordinates = []): TestResponse
    {
        $token = ($card ?? $this->card)->qrCodes()->first()->token;

        return $this->postJson(route('api.duels.store'), ['code' => $token, ...$coordinates]);
    }

    /**
     * @param  array<string, float>  $coordinates
     */
    private function submit(TestResponse $duel, array $coordinates = []): TestResponse
    {
        $answers = collect($duel->json('data.questions'))
            ->map(fn (array $question) => [
                'question_id' => $question['id'],
                'answer_id' => QuizAnswer::query()
                    ->where('quiz_question_id', $question['id'])
                    ->where('is_correct', true)
                    ->value('id'),
            ])
            ->all();

        return $this->postJson(
            route('api.duels.submit', $duel->json('data.attempt_id')),
            ['answers' => $answers, ...$coordinates]
        );
    }

    /**
     * @param  array<string, float>  $coordinates
     */
    private function zooDeckMap(array $coordinates = []): TestResponse
    {
        return $this->getJson(route('api.map.species', $coordinates))->assertOk();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapCard(TestResponse $map, Card $card): ?array
    {
        return collect($map->json('data.zones.0.species_locations'))
            ->flatMap(fn (array $location) => $location['species']['cards'])
            ->firstWhere('id', $card->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Duelos
    |--------------------------------------------------------------------------
    */

    public function test_sin_validaciones_el_duelo_inicia_sin_coordenadas(): void
    {
        $this->scan()->assertOk();
    }

    public function test_con_validaciones_exige_la_ubicacion(): void
    {
        $this->enforce();
        $this->buyTicket();

        $this->scan()
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'location_required');
    }

    public function test_con_validaciones_rechaza_fuera_del_zoologico_sin_contar_el_escaneo(): void
    {
        $this->enforce();
        $this->buyTicket();

        $this->scan(coordinates: self::OUTSIDE)
            ->assertForbidden()
            ->assertJsonPath('data.reason', 'outside_zoo');

        $this->assertSame(0, $this->card->qrCodes()->first()->scans_count);
    }

    public function test_con_validaciones_exige_un_boleto_pagado_hoy(): void
    {
        $this->enforce();

        $this->scan(coordinates: self::INSIDE)
            ->assertForbidden()
            ->assertJsonPath('data.reason', 'no_ticket');
    }

    public function test_un_boleto_pagado_ayer_no_sirve(): void
    {
        $this->enforce();
        $this->buyTicket(paidAt: '-1 day');

        $this->scan(coordinates: self::INSIDE)->assertJsonPath('data.reason', 'no_ticket');
    }

    public function test_una_orden_pendiente_no_sirve(): void
    {
        $this->enforce();
        $this->buyTicket(status: 'pending', paidAt: null);

        $this->scan(coordinates: self::INSIDE)->assertJsonPath('data.reason', 'no_ticket');
    }

    public function test_dentro_y_con_boleto_inicia_el_duelo(): void
    {
        $this->enforce();
        $this->buyTicket();

        $this->scan(coordinates: self::INSIDE)->assertOk();

        $this->assertSame(1, $this->card->qrCodes()->first()->scans_count);
    }

    public function test_un_qr_invalido_sigue_dando_404_antes_de_las_validaciones(): void
    {
        $this->enforce();

        $this->postJson(route('api.duels.store'), ['code' => 'no-existe'])->assertNotFound();
    }

    public function test_responder_tambien_revisa_la_visita(): void
    {
        $this->buyTicket();
        $duel = $this->scan()->assertOk();

        $this->enforce();

        $this->submit($duel)->assertStatus(422)->assertJsonPath('data.reason', 'location_required');
        $this->submit($duel, self::OUTSIDE)->assertForbidden()->assertJsonPath('data.reason', 'outside_zoo');
        $this->submit($duel, self::INSIDE)->assertCreated()->assertJsonPath('data.outcome', 'won');
    }

    /*
    |--------------------------------------------------------------------------
    | validate-capture
    |--------------------------------------------------------------------------
    */

    public function test_validate_capture_usa_las_mismas_reglas(): void
    {
        $this->buyTicket();

        $this->getJson("/api/map/validate-capture/{$this->user->id}/19.45/-99.15")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => ['zones' => true, 'ticket' => true, 'enforced' => false],
            ]);

        $this->enforce();

        $this->getJson("/api/map/validate-capture/{$this->user->id}/25/-99.15")
            ->assertJsonPath('data.zones', false)
            ->assertJsonPath('data.enforced', true);
    }

    public function test_validate_capture_rechaza_a_otro_usuario(): void
    {
        $other = User::factory()->create();

        $this->getJson("/api/map/validate-capture/{$other->id}/19.45/-99.15")->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Mapa de ZooDeck
    |--------------------------------------------------------------------------
    */

    public function test_el_mapa_de_zoodeck_tiene_la_forma_del_mapa_normal(): void
    {
        MapMarker::create([
            'zone_id' => $this->zone->id,
            'name' => 'Café',
            'latitude' => 19.46,
            'longitude' => -99.16,
            'icon' => 'poi.svg',
            'is_active' => true,
        ]);

        MapPath::create([
            'zone_id' => $this->zone->id,
            'name' => 'Camino',
            'coordinates' => ['nodes' => [], 'edges' => []],
            'distance' => 0,
            'estimated_time' => 0,
            'is_active' => true,
            'order' => 0,
        ]);

        $normal = $this->getJson(route('api.map.index'))->assertOk();
        $zooDeck = $this->zooDeckMap();

        $this->assertSame(
            [
                'id', 'name', 'description', 'type', 'geometry', 'map_image', 'map_image_bounds',
                'markers', 'species_locations', 'paths',
            ],
            array_keys($normal->json('data.zones.0'))
        );

        $this->assertSame(
            [...array_keys($normal->json('data.zones.0')), 'species_count'],
            array_keys($zooDeck->json('data.zones.0'))
        );

        $this->assertSame(
            array_keys($normal->json('data.zones.0.species_locations.0')),
            array_keys($zooDeck->json('data.zones.0.species_locations.0'))
        );

        $this->assertSame($normal->json('data.zones.0.markers'), $zooDeck->json('data.zones.0.markers'));
        $this->assertSame($normal->json('data.zones.0.paths'), $zooDeck->json('data.zones.0.paths'));
    }

    public function test_el_mapa_de_zoodeck_solo_lista_especies_con_qr_y_no_expone_tokens(): void
    {
        $this->createCard('Sin QR', qr: false);

        $map = $this->zooDeckMap();

        $names = collect($map->json('data.zones.0.species_locations'))->pluck('species.common_name');

        $this->assertSame(['Mapache'], $names->all());
        $this->assertSame(1, $map->json('data.zones.0.species_count'));
        $this->assertStringNotContainsString('token', $map->getContent());
        $this->assertStringNotContainsString($this->card->qrCodes()->first()->token, $map->getContent());
    }

    public function test_el_mapa_marca_el_estado_y_los_intentos_de_cada_tarjeta(): void
    {
        $owned = $this->createCard('León');
        $locked = $this->createCard('Jaguar');

        CardCapture::create([
            'user_id' => $this->user->id,
            'card_id' => $owned->id,
            'captured_at' => now(),
        ]);

        CardQuizState::create([
            'user_id' => $this->user->id,
            'card_id' => $locked->id,
            'failed_attempts' => 2,
            'locked_until' => now()->addHour(),
        ]);

        $map = $this->zooDeckMap();

        $this->assertSame(
            ['status' => 'available', 'can_duel' => true, 'reason' => null],
            collect($this->mapCard($map, $this->card))->only('status', 'can_duel', 'reason')->all()
        );
        $this->assertSame(['max' => 2, 'left' => 2, 'locked_until' => null], $this->mapCard($map, $this->card)['attempts']);

        $this->assertSame('owned', $this->mapCard($map, $owned)['reason']);

        $lockedCard = $this->mapCard($map, $locked);
        $this->assertSame('locked', $lockedCard['status']);
        $this->assertSame(0, $lockedCard['attempts']['left']);
        $this->assertNotNull($lockedCard['attempts']['locked_until']);
        $this->assertFalse($lockedCard['can_duel']);
    }

    public function test_un_bloqueo_terminado_se_muestra_disponible(): void
    {
        CardQuizState::create([
            'user_id' => $this->user->id,
            'card_id' => $this->card->id,
            'failed_attempts' => 2,
            'locked_until' => now()->subMinute(),
        ]);

        $card = $this->mapCard($this->zooDeckMap(), $this->card);

        $this->assertSame('available', $card['status']);
        $this->assertSame(2, $card['attempts']['left']);
    }

    public function test_el_mapa_informa_la_visita(): void
    {
        $this->assertSame(
            ['enforced' => false, 'ticket' => false, 'inside_zoo' => null, 'can_duel' => true, 'reason' => 'location_required'],
            $this->zooDeckMap()->json('data.visit')
        );
        $this->assertTrue($this->mapCard($this->zooDeckMap(), $this->card)['can_duel']);

        $this->enforce();

        $this->assertSame('location_required', $this->mapCard($this->zooDeckMap(), $this->card)['reason']);
        $this->assertSame('outside_zoo', $this->zooDeckMap(self::OUTSIDE)->json('data.visit.reason'));
        $this->assertSame('no_ticket', $this->zooDeckMap(self::INSIDE)->json('data.visit.reason'));

        $this->buyTicket();

        $this->assertSame(
            ['enforced' => true, 'ticket' => true, 'inside_zoo' => true, 'can_duel' => true, 'reason' => null],
            $this->zooDeckMap(self::INSIDE)->json('data.visit')
        );
    }

    public function test_el_mapa_coincide_con_lo_que_acepta_post_duels(): void
    {
        $owned = $this->createCard('León');
        $locked = $this->createCard('Jaguar');

        CardCapture::create(['user_id' => $this->user->id, 'card_id' => $owned->id, 'captured_at' => now()]);
        CardQuizState::create([
            'user_id' => $this->user->id,
            'card_id' => $locked->id,
            'failed_attempts' => 2,
            'locked_until' => now()->addHour(),
        ]);

        $this->enforce();
        $this->buyTicket();

        $expectedStatus = [
            null => 200,
            'owned' => 409,
            'locked' => 423,
        ];

        foreach ([self::INSIDE, self::OUTSIDE, []] as $coordinates) {
            $map = $this->zooDeckMap($coordinates);

            foreach ([$this->card, $owned, $locked] as $card) {
                $mapped = $this->mapCard($map, $card);
                $response = $this->scan($card, $coordinates);

                $status = match ($mapped['reason']) {
                    'outside_zoo', 'no_ticket' => 403,
                    'location_required' => 422,
                    default => $expectedStatus[$mapped['reason']],
                };

                $response->assertStatus($status);
                $this->assertSame($mapped['can_duel'], $response->status() === 200, "{$card->name}: {$mapped['reason']}");
            }
        }
    }

    public function test_una_tarjeta_sin_preguntas_activas_no_aparece_en_el_mapa(): void
    {
        $hidden = $this->createCard('Guacamaya');
        $hidden->quizQuestions()->update(['is_active' => false]);

        $this->enforce();
        $this->buyTicket();

        // Fuera del zoológico el motivo de la visita ocultaría "no_questions"; aun así no se lista.
        foreach ([self::OUTSIDE, self::INSIDE, []] as $coordinates) {
            $map = $this->zooDeckMap($coordinates);

            $names = collect($map->json('data.zones.0.species_locations'))->pluck('species.common_name');

            $this->assertSame(['Mapache'], $names->all());
            $this->assertNull($this->mapCard($map, $hidden));
            $this->assertSame(1, $map->json('data.zones.0.species_count'));
        }

        // POST /duels conserva su propia revisión: con QR válido y visita correcta, 422.
        $this->scan($hidden, self::INSIDE)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta tarjeta aún no tiene preguntas disponibles.')
            ->assertJsonMissingPath('data.reason');
    }
}
