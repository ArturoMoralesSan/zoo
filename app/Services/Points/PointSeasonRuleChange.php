<?php

namespace App\Services\Points;

/**
 * Regla que no es de captura de tarjetas (boletos, donaciones, eventos…) cuyos
 * puntos cambian durante la temporada.
 */
final readonly class PointSeasonRuleChange
{
    public function __construct(
        public string $type,
        public string $name,
        public int $basePoints,
        public int $points,
    ) {}
}
