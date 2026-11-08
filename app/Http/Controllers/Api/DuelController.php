<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StartDuelRequest;
use App\Http\Requests\Api\SubmitDuelRequest;
use App\Http\Resources\Duel\DuelResultResource;
use App\Http\Resources\Duel\DuelStartResource;
use App\Models\QuizAttempt;
use App\Services\CardDuelService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Duelos
 */
class DuelController extends Controller
{
    public function __construct(
        private CardDuelService $duelService
    ) {}

    /**
     * Iniciar (o reanudar) un duelo
     *
     * Se inicia escaneando el código QR del recinto (A-18). Devuelve el animal que reta
     * al usuario, sus intentos y las preguntas a responder, cada una con sus opciones en
     * `answers` (sin indicar cuál es la correcta).
     *
     * **Flujo en la app:**
     *
     * | Pantalla | Qué hace |
     * |---|---|
     * | A-18 Escanear QR | Envía el `code` leído a este endpoint. |
     * | A-19 Reto | Muestra `card`. "Rechazar" no envía nada ni gasta un intento: el duelo queda pendiente. |
     * | A-20 Batalla | Muestra `card` y "Tienes `attempts.left` intentos". |
     * | A-21 Quiz | Muestra las `questions` (una a una, si se quiere) y envía todas juntas a `POST /duels/{attempt}/submit`. |
     *
     * **Sin tiempo límite:** un duelo queda pendiente hasta que se responde. Volver a
     * escanear el QR reanuda el mismo duelo, con las mismas preguntas (las opciones se
     * vuelven a mezclar en cada llamada). Solo cuenta como intento fallido responder mal.
     *
     * **Preguntas:** el número de preguntas por duelo se configura en la tarjeta o en su
     * rareza. La tarjeta se gana solo si **todas** las respuestas son correctas.
     *
     * **Entre intentos las preguntas cambian:** cada intento nuevo elige al azar otras
     * preguntas entre las activas de la tarjeta (pueden repetirse si hay pocas) y mezcla
     * sus opciones. Para reintentar ("Reintentar" en A-23) se vuelve a llamar a este
     * endpoint con el mismo `code` escaneado; "Rendirte" solo sale del duelo (el fallo
     * ya se contó al responder).
     *
     * **Bloqueo:** al agotar los intentos la tarjeta queda bloqueada hasta
     * `attempts.locked_until` (423). Al terminar la espera, los intentos vuelven a `max`.
     *
     * **Errores** (todos con el formato `{ success: false, message, data? }`):
     *
     * | Estado | Cuándo | `data` |
     * |---|---|---|
     * | 404 | El código QR no existe o está desactivado, o la tarjeta no está disponible | — |
     * | 409 | El usuario ya tiene la tarjeta | `card` (tarjeta obtenida, como en `GET /cards`) |
     * | 410 | El código QR venció | — |
     * | 422 | La tarjeta aún no tiene preguntas | — |
     * | 423 | Agotó sus intentos; la tarjeta está bloqueada | `attempts` (con `locked_until`) |
     */
    public function store(StartDuelRequest $request): JsonResponse
    {
        $duel = $this->duelService->startFromQr(
            $request->user(),
            $request->validated('code')
        );

        return response()->json([
            'success' => true,
            'data' => new DuelStartResource($duel),
        ]);
    }

    /**
     * Responder un duelo
     *
     * Envía una respuesta por cada pregunta del duelo (`question_id` y el `answer_id` elegido),
     * todas en una sola petición. El duelo no tiene tiempo límite.
     *
     * **Resultado** (`data.outcome`):
     *
     * | `outcome` | HTTP | Cuándo | Campos con valor |
     * |---|---|---|---|
     * | `won` | 201 | Todas correctas; la tarjeta se agregó a la colección (A-22) | `results`, `card` |
     * | `retry` | 200 | Falló y le quedan intentos (A-23) | `results`, `attempts` |
     * | `locked` | 200 | Falló y ya no le quedan intentos; bloqueada hasta `attempts.locked_until` | `results`, `attempts`, `review`* |
     *
     * Los campos sin valor vienen en `null`.
     *
     * **Mensaje:** `message` es el texto del resultado configurado en el panel: el de la
     * tarjeta (si usa mensajes personalizados) o el predeterminado. Los marcadores ya vienen
     * reemplazados; se muestra tal cual en el modal del resultado.
     *
     * **Respuestas:** `results` solo indica si cada opción elegida fue correcta, para marcar
     * la equivocada; no revela cuál era la correcta.
     *
     * **Repaso final** (*): solo cuando `outcome` es `locked` y la rareza de la tarjeta tiene
     * activado "mostrar respuestas". Incluye únicamente las preguntas de este último duelo
     * (las de intentos anteriores pueden haber sido otras), cada una con su
     * `correct_answer_id`. Los textos son los que llegaron en `POST /duels`.
     *
     * Para reintentar se vuelve a llamar a `POST /duels` con el mismo código (traerá
     * preguntas nuevas).
     *
     * **Errores** (todos con el formato `{ success: false, message, data? }`):
     *
     * | Estado | Cuándo | `data` |
     * |---|---|---|
     * | 403 | El duelo es de otro usuario | — |
     * | 404 | El duelo no existe, o la tarjeta ya no está disponible | — |
     * | 409 | El duelo ya fue respondido | — |
     * | 422 | Las respuestas no corresponden a las preguntas del duelo | — |
     *
     * @response array{success: true, message: string, data: DuelResultResource}
     */
    public function submit(
        SubmitDuelRequest $request,
        QuizAttempt $attempt
    ): JsonResponse {
        $result = $this->duelService->submit(
            $request->user(),
            $attempt,
            $request->validated('answers'),
            $request->validated('latitude'),
            $request->validated('longitude')
        );

        return response()->json([
            'success' => true,
            /**
             * Mensaje del resultado configurado en el panel (de la tarjeta o el predeterminado), con los marcadores reemplazados, listo para mostrarse.
             */
            'message' => $result->message,
            'data' => new DuelResultResource($result),
        ], $result->passed() ? 201 : 200);
    }
}
