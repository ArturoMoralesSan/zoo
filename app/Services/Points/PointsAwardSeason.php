<?php

namespace App\Services\Points;

/**
 * Temporada visible al otorgar los puntos, para explicar por qué son
 * distintos de lo normal.
 */
final readonly class PointsAwardSeason
{
    /**
     * @param  array{accent: string, on_accent: string, soft: string, text: string}|null  $color
     */
    public function __construct(
        public string $title,
        public ?string $message,
        public ?string $icon,
        public ?array $color,
        // Puntos que la acción daría sin temporada; null si no otorga puntos.
        public ?int $basePoints,
    ) {}
}
