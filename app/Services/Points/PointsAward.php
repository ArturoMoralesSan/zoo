<?php

namespace App\Services\Points;

use App\Models\Level;

/**
 * Puntos que dejó una acción (capturar una tarjeta, comprar boletos, donar),
 * para devolverlos en la respuesta de esa acción.
 */
final readonly class PointsAward
{
    public function __construct(
        public int $awarded,
        public int $total,
        public ?Level $level,
        public bool $levelUp,
        public ?PointsAwardSeason $season = null,
    ) {}
}
