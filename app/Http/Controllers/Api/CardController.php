<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    /**
     * Obtener todas las tarjetas activas para el hub.
     *
     * Cada tarjeta indica si el usuario la posee; el modelo 3D
     * solo se incluye para las tarjetas capturadas.
     */
    public function index(Request $request): JsonResponse
    {
        $capturedAt = $request->user()
            ->cardCaptures()
            ->pluck('captured_at', 'card_id');

        $cards = Card::query()
            ->select([
                'id',
                'species_id',
                'name',
                'rarity',
                'thumbnail_image',
                'generated_image',
                'model_file',
                'sort_order',
                'astucia',
                'rapidez',
                'fuerza',
            ])
            ->with([
                'species:id,common_name,species_category_id',
                'species.category:id,name',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Card $card) => new CardResource(
                $card,
                $capturedAt->get($card->id)
            ));

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }
}
