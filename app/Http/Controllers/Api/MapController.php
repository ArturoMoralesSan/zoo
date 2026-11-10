<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\Species;
use App\Models\SpeciesImage;
use App\Models\Card;
use App\Models\ZooZone;
use App\Models\Ticket;
use App\Models\SpeciesLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * Obtener toda la información necesaria
     * para mostrar y utilizar el mapa de ZooApp.
     */
    public function index(): JsonResponse
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

        /*
        |--------------------------------------------------------------------------
        | Marcadores
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Ubicaciones de especies
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Organizar información por zona
        |--------------------------------------------------------------------------
        */

        $zones = $zones->map(function (ZooZone $zone) use (
            $markers,
            $speciesLocations,
            $paths
        ) {
            return [
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
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'zones' => $zones,
            ],
        ]);
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
     * Obtener toda la información necesaria
     * para mostrar el mapa de ZooApp con especies
     * que tengan códigos QR disponibles para duelos.
     */
    public function species(): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Zonas
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Ubicaciones de especies
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | IDs de especies
        |--------------------------------------------------------------------------
        */

        $speciesIds = $speciesLocations
            ->pluck('species_id')
            ->filter()
            ->unique()
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Thumbnails de especies
        |--------------------------------------------------------------------------
        */

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
        | Tarjetas de especies con QR utilizables
        |--------------------------------------------------------------------------
        */

        $cards = Card::query()
            ->whereIn('species_id', $speciesIds)
            ->where('is_active', true)
            ->with([
                'qrCodes' => function ($query) {
                    $query
                        ->usable()
                        ->select([
                            'id',
                            'card_id',
                            'label',
                            'is_active',
                            'expires_at',
                            'scans_count',
                            'last_scanned_at',
                        ]);
                },
            ])
            ->orderBy('name')
            ->get([
                'id',
                'species_id',
                'name',
                'rarity',
                'thumbnail_image',
            ])
            ->groupBy('species_id');

        /*
        |--------------------------------------------------------------------------
        | Agregar tarjetas y QR a cada especie
        |--------------------------------------------------------------------------
        */

        $speciesLocations->each(function ($location) use ($cards) {
            if (!$location->species) {
                return;
            }

            $speciesCards = $cards
                ->get($location->species_id, collect())
                ->filter(function ($card) {
                    return $card->qrCodes->isNotEmpty();
                })
                ->values();

            $location->species->setRelation(
                'cards',
                $speciesCards
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Conservar solamente especies que tengan QR
        |--------------------------------------------------------------------------
        */

        $speciesLocations = $speciesLocations
            ->filter(function ($location) {
                return $location->species
                    && $location->species->cards->isNotEmpty();
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Caminos
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Organizar información por zona
        |--------------------------------------------------------------------------
        */

        $zones = $zones->map(function (ZooZone $zone) use (
            $speciesLocations,
            $paths
        ) {
            return [
                'id' => $zone->id,
                'name' => $zone->name,
                'description' => $zone->description,
                'type' => $zone->type,
                'geometry' => $zone->geometry,
                'map_image' => $zone->map_image,
                'map_image_bounds' => $zone->map_image_bounds,

                'species' => $speciesLocations
                    ->where('zone_id', $zone->id)
                    ->map(function ($location) {
                        return [
                            'id' => $location->id,
                            'name' => $location->name,
                            'description' => $location->description,
                            'latitude' => $location->latitude,
                            'longitude' => $location->longitude,

                            'species' => [
                                'id' => $location->species->id,
                                'common_name' => $location->species->common_name,
                                'scientific_name' => $location->species->scientific_name,
                                'description' => $location->species->description,

                                'image' => $location->species->images
                                    ->first()?->path,

                                'cards' => $location->species->cards
                                    ->map(function ($card) {
                                        return [
                                            'id' => $card->id,
                                            'name' => $card->name,
                                            'rarity' => $card->rarity,
                                            'thumbnail_image' => $card->thumbnail_image,

                                            'qr_codes' => $card->qrCodes
                                                ->map(function ($qr) {
                                                    return [
                                                        'id' => $qr->id,
                                                        'label' => $qr->label,
                                                        'expires_at' => $qr->expires_at,
                                                        'scans_count' => $qr->scans_count,
                                                        'last_scanned_at' => $qr->last_scanned_at,
                                                    ];
                                                })
                                                ->values(),
                                        ];
                                    })
                                    ->values(),
                            ],
                        ];
                    })
                    ->values(),

                'paths' => $paths
                    ->where('zone_id', $zone->id)
                    ->values(),
            ];
        })->values();

        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'zones' => $zones,
            ],
        ]);
    }


    /**
     * Validar si un usuario puede realizar capturas.
     *
     * Comprueba:
     * - Que el usuario autenticado corresponda al ID recibido.
     * - Que sus coordenadas estén dentro del zoológico.
     * - Que tenga un boleto pagado y validado durante el día actual.
     */
    public function validateCapture(
        int $user,
        float $latitude,
        float $longitude
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Validar coordenadas
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Validar usuario autenticado
        |--------------------------------------------------------------------------
        */

        $authenticatedUser = request()->user();

        if (!$authenticatedUser || (int) $authenticatedUser->id !== $user) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para consultar este usuario.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar si está dentro del zoológico
        |--------------------------------------------------------------------------
        */

        $zones = ZooZone::query()
            ->where('is_active', true)
            ->get([
                'id',
                'geometry',
            ]);

        $insideZone = $zones->contains(function (ZooZone $zone) use (
            $latitude,
            $longitude
        ) {
            $geometry = $zone->geometry;

            if (is_string($geometry)) {
                $geometry = json_decode($geometry, true);
            }

            if (!is_array($geometry)) {
                return false;
            }

            $type = $geometry['type'] ?? null;
            $coordinates = $geometry['coordinates'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Polygon y MultiPolygon
            |--------------------------------------------------------------------------
            */

            if ($type === 'Polygon') {
                $polygons = [$coordinates];
            } elseif ($type === 'MultiPolygon') {
                $polygons = $coordinates;
            } else {
                return false;
            }

            foreach ($polygons as $polygon) {
                $outerRing = $polygon[0] ?? [];

                if (!$this->pointInPolygon(
                    $longitude,
                    $latitude,
                    $outerRing
                )) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Comprobar huecos del polígono
                |--------------------------------------------------------------------------
                */

                $insideHole = false;

                foreach (array_slice($polygon, 1) as $hole) {
                    if ($this->pointInPolygon(
                        $longitude,
                        $latitude,
                        $hole
                    )) {
                        $insideHole = true;
                        break;
                    }
                }

                if (!$insideHole) {
                    return true;
                }
            }

            return false;
        });

        /*
        |--------------------------------------------------------------------------
        | Validar boleto del usuario
        |--------------------------------------------------------------------------
        */

        $hasValidTicket = Ticket::query()
            ->whereHas('order', function ($query) use ($user) {
                $query
                    ->where('user_id', $user)
                    ->whereNotNull('paid_at');
            })
            ->whereDate('used_at', today())
            ->exists();

        /*
        |--------------------------------------------------------------------------
        | Respuesta
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,

            'data' => [
                'zones' => $insideZone,
                'ticket' => $hasValidTicket,
            ],
        ]);
    }

    /**
     * Determinar si un punto está dentro de un polígono.
     *
     * GeoJSON utiliza coordenadas [longitud, latitud].
     */
    private function pointInPolygon(
        float $longitude,
        float $latitude,
        array $polygon
    ): bool {
        $inside = false;
        $count = count($polygon);

        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $polygon[$i][0];
            $yi = (float) $polygon[$i][1];

            $xj = (float) $polygon[$j][0];
            $yj = (float) $polygon[$j][1];

            $intersects = (
                ($yi > $latitude) !== ($yj > $latitude)
            ) && (
                $longitude < (
                    ($xj - $xi) *
                    ($latitude - $yi) /
                    (($yj - $yi) ?: 0.000000000001)
                    + $xi
                )
            );

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }


}

