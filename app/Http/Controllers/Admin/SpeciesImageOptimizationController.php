<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Species\OptimizeSpeciesImageRequest;
use App\Models\SpeciesImage;
use App\Services\SpeciesImageOptimizer;
use Illuminate\Http\JsonResponse;

/**
 * Herramienta del panel para convertir a WebP las imágenes de especies ya
 * guardadas. Misma forma que CardImageOptimizationController.
 */
class SpeciesImageOptimizationController extends Controller
{
    public function __construct(private SpeciesImageOptimizer $optimizer) {}

    /**
     * Imágenes pendientes y si el servidor puede codificarlas.
     */
    public function pending(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'server_encoder' => $this->optimizer->serverEncoder(),
                'profiles' => config('images.species'),
                'images' => $this->optimizer->pending(),
            ],
        ]);
    }

    /**
     * Reemplazar una imagen por su versión WebP (del navegador o del servidor).
     */
    public function store(
        OptimizeSpeciesImageRequest $request,
        SpeciesImage $image
    ): JsonResponse {
        $result = $this->optimizer->replace(
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
