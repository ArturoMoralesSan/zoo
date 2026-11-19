<?php

namespace App\Http\Resources\Points;

use App\Services\Points\PointSeason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Temporada de puntos activa y lo que cambia.
 *
 * @mixin PointSeason
 */
class PointSeasonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Título de la temporada, escrito por el zoológico para los visitantes.
             *
             * @example ¡Temporada de Halloween!
             */
            'title' => $this->title,

            /**
             * Mensaje corto para los visitantes; `null` si no hay.
             *
             * @example Las cartas épicas dan el doble de puntos hasta el 2 de noviembre.
             */
            'message' => $this->message,

            /**
             * Puntos por capturar tarjetas que cambian, una entrada por rareza, en orden de
             * rareza (común, rara, épica, edición especial). Vacío si la temporada no cambia
             * ninguna captura.
             *
             * @var array<int, PointSeasonCardChangeResource>
             */
            'cards' => PointSeasonCardChangeResource::collection($this->cards),

            /**
             * Otras acciones cuyos puntos cambian (boletos, donaciones, eventos…), ordenadas
             * por nombre. Vacío si la temporada solo cambia capturas.
             *
             * @var array<int, PointSeasonRuleChangeResource>
             */
            'other' => PointSeasonRuleChangeResource::collection($this->other),
        ];
    }
}
