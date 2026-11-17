<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ZooDeckMapRequest;
use App\Http\Resources\Duel\DuelAttemptsResource;
use App\Models\Card;
use App\Models\CardQrCode;
use App\Models\CardQuizState;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\SpeciesImage;
use App\Models\SpeciesLocation;
use App\Models\User;
use App\Models\ZooZone;
use App\Services\CardDuelService;
use App\Services\ZooDeck\VisitValidator;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * @tags Mapa
 */
class MapController extends Controller
{
    public function __construct(
        private VisitValidator $visits,
        private CardDuelService $duels
    ) {}

    /**
     * Mapa del zoológico
     *
     * Zonas activas con sus marcadores, ubicaciones de especies (con miniatura) y caminos.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,

            'data' => [
                'zones' => $this->mapZones(),
            ],
        ]);
    }

    /**
     * Zonas activas con marcadores, ubicaciones de especies y caminos.
     *
     * Es la forma de `GET /map`; el mapa de ZooDeck la reutiliza para que la app
     * dibuje ambos con el mismo código. `$prepareLocations` filtra o completa las
     * ubicaciones antes de agruparlas por zona.
     *
     * @param  (Closure(EloquentCollection<int, SpeciesLocation>): EloquentCollection<int, SpeciesLocation>)|null  $prepareLocations
     * @return Collection<int, array<string, mixed>>
     */
    private function mapZones(?Closure $prepareLocations = null): Collection
    {
        $zones = ZooZone::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'type',
                'geometry',
                'map_image',
                'map_image_bounds',
            ]);

        $zoneIds = $zones->pluck('id');

        $markers = MapMarker::query()
            ->whereIn('zone_id', $zoneIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'zone_id',
                'name',
                'description',
                'type',
                'latitude',
                'longitude',
                'icon',
                'color',
            ]);

        $speciesLocations = SpeciesLocation::query()
            ->with([
                'species:id,common_name,scientific_name,description',
            ])
            ->whereIn('zone_id', $zoneIds)
            ->where('is_active', true)
            ->get([
                'id',
                'species_id',
                'zone_id',
                'name',
                'latitude',
                'longitude',
                'description',
                'is_active',
            ]);

        $speciesImages = SpeciesImage::query()
            ->whereIn('species_id', $speciesLocations->pluck('species_id')->filter()->unique())
            ->where('type', 'thumbnail')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id',
                'species_id',
                'type',
                'path',
                'alt_text',
                'sort_order',
            ])
            ->groupBy('species_id');

        $speciesLocations->each(function (SpeciesLocation $location) use ($speciesImages) {
            $location->species?->setRelation(
                'images',
                $speciesImages->get($location->species_id, collect())->values()
            );
        });

        if ($prepareLocations) {
            $speciesLocations = $prepareLocations($speciesLocations);
        }

        $paths = MapPath::query()
            ->whereIn('zone_id', $zoneIds)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get([
                'id',
                'zone_id',
                'name',
                'description',
                'coordinates',
                'distance',
                'estimated_time',
                'is_active',
                'order',
            ]);

        return $zones->map(fn (ZooZone $zone) => [
            'id' => $zone->id,
            'name' => $zone->name,
            'description' => $zone->description,
            'type' => $zone->type,
            'geometry' => $zone->geometry,
            'map_image' => $zone->map_image,
            'map_image_bounds' => $zone->map_image_bounds,

            'markers' => $markers
                ->where('zone_id', $zone->id)
                ->values(),

            'species_locations' => $speciesLocations
                ->where('zone_id', $zone->id)
                ->values(),

            'paths' => $paths
                ->where('zone_id', $zone->id)
                ->values(),
        ])->values();
    }

    /**
     * Obtener una zona específica con toda
     * la información necesaria para navegación.
     */
    public function zone(ZooZone $zooZone): JsonResponse
    {
        if (! $zooZone->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'La zona no está disponible.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Marcadores
        |--------------------------------------------------------------------------
        */

        $markers = MapMarker::query()
            ->where('zone_id', $zooZone->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'zone_id',
                'name',
                'description',
                'type',
                'latitude',
                'longitude',
                'icon',
                'color',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Ubicaciones de especies
        |--------------------------------------------------------------------------
        */

        $speciesLocations = SpeciesLocation::query()
            ->with([
                'species:id,common_name,scientific_name,description',
            ])
            ->where('zone_id', $zooZone->id)
            ->where('is_active', true)
            ->get([
                'id',
                'species_id',
                'zone_id',
                'name',
                'latitude',
                'longitude',
                'description',
                'is_active',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Thumbnails de especies
        |--------------------------------------------------------------------------
        */

        $speciesIds = $speciesLocations
            ->pluck('species_id')
            ->filter()
            ->unique()
            ->values();

        $speciesImages = SpeciesImage::query()
            ->whereIn('species_id', $speciesIds)
            ->where('type', 'thumbnail')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id',
                'species_id',
                'type',
                'path',
                'alt_text',
                'sort_order',
            ])
            ->groupBy('species_id');

        /*
        |--------------------------------------------------------------------------
        | Agregar thumbnails a cada especie
        |--------------------------------------------------------------------------
        */

        $speciesLocations->each(function ($location) use ($speciesImages) {
            $images = $speciesImages->get(
                $location->species_id,
                collect()
            );

            if ($location->species) {
                $location->species->setRelation(
                    'images',
                    $images->values()
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Caminos
        |--------------------------------------------------------------------------
        */

        $paths = MapPath::query()
            ->where('zone_id', $zooZone->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get([
                'id',
                'zone_id',
                'name',
                'description',
                'coordinates',
                'distance',
                'estimated_time',
                'is_active',
                'order',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'zone' => [
                    'id' => $zooZone->id,
                    'name' => $zooZone->name,
                    'description' => $zooZone->description,
                    'type' => $zooZone->type,
                    'geometry' => $zooZone->geometry,
                    'map_image' => $zooZone->map_image,
                    'map_image_bounds' => $zooZone->map_image_bounds,
                ],

                'markers' => $markers,

                'species_locations' => $speciesLocations,

                'paths' => $paths,
            ],
        ]);
    }

    /**
     * Mapa de ZooDeck
     *
     * El mismo mapa que `GET /map` (zonas, marcadores, `species_locations` y caminos), pero
     * solo con las especies que tienen una tarjeta activa con un código QR utilizable. Es el
     * mapa de las pantallas A-16 ZooDeck y A-17 Duelos.
     *
     * Envía `latitude` y `longitude` (opcionales) con la posición del usuario para saber si
     * está dentro del zoológico.
     *
     * **`visit`**: si el usuario puede retar ahora, con las mismas reglas que `POST /duels`.
     * Con `APP_ZOODECK_ENFORCE_VALIDATIONS` apagado, `can_duel` es `true` aunque `ticket` o
     * `inside_zoo` sean falsos; se muestran solo como aviso.
     *
     * **Cada tarjeta** (`species_locations[].species.cards[]`) trae su `status` para el
     * usuario (`owned`, `locked`, `available`), sus `attempts` (como en el 423 de
     * `POST /duels`) y `can_duel`/`reason` para habilitar o deshabilitar el botón de retar.
     * `reason` sigue el orden en que `POST /duels` rechaza: primero el motivo de `visit`, luego
     * `owned`, `locked` y `no_questions`. `status` indica la propiedad y el bloqueo aunque la
     * visita no permita retar. Estos datos solo guían la interfaz: `POST /duels` vuelve a
     * revisar todo.
     *
     * `species_count` de cada zona cuenta las especies que se pueden retar en ella.
     */
    public function species(ZooDeckMapRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $visit = $this->visits->status(
            $user,
            $request->coordinate('latitude'),
            $request->coordinate('longitude')
        );

        $zones = $this->mapZones(function (EloquentCollection $locations) use ($user, $visit) {
            $cards = $this->duelCards(
                $user,
                $locations->pluck('species_id')->filter()->unique()->values(),
                $visit
            );

            $locations->each(function (SpeciesLocation $location) use ($cards) {
                $location->species?->setRelation(
                    'cards',
                    $cards->get($location->species_id, collect())
                );
            });

            return $locations
                ->filter(fn (SpeciesLocation $location) => $location->species
                    && $location->species->cards->isNotEmpty())
                ->values();
        });

        $zones = $zones->map(fn (array $zone) => [
            ...$zone,
            'species_count' => $zone['species_locations']->pluck('species_id')->unique()->count(),
        ]);

        return response()->json([
            'success' => true,

            'data' => [
                'visit' => $visit,
                'zones' => $zones,
            ],
        ]);
    }

    /**
     * Tarjetas activas con QR utilizable, agrupadas por especie, con el estado del usuario.
     *
     * @param  Collection<int, int>  $speciesIds
     * @param  array{can_duel: bool, reason: string|null}  $visit
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    private function duelCards(User $user, Collection $speciesIds, array $visit): Collection
    {
        $cards = Card::query()
            ->whereIn('species_id', $speciesIds)
            ->where('is_active', true)
            ->whereHas('qrCodes', fn ($query) => $query->usable())
            ->with([
                'raritySetting',
                'qrCodes' => fn ($query) => $query
                    ->usable()
                    ->select([
                        'id',
                        'card_id',
                        'label',
                        'expires_at',
                        'scans_count',
                        'last_scanned_at',
                    ]),
            ])
            ->withCount([
                'quizQuestions as active_questions_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get();

        $cardIds = $cards->modelKeys();

        $owned = $user->cardCaptures()
            ->whereIn('card_id', $cardIds)
            ->pluck('card_id')
            ->flip();

        /** @var EloquentCollection<int, CardQuizState> $states */
        $states = $user->cardQuizStates()
            ->whereIn('card_id', $cardIds)
            ->get()
            ->keyBy('card_id');

        return $cards
            ->map(function (Card $card) use ($owned, $states, $visit) {
                // Solo lectura: un bloqueo que ya terminó se muestra con los intentos reiniciados.
                $attempts = $this->duels->attemptsFor($card, $states->get($card->id));

                /*
                 * Sin intentos y sin bloqueo (el administrador redujo los reintentos)
                 * también es "locked": POST /duels la bloquearía al iniciar.
                 */
                $status = match (true) {
                    $owned->has($card->id) => 'owned',
                    $attempts->lockedUntil !== null || $attempts->left === 0 => 'locked',
                    default => 'available',
                };

                // El mismo orden en que POST /duels rechaza, para que el motivo coincida.
                $reason = match (true) {
                    ! $visit['can_duel'] => $visit['reason'],
                    $status !== 'available' => $status,
                    (int) $card->getAttribute('active_questions_count') === 0 => 'no_questions',
                    default => null,
                };

                return [
                    'species_id' => $card->species_id,
                    'id' => $card->id,
                    'name' => $card->name,
                    'rarity' => $card->rarity,
                    'thumbnail_image' => $card->thumbnail_image,
                    'status' => $status,
                    'attempts' => new DuelAttemptsResource($attempts),
                    'can_duel' => $reason === null,
                    'reason' => $reason,
                    'qr_codes' => $card->qrCodes
                        ->map(fn (CardQrCode $qr) => [
                            'id' => $qr->id,
                            'label' => $qr->label,
                            'expires_at' => $qr->expires_at,
                            'scans_count' => $qr->scans_count,
                            'last_scanned_at' => $qr->last_scanned_at,
                        ])
                        ->values(),
                ];
            })
            ->groupBy('species_id')
            ->map(fn (Collection $group) => $group
                ->map(fn (array $card) => collect($card)->except('species_id')->all())
                ->values());
    }

    /**
     * Validar si un usuario puede realizar capturas
     *
     * Comprueba que el usuario autenticado sea el de la URL, si las coordenadas están dentro
     * de una zona activa del zoológico (`zones`) y si tiene un boleto de una orden pagada hoy
     * (`ticket`). `enforced` indica si `POST /duels` exige ambas cosas
     * (`APP_ZOODECK_ENFORCE_VALIDATIONS`). Son las mismas reglas que usan los duelos y el
     * mapa de ZooDeck.
     */
    public function validateCapture(
        int $user,
        float $latitude,
        float $longitude
    ): JsonResponse {
        if (
            $latitude < -90 ||
            $latitude > 90 ||
            $longitude < -180 ||
            $longitude > 180
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Las coordenadas proporcionadas no son válidas.',
            ], 422);
        }

        /** @var User|null $authenticatedUser */
        $authenticatedUser = request()->user();

        if (! $authenticatedUser || (int) $authenticatedUser->id !== $user) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para consultar este usuario.',
            ], 403);
        }

        return response()->json([
            'success' => true,

            'data' => [
                'zones' => $this->visits->isInsideZoo($latitude, $longitude),
                'ticket' => $this->visits->hasTicketForToday($authenticatedUser),
                'enforced' => $this->visits->enforced(),
            ],
        ]);
    }
}
