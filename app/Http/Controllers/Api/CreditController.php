<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Licensing\CreditsCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CreditController extends Controller
{
    /**
     * Créditos de las imágenes y modelos 3D de las tarjetas activas, agrupados
     * por tarjeta. Público (sin token) e igual para todos los usuarios.
     *
     * El contenido propio o con licencia privada y los créditos ocultos nunca
     * se envían. Responde 304 si el ETag o la fecha no cambiaron.
     * Ver docs/api-credits.md.
     */
    public function index(Request $request, CreditsCatalog $catalog): JsonResponse
    {
        $credits = $catalog->get();

        $response = response()->json([
            'success' => true,
            'data' => $credits['data'],
            'meta' => $credits['meta'],
        ]);

        $response
            ->setEtag($credits['etag'])
            ->setLastModified(Carbon::parse($credits['meta']['generated_at']))
            ->setPublic()
            ->setMaxAge((int) config('licensing.api.max_age'));

        $response->isNotModified($request);

        return $response;
    }
}
