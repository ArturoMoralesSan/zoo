<?php

namespace Tests\Feature\Api;

use App\Models\CardRaritySetting;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class PointSeasonTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected PointRule $rare;

    protected PointRule $epic;

    protected function setUp(): void
    {
        parent::setUp();

        // Las creó la migración de reglas por rareza.
        $this->rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $this->epic = PointRule::where('type', 'card_capture_epica')->firstOrFail();
    }

    private function mode(array $attributes, array $overrides = []): PointMode
    {
        $mode = PointMode::create(array_merge([
            'name' => 'Interno '.uniqid(),
            'description' => 'Nota interna',
            'is_active' => true,
        ], $attributes));

        foreach ($overrides as $ruleId => $points) {
            $mode->rules()->attach($ruleId, ['points' => $points]);
        }

        return $mode;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson(route('api.points.season'))->assertUnauthorized();
    }

    public function test_returns_null_without_active_mode(): void
    {
        $this->mode(['is_active' => false, 'is_public' => true, 'public_title' => 'Halloween']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => null]);
    }

    public function test_hidden_active_mode_is_not_revealed(): void
    {
        $this->mode(['is_public' => false], [$this->rare->id => 10]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_public_mode_returns_its_notice_and_card_changes_per_rarity(): void
    {
        $this->mode(
            [
                'is_public' => true,
                'public_title' => '¡Halloween!',
                'public_message' => 'Las épicas dan el doble.',
            ],
            [
                $this->epic->id => 150,
                $this->rare->id => 50, // Igual que la base: no es un cambio.
            ]
        );

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    'title' => '¡Halloween!',
                    'message' => 'Las épicas dan el doble.',
                    'cards' => [
                        [
                            'rarity' => 'epica',
                            'rarity_label' => 'Épica',
                            'base_points' => 75,
                            'points' => 150,
                        ],
                    ],
                    'other' => [],
                ],
            ]);
    }

    public function test_other_actions_are_listed_separately_from_cards(): void
    {
        $ticket = PointRule::create([
            'type' => 'ticket_purchase',
            'name' => 'Compra de boleto',
            'points' => 10,
            'is_active' => true,
        ]);

        $this->mode(
            ['is_public' => true, 'public_title' => 'Fin de semana doble'],
            [$ticket->id => 20, $this->epic->id => 150]
        );

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertJsonCount(1, 'data.cards')
            ->assertJsonPath('data.cards.0.rarity', 'epica')
            ->assertJsonPath('data.other', [
                [
                    'type' => 'ticket_purchase',
                    'name' => 'Compra de boleto',
                    'base_points' => 10,
                    'points' => 20,
                ],
            ]);
    }

    public function test_rarities_sharing_a_rule_each_get_an_entry_in_rarity_order(): void
    {
        // Común y rara usan la misma regla.
        CardRaritySetting::where('rarity', 'comun')->update(['point_rule_id' => $this->rare->id]);

        $this->mode(['is_public' => true, 'public_title' => 'Evento'], [$this->rare->id => 0]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertJsonPath('data.cards.*.rarity', ['comun', 'rara'])
            ->assertJsonPath('data.cards.0.points', 0)
            ->assertJsonPath('data.other', []);
    }

    public function test_rarity_without_points_and_inactive_rules_are_not_listed(): void
    {
        CardRaritySetting::where('rarity', 'rara')->update(['point_rule_id' => null]);
        $this->epic->update(['is_active' => false]);

        $this->mode(
            ['is_public' => true, 'public_title' => 'Evento'],
            [$this->rare->id => 10, $this->epic->id => 0]
        );

        Sanctum::actingAs(User::factory()->create());

        // La regla rara ya no la usa ninguna rareza: queda como otra acción.
        $this->getJson(route('api.points.season'))
            ->assertOk()
            ->assertJsonPath('data.cards', [])
            ->assertJsonPath('data.other.*.type', ['card_capture_rara']);
    }
}
