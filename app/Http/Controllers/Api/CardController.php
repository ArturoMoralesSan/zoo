<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Services\CardDuelService;
use App\Services\CardPack\CardPackCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @tags Tarjetas
 */
class CardController extends Controller
{
    /**
     * Listar las tarjetas
     *
     * Todas las tarjetas activas, en el orden configurado en el panel (Mi Deck, MiZoo,
     * Vista 3D). Los conteos ("Obtenidos · 3", "Todos · 24") se calculan en la app.
     *
     * Cada tarjeta viene en una de dos formas, según `encountered`:
     *
     * | `encountered` | Campos | Uso |
     * |---|---|---|
     * | `true` | `id`, `name`, `rarity`, `category_name`, `thumbnail_image`, `card_image_url`, `stats`, `model_url` | Tarjeta obtenida: cuadrícula, Vista 3D y AR |
     * | `false` | `id`, `attempts` | "???" / "Sin descubrir": no se revela el animal hasta ganarlo |
     *
     * `attempts` de una tarjeta sin obtener: con `locked_until` en `null` se puede retar
     * (le quedan `left` de `max` intentos); con fecha, está bloqueada hasta entonces.
     *
     * La respuesta lleva `ETag`: con `If-None-Match` el servidor responde 304 si nada cambió.
     *
     * @response array{success: true, data: array<int, CardResource>}
     */
    public function index(Request $request, CardDuelService $duelService): JsonResponse
    {
        $quizStates = $request->user()
            ->cardQuizStates()
            ->get()
            ->keyBy('card_id');

        $ownedIds = $request->user()
            ->cardCaptures()
            ->pluck('card_id')
            ->flip();

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
                'duel_settings_source',
                'retries',
                'cooldown_minutes',
            ])
            ->with([
                'species:id,species_category_id',
                'species.category:id,name',
                'raritySetting',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Card $card) => new CardResource(
                $card,
                $ownedIds->has($card->id),
                $ownedIds->has($card->id)
                    ? null
                    : $duelService->attemptsFor($card, $quizStates->get($card->id))
            ));

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }

    /**
     * Catálogo del paquete sin conexión
     *
     * Archivos cifrados (AES-256-GCM) de **todas** las tarjetas activas, para que la app
     * los descargue en Wi-Fi antes de la visita: una entrada por archivo (`model`,
     * `card_image`, `thumbnail`), cada una con su versión (`id`), `url`, `bytes` y `sha256`.
     * Es igual para todos los usuarios y **nunca trae llaves**: la llave de cada archivo
     * llega en `pack` de `GET /cards` (tarjetas obtenidas) y del inicio del duelo.
     *
     * Si cambia un archivo en el panel, su entrada cambia de `id` y la app baja solo ese
     * archivo. Solo aparecen los archivos ya cifrados; los demás se piden por su URL.
     *
     * La respuesta lleva `ETag` y `Last-Modified`: con `If-None-Match` el servidor responde
     * 304 si nada cambió. Ver docs/offline-packs-backend.md.
     *
     * @response array{success: true, data: array{generated_at: string, total_bytes: int, files: list<array{id: string, card_id: int, kind: 'model'|'card_image'|'thumbnail', url: string, bytes: int, sha256: string}>}}
     */
    public function pack(Request $request, CardPackCatalog $catalog): JsonResponse
    {
        $pack = $catalog->get();

        $response = response()->json([
            'success' => true,
            'data' => [
                'generated_at' => $pack['generated_at'],
                'total_bytes' => $pack['total_bytes'],
                'files' => $pack['files'],
            ],
        ]);

        $response
            ->setEtag($pack['etag'])
            ->setLastModified(Carbon::parse($pack['generated_at']))
            ->setPrivate();

        $response->isNotModified($request);

        return $response;
    }
}
