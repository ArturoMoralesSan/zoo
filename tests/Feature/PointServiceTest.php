<?php

namespace Tests\Feature;

use App\Models\PointMode;
use App\Models\PointRule;
use App\Models\User;
use App\Services\PointService;
use RuntimeException;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class PointServiceTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    protected User $user;

    protected PointRule $ticket;

    protected PointService $points;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['points' => 0]);
        $this->points = app(PointService::class);

        $this->ticket = PointRule::create([
            'type' => 'ticket_purchase',
            'name' => 'Compra de boleto',
            'points' => 10,
            'is_active' => true,
        ]);
    }

    private function activeMode(array $overrides): PointMode
    {
        $mode = PointMode::create(['name' => 'Temporada '.uniqid(), 'is_active' => true]);

        $mode->rules()->sync(
            collect($overrides)->mapWithKeys(fn ($points, $ruleId) => [$ruleId => ['points' => $points]])->all()
        );

        return $mode;
    }

    public function test_without_active_mode_base_points_are_used(): void
    {
        $movement = $this->points->add($this->user, 'ticket_purchase');

        $this->assertSame(10, $movement?->points);
        $this->assertNull($movement?->point_mode_id);
        $this->assertSame(10, $this->user->fresh()->points);
    }

    public function test_active_mode_overrides_the_rule_points(): void
    {
        $mode = $this->activeMode([$this->ticket->id => 4]);

        $movement = $this->points->add($this->user, 'ticket_purchase');

        $this->assertSame(4, $movement?->points);
        $this->assertSame($mode->id, $movement?->point_mode_id);
        $this->assertSame(4, $this->user->fresh()->points);
    }

    public function test_rule_not_changed_by_the_mode_keeps_base_points(): void
    {
        $rare = PointRule::where('type', 'card_capture_rara')->firstOrFail();
        $mode = $this->activeMode([$rare->id => 5]);

        $movement = $this->points->add($this->user, 'ticket_purchase');

        $this->assertSame(10, $movement?->points);
        $this->assertSame($mode->id, $movement?->point_mode_id);
    }

    public function test_mode_with_zero_points_awards_nothing_without_failing(): void
    {
        $this->activeMode([$this->ticket->id => 0]);

        $this->assertNull($this->points->add($this->user, 'ticket_purchase'));
        $this->assertSame(0, (int) $this->user->fresh()->points);
        $this->assertDatabaseCount('point_movements', 0);
    }

    public function test_missing_or_inactive_rule_still_fails(): void
    {
        $this->ticket->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);

        $this->points->add($this->user, 'ticket_purchase');
    }

    public function test_inactive_mode_is_ignored(): void
    {
        $mode = $this->activeMode([$this->ticket->id => 1]);
        $mode->update(['is_active' => false]);

        $this->assertSame(10, $this->points->add($this->user, 'ticket_purchase')?->points);
    }
}
