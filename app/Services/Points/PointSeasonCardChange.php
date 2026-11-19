<?php

namespace App\Services\Points;

use App\Enums\CardRarity;

/**
 * Cambio en los puntos por capturar una tarjeta de una rareza durante la temporada.
 */
final readonly class PointSeasonCardChange
{
    public function __construct(
        public CardRarity $rarity,
        public int $basePoints,
        public int $points,
    ) {}
}
