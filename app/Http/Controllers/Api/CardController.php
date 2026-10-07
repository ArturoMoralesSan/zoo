<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;

class CardController extends Controller
{
    /**
     * Obtener todas las tarjetas activas.
     */
    public function index(): JsonResponse
    {
        $cards = Card::query()
            ->with('species')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    /**
     * Obtener una tarjeta específica.
     */
    public function show(Card $card): JsonResponse
    {
        abort_unless($card->is_active, 404);

        $card->load('species');

        return response()->json([
            'success' => true,
            'data' => $card,
        ]);
    }
}