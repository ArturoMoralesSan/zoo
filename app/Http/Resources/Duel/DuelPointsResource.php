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
             * Temporada (modo de puntos visible) activa al ganar, para explicar en el aviso
             * de victoria por qué los puntos son distintos. Por ejemplo:
             * "Por la temporada `title`, esta carta da `awarded` puntos en lugar de `base_points`",
             * seguido de `message`.
             *
             * `null` si no hay modo activo o si el modo está oculto a los visitantes. Un modo
             * oculto igual cambia los puntos: `awarded` siempre trae los puntos reales.
             *
             * - `title`: nombre público de la temporada.
             * - `message`: texto del zoológico para el aviso de victoria; `null` si no escribió uno.
             * - `base_points`: puntos normales (sin temporada) de la regla de esta rareza.
             *   Compáralo con `awarded`: menor = la temporada los bajó, mayor = los subió, igual =
             *   la temporada no cambia esta rareza (no expliques nada). Si la temporada pone la
             *   rareza en 0, `awarded` es `0` y `base_points` trae el valor normal. `null` si la
             *   rareza no da puntos ("Sin puntos" o regla desactivada).
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
