<?php

namespace App\Services\Duel;

/**
 * Temporada visible al ganar el duelo, para explicar por qué los puntos
 * son distintos de lo normal.
 */
final readonly class DuelPointsSeason
{
    /**
     * @param  array{accent: string, on_accent: string, soft: string, text: string}|null  $color
     */
    public function __construct(
        public string $title,
        public ?string $message,
        public ?string $icon,
        public ?array $color,
        // Puntos que la tarjeta daría sin temporada; null si su rareza no otorga puntos.
        public ?int $basePoints,
    ) {}
}
