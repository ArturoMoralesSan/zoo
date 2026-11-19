<?php

namespace Tests\Feature\Admin;

use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class PointModeTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $admin;

    protected PointRule $ticket;

    protected PointRule $rare;

    protected function setUp(): void
    {
        parent::setUp();

        // Las páginas nuevas no están en el manifiesto de un build anterior.
        $this->withoutVite();

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            Permission::findOrCreate("point-modes.{$action}");
        }

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'point-modes.view',
            'point-modes.create',
            'point-modes.edit',
            'point-modes.delete',
        ]);

        $this->ticket = PointRule::create([
            'type' => 'ticket_purchase',
            'name' => 'Compra de boleto',
            'points' => 10,
            'is_active' => true,
        ]);

        // La creó la migración de reglas por rareza.
        $this->rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
    }

    private function mode(string $name, array $overrides = [], bool $active = false): PointMode
    {
        $mode = PointMode::create(['name' => $name, 'is_active' => $active]);

        $mode->rules()->sync(
            collect($overrides)->mapWithKeys(fn ($points, $ruleId) => [$ruleId => ['points' => $points]])->all()
        );

        return $mode;
    }

    public function test_index_and_forms_render_with_permission(): void
    {
        $mode = $this->mode('Temporada alta');

        $this->actingAs($this->admin)->get(route('admin.point-modes.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.point-modes.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.point-modes.edit', $mode))->assertOk();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $mode = $this->mode('Temporada alta');

        $this->actingAs($user)->get(route('admin.point-modes.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.point-modes.activate', $mode))->assertForbidden();
    }

    public function test_view_only_user_cannot_change_modes(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('point-modes.view');
        $mode = $this->mode('Temporada alta');

        $this->actingAs($viewer)->get(route('admin.point-modes.index'))->assertOk();
        $this->actingAs($viewer)->post(route('admin.point-modes.activate', $mode))->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.point-modes.destroy', $mode))->assertForbidden();
    }

    public function test_store_saves_only_rules_with_points(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.point-modes.store'), [
                'name' => 'Halloween',
                'description' => 'Más difícil',
                'rules' => [
                    ['point_rule_id' => $this->ticket->id, 'points' => null],
                    ['point_rule_id' => $this->rare->id, 'points' => 30],
                ],
            ])
            ->assertRedirect(route('admin.point-modes.index'))
            ->assertSessionHasNoErrors();

        $mode = PointMode::where('name', 'Halloween')->firstOrFail();

        $this->assertFalse($mode->is_active);
        $this->assertSame([$this->rare->id => 30], $mode->rules->pluck('pivot.points', 'id')->all());
    }

    public function test_store_validates_input(): void
    {
        $this->mode('Halloween');

        $this->actingAs($this->admin)
            ->post(route('admin.point-modes.store'), [
                'name' => 'Halloween',
                'rules' => [
                    ['point_rule_id' => 999999, 'points' => -1],
                ],
            ])
            ->assertSessionHasErrors(['name', 'rules.0.point_rule_id', 'rules.0.points']);
    }

    public function test_update_replaces_overrides_and_keeps_its_own_name(): void
    {
        $mode = $this->mode('Halloween', [$this->rare->id => 30]);

        $this->actingAs($this->admin)
            ->put(route('admin.point-modes.update', $mode), [
                'name' => 'Halloween',
                'description' => null,
                'rules' => [
                    ['point_rule_id' => $this->ticket->id, 'points' => 0],
                    ['point_rule_id' => $this->rare->id, 'points' => null],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame([$this->ticket->id => 0], $mode->fresh()->rules->pluck('pivot.points', 'id')->all());
    }

    public function test_activating_a_mode_deactivates_the_previous_one(): void
    {
        $first = $this->mode('Normal', active: true);
        $second = $this->mode('Halloween');

        $this->actingAs($this->admin)
            ->post(route('admin.point-modes.activate', $second))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(1, PointMode::where('is_active', true)->count());
    }

    public function test_deactivate_returns_to_base_points(): void
    {
        $this->mode('Halloween', active: true);

        $this->actingAs($this->admin)
            ->post(route('admin.point-modes.deactivate'))
            ->assertRedirect();

        $this->assertNull(PointMode::active());
    }

    public function test_active_mode_cannot_be_deleted(): void
    {
        $active = $this->mode('Halloween', active: true);
        $inactive = $this->mode('Verano');

        $this->actingAs($this->admin)->delete(route('admin.point-modes.destroy', $active));
        $this->actingAs($this->admin)->delete(route('admin.point-modes.destroy', $inactive));

        $this->assertModelExists($active);
        $this->assertModelMissing($inactive);
    }

    public function test_duplicate_copies_overrides_as_an_inactive_mode(): void
    {
        $mode = $this->mode('Halloween', [$this->rare->id => 30], active: true);

        $this->actingAs($this->admin)->post(route('admin.point-modes.duplicate', $mode));
        $this->actingAs($this->admin)->post(route('admin.point-modes.duplicate', $mode));

        $copy = PointMode::where('name', 'Copia de Halloween')->firstOrFail();

        $this->assertFalse($copy->is_active);
        $this->assertSame([$this->rare->id => 30], $copy->rules->pluck('pivot.points', 'id')->all());
        $this->assertTrue(PointMode::where('name', 'Copia de Halloween (2)')->exists());
    }
}
