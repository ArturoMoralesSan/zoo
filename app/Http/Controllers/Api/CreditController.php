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
     * Créditos de las imágenes y modelos 3D de las tarjetas activas y de las
     * imágenes de las especies activas (principal, miniatura y galería), en
     * secciones. Público (sin token) e igual para todos los usuarios.
     *
     * Cada sección tiene `type`: `card` (con `card`) o `species` (con
     * `species`; cada crédito trae además `image`, la foto a la que
     * pertenece). Primero van las tarjetas y después las especies.
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
