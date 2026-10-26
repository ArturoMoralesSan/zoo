<?php

namespace App\Services\Duel;

use Carbon\CarbonInterface;

/**
 * Intentos del usuario para obtener una tarjeta en la ronda actual.
 *
 * Una ronda termina al agotar los intentos: la tarjeta se bloquea durante
 * el tiempo de espera y después los intentos se reinician.
 */
final readonly class DuelAttempts
{
    public function __construct(
        public int $max,
        public int $used,
        public int $left,
        public int $cooldownMinutes,
        public ?CarbonInterface $lockedUntil,
    ) {}
}
