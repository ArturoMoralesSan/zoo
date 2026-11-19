<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Points\PointSeasonResource;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Puntos
 */
class PointSeasonController extends Controller
{
    /**
     * Temporada de puntos activa
     *
     * Durante una temporada o evento, el zoológico puede cambiar cuántos puntos da cada
     * acción: por ejemplo, la mitad por capturar tarjetas en una temporada difícil, o el
     * doble por comprar boletos un fin de semana. Este endpoint dice si hay una temporada
     * y qué cambia, para avisarlo en la app **antes** de que el visitante juegue o compre.
     *
     * **Cuándo `data` es `null`:** no hay temporada activa, o el zoológico la activó sin
     * mostrarla a los visitantes. En ambos casos no se muestra ningún aviso. Una temporada
     * oculta igual cambia los puntos: los reales siempre llegan en la respuesta de cada
     * acción (por ejemplo, `data.points` al ganar un duelo).
     *
     * **Qué cambia, en dos listas:**
     *
     * | Lista | Qué contiene | Cómo mostrarla |
     * |---|---|---|
     * | `cards` | Una entrada por **rareza** cuyos puntos de captura cambian, en orden de rareza. | "Cartas épicas: ~~75~~ 150 pts". Usa `rarity_label` o tus propios nombres por `rarity`. |
     * | `other` | Las demás **acciones** que cambian: boletos, donaciones, eventos… | "Compra de boleto: ~~10~~ 20 pts". Usa `name` tal cual. |
     *
     * - Lo que no aparece en ninguna lista da sus puntos normales.
     * - `points: 0` significa que esa acción no da puntos durante la temporada (la tarjeta
     *   se captura igual).
     * - Ambas listas pueden venir vacías aunque haya temporada (solo un aviso).
     * - No uses `other[].type` para elegir textos o íconos: el zoológico puede crear acciones
     *   nuevas en cualquier momento. Muestra `name`.
     * - Las acciones de `cards` no se repiten en `other`. Si el zoológico usa la misma regla
     *   para una rareza y para otra acción, aparece solo en `cards`.
     *
     * **Estilo:** `icon` es una clave del catálogo de íconos (la app la traduce a su ícono) y
     * `color` trae los tonos listos para usar en el aviso. Cualquiera de los dos puede ser
     * `null`: entonces la app usa su ícono o color predeterminado. Una clave de ícono que
     * la app no conozca también debe mostrarse con el predeterminado.
     *
     * **No calcules puntos con esto.** Es para avisar y explicar; los puntos que se ganan
     * siempre los decide el servidor en la respuesta de la acción.
     *
     * La temporada cambia pocas veces y a mano: basta con pedirla al abrir la app o al
     * volver a primer plano.
     *
     * @response array{success: true, data: PointSeasonResource|null}
     */
    public function show(PointService $points): JsonResponse
    {
        $season = $points->visibleSeason();

        return response()->json([
            'success' => true,
            'data' => $season ? new PointSeasonResource($season) : null,
        ]);
    }
}
