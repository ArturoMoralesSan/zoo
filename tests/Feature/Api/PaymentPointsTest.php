<?php

namespace Tests\Feature\Api;

use App\Models\Donation;
use App\Models\Level;
use App\Models\PaymentMethod;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Points\PaymentPoints;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Puntos al comprar boletos (POST /api/tickets/orders) y al donar
 * (POST /api/donations): data.points con el mismo formato que el duelo.
 */
class PaymentPointsTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $user;

    protected PointRule $ticketRule;

    protected PointRule $donationRule;

    protected TicketType $ticketType;

    protected function setUp(): void
    {
        parent::setUp();

        config(['points.donation_min_amount' => 50]);

        $this->user = User::factory()->create(['points' => 0]);
        Sanctum::actingAs($this->user);

        $this->ticketRule = PointRule::updateOrCreate(
            ['type' => 'ticket_purchase'],
            ['name' => 'Compra de boleto', 'points' => 10, 'is_active' => true]
        );
        $this->donationRule = PointRule::updateOrCreate(
            ['type' => 'donation'],
            ['name' => 'Donación', 'points' => 5, 'is_active' => true]
        );

        PaymentMethod::create(['name' => 'Tarjeta', 'code' => 'card', 'is_active' => true, 'sort_order' => 1]);

        $this->ticketType = TicketType::create(['name' => 'General', 'price' => 100, 'is_active' => true]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function endpoints(): array
    {
        return [
            'compra de boletos' => ['ticket'],
            'donación' => ['donation'],
        ];
    }

    private function act(string $endpoint, float $amount = 100, int $quantity = 3): TestResponse
    {
        return $endpoint === 'ticket'
            ? $this->postJson(route('api.tickets.orders.store'), [
                'items' => [['ticket_type_id' => $this->ticketType->id, 'quantity' => $quantity]],
            ])
            : $this->postJson('/api/donations', ['amount' => $amount]);
    }

    private function rule(string $endpoint): PointRule
    {
        return $endpoint === 'ticket' ? $this->ticketRule : $this->donationRule;
    }

    /**
     * @param  array<int, int>  $overrides  puntos por id de regla
     */
    private function mode(bool $public, array $overrides): PointMode
    {
        $mode = PointMode::create([
            'name' => 'Interno',
            'is_active' => true,
            'is_public' => $public,
            'public_title' => $public ? 'Temporada de prueba' : null,
            'public_message' => $public ? 'Mensaje de la temporada.' : null,
            'icon' => $public ? 'snowflake' : null,
        ]);

        foreach ($overrides as $ruleId => $points) {
            $mode->rules()->attach($ruleId, ['points' => $points]);
        }

        return $mode;
    }

    #[DataProvider('endpoints')]
    public function test_awards_the_base_points_without_mode(string $endpoint): void
    {
        $base = $this->rule($endpoint)->points;

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points', [
                'awarded' => $base,
                'total' => $base,
                'level' => null,
                'level_up' => false,
                'season' => null,
            ]);

        $this->assertSame($base, $this->user->fresh()->points);
        $this->assertDatabaseHas('point_movements', [
            'user_id' => $this->user->id,
            'type' => $this->rule($endpoint)->type,
            'points' => $base,
        ]);
    }

    public function test_ticket_points_are_awarded_once_per_order_whatever_the_quantity(): void
    {
        $this->act('ticket', quantity: 5)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 10);

        $this->assertDatabaseCount('point_movements', 1);
        $this->assertDatabaseHas('point_movements', [
            'reference_type' => TicketOrder::class,
            'description' => 'Compra de boleto',
        ]);
    }

    public function test_donation_movement_references_the_donation(): void
    {
        $id = $this->act('donation')->assertCreated()->json('data.donation.id');

        $this->assertDatabaseHas('point_movements', [
            'reference_type' => Donation::class,
            'reference_id' => $id,
            'description' => 'Donación',
        ]);
    }

    #[DataProvider('endpoints')]
    public function test_rest_of_the_response_is_unchanged(string $endpoint): void
    {
        $response = $this->act($endpoint)->assertCreated();

        if ($endpoint === 'ticket') {
            $this->assertSame(
                ['id', 'folio', 'source', 'status', 'subtotal', 'discount', 'total', 'paid_at', 'payment', 'items', 'tickets', 'points'],
                array_keys($response->json('data'))
            );
            $response->assertJsonPath('message', 'Compra realizada correctamente.');
        } else {
            $this->assertSame(['donation', 'points'], array_keys($response->json('data')));
            $this->assertArrayNotHasKey('user', $response->json('data.donation'));
            $response->assertJsonPath('success', true);
        }
    }

    #[DataProvider('endpoints')]
    public function test_visible_season_that_raises_the_points(string $endpoint): void
    {
        $rule = $this->rule($endpoint);
        $this->mode(true, [$rule->id => 40]);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 40)
            ->assertJsonPath('data.points.total', 40)
            ->assertJsonPath('data.points.season.title', 'Temporada de prueba')
            ->assertJsonPath('data.points.season.message', 'Mensaje de la temporada.')
            ->assertJsonPath('data.points.season.base_points', $rule->points)
            ->assertJsonPath('data.points.season.icon', 'snowflake')
            ->assertJsonPath('data.points.season.color', null);
    }

    #[DataProvider('endpoints')]
    public function test_visible_season_that_lowers_the_points(string $endpoint): void
    {
        $rule = $this->rule($endpoint);
        $rule->update(['points' => 20]);
        $this->mode(true, [$rule->id => 3]);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 3)
            ->assertJsonPath('data.points.season.base_points', 20);
    }

    #[DataProvider('endpoints')]
    public function test_visible_season_that_sets_the_rule_to_zero_still_completes_the_action(string $endpoint): void
    {
        $rule = $this->rule($endpoint);
        $this->mode(true, [$rule->id => 0]);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 0)
            ->assertJsonPath('data.points.total', 0)
            ->assertJsonPath('data.points.season.base_points', $rule->points);

        $this->assertDatabaseCount('point_movements', 0);
    }

    #[DataProvider('endpoints')]
    public function test_hidden_mode_changes_the_points_without_season(string $endpoint): void
    {
        $this->mode(false, [$this->rule($endpoint)->id => 25]);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 25)
            ->assertJsonPath('data.points.season', null);
    }

    #[DataProvider('endpoints')]
    public function test_inactive_rule_completes_the_action_without_points(string $endpoint): void
    {
        $this->rule($endpoint)->update(['is_active' => false]);
        $this->mode(true, []);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 0)
            ->assertJsonPath('data.points.season.base_points', null);

        $this->assertSame(0, $this->user->fresh()->points);
        $this->assertDatabaseCount('point_movements', 0);
        $this->assertDatabaseCount($endpoint === 'ticket' ? 'ticket_orders' : 'donations', 1);
    }

    #[DataProvider('endpoints')]
    public function test_missing_rule_completes_the_action_without_points(string $endpoint): void
    {
        $this->rule($endpoint)->delete();

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 0);
    }

    #[DataProvider('endpoints')]
    public function test_points_can_level_up_the_user(string $endpoint): void
    {
        Level::create(['name' => 'Bronce', 'min_points' => 0, 'max_points' => 99]);
        $silver = Level::create(['name' => 'Plata', 'min_points' => 100, 'max_points' => null]);
        $this->user->update(['points' => 95]);

        $this->act($endpoint)
            ->assertCreated()
            ->assertJsonPath('data.points.total', 95 + $this->rule($endpoint)->points)
            ->assertJsonPath('data.points.level', ['id' => $silver->id, 'name' => 'Plata'])
            ->assertJsonPath('data.points.level_up', true);
    }

    public function test_donation_below_the_minimum_awards_no_points(): void
    {
        $this->mode(true, [$this->donationRule->id => 30]);

        $this->act('donation', amount: 49.99)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 0)
            ->assertJsonPath('data.points.season.base_points', null);

        $this->assertDatabaseCount('donations', 1);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_donation_of_exactly_the_minimum_awards_points(): void
    {
        $this->act('donation', amount: 50)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 5);
    }

    public function test_donation_minimum_comes_from_config(): void
    {
        config(['points.donation_min_amount' => 200]);

        $this->act('donation', amount: 150)
            ->assertCreated()
            ->assertJsonPath('data.points.awarded', 0);
    }

    public function test_awarding_the_same_order_twice_gives_points_once(): void
    {
        $id = $this->act('ticket')->assertCreated()->json('data.id');

        $again = app(PaymentPoints::class)->awardTicketOrder(TicketOrder::findOrFail($id));

        $this->assertSame(10, $again?->awarded);
        $this->assertFalse($again->levelUp);
        $this->assertSame(10, $this->user->fresh()->points);
        $this->assertDatabaseCount('point_movements', 1);
    }

    public function test_awarding_the_same_donation_twice_gives_points_once(): void
    {
        $id = $this->act('donation')->assertCreated()->json('data.donation.id');

        app(PaymentPoints::class)->awardDonation(Donation::findOrFail($id));

        $this->assertSame(5, $this->user->fresh()->points);
        $this->assertDatabaseCount('point_movements', 1);
    }

    public function test_unconfirmed_payment_awards_nothing(): void
    {
        $donation = Donation::create([
            'user_id' => $this->user->id,
            'payment_method_id' => PaymentMethod::firstOrFail()->id,
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->assertNull(app(PaymentPoints::class)->awardDonation($donation));
        $this->assertDatabaseCount('point_movements', 0);
    }
}
