<?php

namespace Tests\Feature\Admin;

use App\Models\CardRaritySetting;
use App\Models\Donation;
use App\Models\PaymentMethod;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

/**
 * Puntos en taquilla y en donaciones del panel, y qué reglas tienen efecto
 * en "Reglas de puntos" y "Modos de puntos".
 */
class PointsUsageAdminTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected User $visitor;

    protected PointRule $ticketRule;

    protected PointRule $donationRule;

    protected PaymentMethod $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['points.donation_min_amount' => 50]);

        $permissions = [];

        foreach (['ticket-orders', 'donations', 'point-rules', 'point-modes'] as $resource) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = Permission::findOrCreate("{$resource}.{$action}")->name;
            }
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo($permissions);

        $this->visitor = User::factory()->create(['points' => 0]);

        $this->ticketRule = PointRule::create(['type' => 'ticket_purchase', 'name' => 'Compra de boleto', 'points' => 10, 'is_active' => true]);
        $this->donationRule = PointRule::create(['type' => 'donation', 'name' => 'Donación', 'points' => 5, 'is_active' => true]);

        $this->cash = PaymentMethod::create(['name' => 'Efectivo', 'code' => 'cash', 'is_active' => true, 'sort_order' => 1]);
    }

    private function sellTickets(?int $userId): void
    {
        $type = TicketType::create(['name' => 'General', 'price' => 100, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('admin.ticket-orders.store'), [
                'user_id' => $userId,
                'items' => [['ticket_type_id' => $type->id, 'quantity' => 2]],
                'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 200]],
            ])
            ->assertRedirect(route('admin.ticket-orders.index'));
    }

    public function test_box_office_sale_awards_points_once_per_order(): void
    {
        $this->sellTickets($this->visitor->id);

        $this->assertSame(10, $this->visitor->fresh()->points);
        $this->assertDatabaseHas('point_movements', [
            'user_id' => $this->visitor->id,
            'reference_type' => TicketOrder::class,
            'points' => 10,
        ]);
    }

    public function test_box_office_sale_completes_with_the_rule_inactive(): void
    {
        $this->ticketRule->update(['is_active' => false]);

        $this->sellTickets($this->visitor->id);

        $this->assertDatabaseCount('ticket_orders', 1);
        $this->assertSame(0, $this->visitor->fresh()->points);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_box_office_sale_without_user_awards_nothing(): void
    {
        $this->sellTickets(null);

        $this->assertDatabaseCount('ticket_orders', 1);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_panel_donation_applies_the_donation_rule_and_minimum(): void
    {
        foreach ([100, 20] as $amount) {
            $this->actingAs($this->admin)
                ->post(route('admin.donations.store'), [
                    'user_id' => $this->visitor->id,
                    'payment_method_id' => $this->cash->id,
                    'amount' => $amount,
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseCount('donations', 2);
        $this->assertSame(5, $this->visitor->fresh()->points);
        $this->assertDatabaseHas('point_movements', [
            'reference_type' => Donation::class,
            'description' => 'Donación',
            'points' => 5,
        ]);
    }

    public function test_rules_index_shows_where_each_rule_is_used(): void
    {
        PointRule::create(['type' => 'event_attendance', 'name' => 'Asistencia a evento', 'points' => 20, 'is_active' => true]);
        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        CardRaritySetting::where('rarity', 'epica')->update(['point_rule_id' => $rare->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.point-rules.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($rare) {
                $usage = collect($page->toArray()['props']['rules']['data'])->keyBy('type')->map->usage;

                $this->assertSame(['Compra de boleto en app y taquilla'], $usage['ticket_purchase']['actions']);
                $this->assertSame(['Donación desde la app y el panel'], $usage['donation']['actions']);
                $this->assertSame(['Rara', 'Épica'], $usage[$rare->type]['rarities']);
                $this->assertTrue($usage[$rare->type]['in_use']);
                $this->assertSame(['actions' => [], 'rarities' => [], 'in_use' => false], $usage['event_attendance']);
                // Ya no la usa ninguna rareza.
                $this->assertFalse($usage['card_capture_epica']['in_use']);
            });
    }

    public function test_mode_form_marks_rules_without_use(): void
    {
        PointRule::create(['type' => 'event_attendance', 'name' => 'Asistencia a evento', 'points' => 20, 'is_active' => true]);
        $mode = PointMode::create(['name' => 'Interno', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.point-modes.edit', $mode))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $inUse = collect($page->toArray()['props']['rules'])->pluck('in_use', 'type');

                $this->assertTrue($inUse['ticket_purchase']);
                $this->assertTrue($inUse['donation']);
                $this->assertTrue($inUse['card_capture_rara']);
                $this->assertFalse($inUse['event_attendance']);
            });
    }
}
