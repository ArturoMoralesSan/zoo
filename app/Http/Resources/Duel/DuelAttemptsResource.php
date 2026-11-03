<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelAttempts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Intentos del usuario para la tarjeta en la ronda actual.
 *
 * Cada duelo fallado gasta un intento. Al agotarlos la tarjeta se bloquea
 * hasta `locked_until`; al terminar la espera, los intentos vuelven a `max`.
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
             * Intentos por ronda (configurados en la tarjeta o en su rareza).
             *
             * @example 3
             */
            'max' => $this->max,

            /**
             * Intentos que quedan antes del bloqueo. Para "Reintentos: {left} de {max}".
             *
             * @example 2
             */
            'left' => $this->left,

            /**
             * Fecha (ISO 8601) en que termina el bloqueo; `null` si no está bloqueada.
             *
             * @example null
             */
            'locked_until' => $this->lockedUntil?->toJSON(),
        ];
    }
}
