<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\OptimizeCardImageRequest;
use App\Models\Card;
use App\Services\CardImageOptimizer;
use Illuminate\Http\JsonResponse;

/**
 * Herramienta del panel para convertir a WebP las imágenes ya guardadas.
 */
class CardImageOptimizationController extends Controller
{
    public function __construct(private CardImageOptimizer $optimizer) {}

    /**
     * Imágenes pendientes y si el servidor puede codificarlas.
     */
    public function pending(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'server_encoder' => $this->optimizer->serverEncoder(),
                'profiles' => config('images.cards'),
                'images' => $this->optimizer->pending(),
            ],
        ]);
    }

    /**
     * Reemplazar una imagen por su versión WebP (del navegador o del servidor).
     */
    public function store(
        OptimizeCardImageRequest $request,
        Card $card,
        string $image
    ): JsonResponse {
        $result = $this->optimizer->replace(
            $card,
            $image,
            $request->validated('expected_path'),
            $request->file('file')?->getRealPath() ?: null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
