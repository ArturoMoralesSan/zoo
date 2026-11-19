<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelPoints;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Puntos obtenidos al capturar la tarjeta.
 *
 * Los puntos salen de la regla elegida para la rareza de la tarjeta en
 * "Configuración por rareza" del panel; la app nunca debe calcularlos.
 *
 * @mixin DuelPoints
 */
class DuelPointsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Puntos otorgados por esta captura. `0` si la rareza no otorga puntos o su regla está desactivada.
             *
             * @example 50
             */
            'awarded' => $this->awarded,

            /**
             * Total de puntos acumulados del usuario después de esta captura.
             *
             * @example 320
             */
            'total' => $this->total,

            /**
             * Nivel del usuario después de esta captura; `null` si no hay niveles configurados.
             *
             * @var array{id: int, name: string}|null
             */
            'level' => $this->level
                ? [
                    'id' => $this->level->id,
                    'name' => $this->level->name,
                ]
                : null,

            /**
             * `true` si esta captura subió al usuario de nivel.
             *
             * @example false
             */
            'level_up' => $this->levelUp,

            /**
             * Temporada (modo de puntos visible) activa al ganar, para explicar por qué los
             * puntos son distintos; `null` si no hay temporada. Campo nuevo y opcional:
             * las versiones de la app que no lo conocen lo ignoran.
             *
             * @var array{title: string, message: string|null, base_points: int|null}|null
             */
            'season' => $this->season
                ? [
                    'title' => $this->season->title,
                    'message' => $this->season->message,
                    'base_points' => $this->season->basePoints,
                ]
                : null,
        ];
    }
}
