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
             * Ícono de la temporada: una clave del catálogo (por ejemplo `pumpkin`, `pine-tree`,
             * `skull`, `panda`), no un nombre de archivo ni de componente. La app la traduce a
             * su ícono de Hugeicons con la tabla de la guía. `null` = ícono predeterminado de
             * la app. Si la app no reconoce la clave (porque es nueva), también debe usar el
             * predeterminado: se pueden agregar íconos sin publicar otra versión de la app.
             *
             * @example pumpkin
             */
            'icon' => $this->icon,

            /**
             * Color de la temporada, con cuatro tonos ya probados para que el texto se lea
             * bien (contraste de al menos 4.5:1). `null` = colores predeterminados de la app.
             *
             * - `key`: nombre del color (por ejemplo `calabaza`), por si la app tiene el suyo.
             * - `soft` + `text`: fondo y texto del aviso.
             * - `accent` + `on_accent`: círculo del ícono (o botón) y el ícono encima.
             *
             * @var array{key: string, accent: string, on_accent: string, soft: string, text: string}|null
             *
             * @example {"key": "calabaza", "accent": "#B24E0A", "on_accent": "#FFFFFF", "soft": "#FDEBDC", "text": "#6B2E05"}
             */
            'color' => $this->color,

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
