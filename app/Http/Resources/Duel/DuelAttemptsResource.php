<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelAttempts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Intentos del usuario para la tarjeta en la ronda actual.
 *
 * Al agotar los intentos la tarjeta se bloquea `cooldown_minutes` minutos;
 * al terminar la espera, los intentos se reinician.
 *
 * @mixin DuelAttempts
 */
class DuelAttemptsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Intentos por ronda.
             *
             * @example 3
             */
            'max' => $this->max,

            /**
             * Intentos fallidos en la ronda actual.
             *
             * @example 1
             */
            'used' => $this->used,

            /**
             * Intentos que quedan antes del bloqueo.
             *
             * @example 2
             */
            'left' => $this->left,

            /**
             * Minutos de bloqueo al agotar los intentos.
             *
             * @example 1440
             */
            'cooldown_minutes' => $this->cooldownMinutes,

            /**
             * Fecha (ISO 8601) en que termina el bloqueo; `null` si no está bloqueada.
             *
             * @example null
             */
            'locked_until' => $this->lockedUntil?->toJSON(),
        ];
    }
}
