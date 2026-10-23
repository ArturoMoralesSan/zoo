<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardCapture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    /**
     * Obtener todas las tarjetas activas.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $capturedCardIds = $user
            ? CardCapture::where('user_id', $user->id)->pluck('card_id')->flip()->all()
            : [];

        $cards = Card::query()
            ->with(['species.category'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Card $card) use ($capturedCardIds) {
                $isOwned = isset($capturedCardIds[$card->id]);

                return $this->formatCard($card, $isOwned);
            });

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    /**
     * Obtener una tarjeta específica.
     */
    public function show(Request $request, Card $card): JsonResponse
    {
        abort_unless($card->is_active, 404);

        $user = $request->user();

        $isOwned = $user
            ? CardCapture::where('user_id', $user->id)
                ->where('card_id', $card->id)
                ->exists()
            : false;

        /*
         * (API) The cards.show data must only be sent if the user owns the card.
         */
        if (! $isOwned) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a esta tarjeta porque aún no ha sido capturada.',
            ], 403);
        }

        $card->load(['species.category']);

        return response()->json([
            'success' => true,
            'data' => $this->formatCard($card, true),
        ]);
    }

    /**
     * Formatear los datos de la tarjeta para la respuesta API.
     */
    private function formatCard(Card $card, bool $isOwned): array
    {
        $data = [
            'id' => $card->id,
            'species_id' => $card->species_id,
            'name' => $card->name,
            'rarity' => $card->rarity,
            'edition' => $card->edition,
            'description' => $card->description,
            'card_image' => $card->card_image,
            'model_name' => $card->model_name,
            'model_format' => $card->model_format,
            'model_description' => $card->model_description,
            'is_active' => (bool) $card->is_active,
            'sort_order' => (int) $card->sort_order,
            'astucia' => (int) ($card->astucia ?? 50),
            'rapidez' => (int) ($card->rapidez ?? 50),
            'fuerza' => (int) ($card->fuerza ?? 50),
            'questions_per_duel' => (int) ($card->questions_per_duel ?? 3),
            'retry_type' => $card->retry_type ?? 'card',
            'retries' => (int) $card->particular_retries,
            'encountered' => $isOwned,
            'created_at' => $card->created_at,
            'updated_at' => $card->updated_at,
        ];

        /*
         * (API) Enviar model_url y model_file únicamente si el usuario posee la tarjeta.
         */
        if ($isOwned) {
            $data['model_file'] = $card->model_file;
            $data['model_url'] = $card->model_url;
        }

        if ($card->relationLoaded('species') && $card->species) {
            $data['species'] = $card->species;
        }

        return $data;
    }
}
