<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardCaptureController extends Controller
{
    /**
     * Obtener todas las tarjetas capturadas
     * por el usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $captures = CardCapture::query()
            ->with([
                'card.species',
            ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('captured_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $captures,
        ]);
    }

    /**
     * Obtener una tarjeta específica
     * de la colección del usuario.
     */
    public function show(
        Request $request,
        Card $card
    ): JsonResponse {
        $capture = CardCapture::query()
            ->with([
                'card.species',
            ])
            ->where('user_id', $request->user()->id)
            ->where('card_id', $card->id)
            ->first();

        if (!$capture) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta aún no ha sido capturada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $capture,
        ]);
    }

    /**
     * Registrar una tarjeta como capturada.
     */
    public function store(
        Request $request,
        Card $card
    ): JsonResponse {
        if (!$card->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta no está disponible.',
            ], 404);
        }

        $validated = $request->validate([
            'capture_image' => [
                'nullable',
                'string',
                'max:2048',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ]);

        $user = $request->user();

        /*
         * Verificar si el usuario ya tiene
         * esta tarjeta.
         */
        $existingCapture = CardCapture::query()
            ->where('user_id', $user->id)
            ->where('card_id', $card->id)
            ->first();

        if ($existingCapture) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta ya fue capturada.',
                'data' => $existingCapture->load([
                    'card.species',
                ]),
            ], 409);
        }

        /*
         * Registrar la captura.
         */
        $capture = CardCapture::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'captured_at' => now(),
            'capture_image' => $validated['capture_image'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        $capture->load([
            'card.species',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarjeta capturada correctamente.',
            'data' => $capture,
        ], 201);
    }
}