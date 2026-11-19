<?php

namespace App\Services\Duel;

use App\Models\Level;

/**
 * Puntos que dejó ganar un duelo (capturar la tarjeta).
 */
final readonly class DuelPoints
{
    public function __construct(
        public int $awarded,
        public int $total,
        public ?Level $level,
        public bool $levelUp,
        public ?DuelPointsSeason $season = null,
    ) {}
}
