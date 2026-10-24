<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StartDuelRequest;
use App\Http\Requests\Api\SubmitDuelRequest;
use App\Models\QuizAttempt;
use App\Services\CardDuelService;
use Illuminate\Http\JsonResponse;

class DuelController extends Controller
{
    public function __construct(
        private CardDuelService $duelService
    ) {}

    /**
     * Iniciar (o reanudar) un duelo escaneando el código QR de una tarjeta.
     */
    public function store(StartDuelRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->duelService->startFromQr(
                $request->user(),
                $request->validated('code')
            ),
        ]);
    }

    /**
     * Responder un duelo.
     *
     * Si todas las respuestas son correctas, la tarjeta se captura.
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
            'message' => $result['passed']
                ? '¡Ganaste el duelo! La tarjeta fue agregada a tu colección.'
                : 'No respondiste todas las preguntas correctamente.',
            'data' => $result,
        ], $result['passed'] ? 201 : 200);
    }
}
