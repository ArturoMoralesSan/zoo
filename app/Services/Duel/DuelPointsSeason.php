<?php

namespace App\Services\Duel;

/**
 * Temporada visible al ganar el duelo, para explicar por qué los puntos
 * son distintos de lo normal.
 */
final readonly class DuelPointsSeason
{
    public function __construct(
        public string $title,
        public ?string $message,
        // Puntos que la tarjeta daría sin temporada; null si su rareza no otorga puntos.
        public ?int $basePoints,
    ) {}
}
