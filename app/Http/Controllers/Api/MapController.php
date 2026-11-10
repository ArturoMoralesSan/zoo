<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\Species;
use App\Models\SpeciesImage;
use App\Models\Card;
use App\Models\SpeciesLocation;
use App\Models\ZooZone;
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


    


}

