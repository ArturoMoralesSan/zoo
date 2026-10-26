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
     * Se inicia escaneando el código QR de una tarjeta. Devuelve las preguntas a responder,
     * cada una con sus opciones en `answers` (sin indicar cuál es la correcta).
     *
     * Si el usuario ya tenía un duelo pendiente de esa tarjeta, se reanuda con las mismas
     * preguntas (`time_left_seconds` indica el tiempo que le queda).
     *
     * **Tiempo límite:** hay `time_limit_seconds` para responder. Responder después, o abandonar
     * el duelo, cuenta como un intento fallido.
     *
     * **Errores** (todos con el formato `{ success: false, message, data? }`):
     *
     * | Estado | Cuándo | `data` |
     * |---|---|---|
     * | 404 | El código QR no existe o está desactivado, o la tarjeta no está disponible | — |
     * | 409 | El usuario ya tiene la tarjeta | `card` |
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
     * Envía una respuesta por cada pregunta del duelo (`question_id` y el `answer_id` elegido).
     *
     * **Resultado** (`data.outcome`):
     * - `won` (HTTP 201): todas correctas; la tarjeta se agregó a la colección (`data.card`).
     * - `retry` (HTTP 200): falló, pero le quedan intentos (`data.attempts.left`).
     * - `locked` (HTTP 200): falló y ya no le quedan intentos; la tarjeta queda bloqueada
     *   hasta `data.attempts.locked_until`. Si la rareza lo permite, `data.review` trae las
     *   respuestas correctas para mostrar un repaso.
     *
     * `message` es el mensaje configurado en el panel para ese resultado: el de la tarjeta o,
     * si no tiene uno propio, el mensaje predeterminado. Viene listo para mostrarse.
     * `data.results` indica si cada respuesta elegida fue correcta (sin revelar la correcta).
     *
     * **Errores** (todos con el formato `{ success: false, message, data? }`):
     *
     * | Estado | Cuándo | `data` |
     * |---|---|---|
     * | 403 | El duelo es de otro usuario | — |
     * | 404 | El duelo no existe, o la tarjeta ya no está disponible | — |
     * | 409 | El duelo ya fue respondido | — |
     * | 410 | Se acabó el tiempo; cuenta como intento fallido | `attempts` |
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
             * Mensaje del resultado configurado en el panel (de la tarjeta o el predeterminado), listo para mostrarse.
             */
            'message' => $result->message,
            'data' => new DuelResultResource($result),
        ], $result->passed() ? 201 : 200);
    }
}
