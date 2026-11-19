<?php

namespace App\Http\Resources\Points;

use App\Services\Points\PointSeasonCardChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cambio en los puntos por capturar una tarjeta de cierta rareza.
 *
 * Hay una entrada por cada rareza cuyos puntos de captura cambian en la temporada.
 * Una rareza que no aparece da sus puntos normales al capturar.
 *
 * @mixin PointSeasonCardChange
 */
class PointSeasonCardChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Rareza de la tarjeta, igual que `card.rarity` en el resto de la API.
             *
             * @var 'comun'|'rara'|'epica'|'edicion_especial'
             *
             * @example epica
             */
            'rarity' => $this->rarity->value,

            /**
             * Puntos normales por capturar una tarjeta de esta rareza (sin temporada).
             *
             * @example 75
             */
            'base_points' => $this->basePoints,

            /**
             * Puntos por capturarla durante la temporada. `0` = la captura no da puntos
             * mientras dure la temporada (la tarjeta se obtiene igual).
             *
             * @example 150
             */
            'points' => $this->points,
        ];
    }
}
