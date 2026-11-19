<?php

namespace App\Http\Resources\Points;

use App\Services\Points\PointSeasonRuleChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cambio en los puntos de una acción que no es capturar tarjetas
 * (comprar boletos, donar, asistir a eventos…).
 *
 * @mixin PointSeasonRuleChange
 */
class PointSeasonRuleChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Identificador de la regla de puntos. Sirve como clave estable (por ejemplo,
             * para `key` en una lista), pero no para decidir textos o íconos: el zoológico
             * puede crear reglas nuevas en cualquier momento.
             *
             * @example ticket_purchase
             */
            'type' => $this->type,

            /**
             * Nombre de la acción, escrito por el zoológico; se muestra tal cual.
             *
             * @example Compra de boleto
             */
            'name' => $this->name,

            /**
             * Puntos normales de la acción (sin temporada).
             *
             * @example 10
             */
            'base_points' => $this->basePoints,

            /**
             * Puntos durante la temporada. `0` = la acción no da puntos mientras dure.
             *
             * @example 20
             */
            'points' => $this->points,
        ];
    }
}
