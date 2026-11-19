<?php

namespace Tests\Feature;

use App\Models\PointMode;
use App\Services\Points\PointSeasonStyles;
use Database\Seeders\PointModeSeeder;
use Database\Seeders\PointRuleSeeder;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class PointModeSeederTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    public function test_examples_are_valid_for_the_panel(): void
    {
        foreach (PointModeSeeder::MODES as $mode) {
            $this->assertLessThanOrEqual(80, mb_strlen($mode['public_title']), $mode['name']);
            $this->assertLessThanOrEqual(300, mb_strlen($mode['public_message']), $mode['name']);
            $this->assertContains($mode['icon'], PointSeasonStyles::iconKeys(), $mode['name']);
            $this->assertContains($mode['color'], PointSeasonStyles::colorKeys(), $mode['name']);
        }
    }

    public function test_seeds_six_inactive_visible_seasons_with_their_rules(): void
    {
        $this->seed([PointRuleSeeder::class, PointModeSeeder::class]);

        $this->assertSame(6, PointMode::count());
        $this->assertSame(0, PointMode::where('is_active', true)->count());
        $this->assertSame(6, PointMode::where('is_public', true)->count());

        $experts = PointMode::where('name', 'Reto de expertos')->firstOrFail();

        $this->assertSame('crown', $experts->icon);
        $this->assertSame('morado', $experts->color);
        $this->assertEqualsCanonicalizing(
            ['card_capture_comun' => 0, 'card_capture_epica' => 150, 'card_capture_edicion_especial' => 200],
            $experts->rules->mapWithKeys(fn ($rule) => [$rule->type => $rule->pivot->points])->all()
        );

        $expected = array_sum(array_map(fn ($mode) => count($mode['rules']), PointModeSeeder::MODES));
        $this->assertDatabaseCount('point_mode_rules', $expected);
    }

    public function test_seeding_again_keeps_edited_modes(): void
    {
        $this->seed([PointRuleSeeder::class, PointModeSeeder::class]);

        PointMode::where('name', 'Navidad')->update(['public_title' => 'Editado en el panel']);

        $this->seed(PointModeSeeder::class);

        $this->assertSame(6, PointMode::count());
        $this->assertSame('Editado en el panel', PointMode::where('name', 'Navidad')->value('public_title'));
    }
}
