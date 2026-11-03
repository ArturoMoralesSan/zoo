<?php

namespace Tests\Unit\Enums;

use App\Enums\CardRarity;
use PHPUnit\Framework\TestCase;

class CardRarityTest extends TestCase
{
    public function test_default_duel_settings_per_rarity(): void
    {
        $this->assertSame(
            [
                'comun' => ['questions_per_duel' => 2, 'retries' => 3, 'cooldown_minutes' => 5],
                'rara' => ['questions_per_duel' => 3, 'retries' => 3, 'cooldown_minutes' => 10],
                'epica' => ['questions_per_duel' => 3, 'retries' => 2, 'cooldown_minutes' => 15],
                'edicion_especial' => ['questions_per_duel' => 4, 'retries' => 1, 'cooldown_minutes' => 30],
            ],
            collect(CardRarity::cases())
                ->mapWithKeys(fn (CardRarity $rarity) => [$rarity->value => $rarity->defaultDuelSettings()])
                ->all()
        );
    }
}
