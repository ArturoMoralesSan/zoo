<?php

namespace App\Http\Resources\Points;

use App\Services\Points\PointsAward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Puntos que dejó una acción: capturar una tarjeta (duelo), comprar boletos o donar.
 *
 * Mismo formato en todas las acciones. Los puntos salen de la regla de la acción
 * en el panel ("Reglas de puntos" y, para las tarjetas, "Configuración por rareza");
 * la app nunca debe calcularlos.
 *
 * @mixin PointsAward
 */
class PointsAwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Puntos otorgados por esta acción. `0` si la acción no da puntos: regla desactivada,
             * puesta en 0 por la temporada, rareza sin puntos o donación bajo el monto mínimo.
             *
             * @example 50
             */
            'awarded' => $this->awarded,

            /**
             * Total de puntos acumulados del usuario después de esta acción.
             *
             * @example 320
             */
            'total' => $this->total,

            /**
             * Nivel del usuario después de esta acción; `null` si no hay niveles configurados.
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
             * `true` si esta acción subió al usuario de nivel.
             *
             * @example false
             */
            'level_up' => $this->levelUp,

            /**
             * Temporada (modo de puntos visible) activa al otorgar los puntos, para explicar en
             * el aviso por qué son distintos. Por ejemplo:
             * "Por la temporada `title`, esto da `awarded` puntos en lugar de `base_points`",
             * seguido de `message`.
             *
             * `null` si no hay modo activo o si el modo está oculto a los visitantes. Un modo
             * oculto igual cambia los puntos: `awarded` siempre trae los puntos reales.
             *
             * - `title`: nombre público de la temporada.
             * - `message`: texto del zoológico para el aviso; `null` si no escribió uno.
             * - `base_points`: puntos normales (sin temporada) de la regla de esta acción (en el
             *   duelo, la de la rareza de la tarjeta). Compáralo con `awarded`: menor = la
             *   temporada los bajó, mayor = los subió, igual = la temporada no cambia esta acción
             *   (no expliques nada). Si la temporada la pone en 0, `awarded` es `0` y
             *   `base_points` trae el valor normal. `null` si la acción no da puntos (rareza
             *   "Sin puntos", regla desactivada o donación bajo el monto mínimo).
             * - `icon` y `color`: los mismos que en `GET /points/season`; `null` = usa el ícono o
             *   color predeterminado de la app.
             *
             * @var array{title: string, message: string|null, base_points: int|null, icon: string|null, color: array{accent: string, on_accent: string, soft: string, text: string}|null}|null
             */
            'season' => $this->season
                ? [
                    'title' => $this->season->title,
                    'message' => $this->season->message,
                    'base_points' => $this->season->basePoints,
                    'icon' => $this->season->icon,
                    'color' => $this->season->color,
                ]
                : null,
        ];
    }
}
