<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\SpeciesLocation;
use App\Models\ZooZone;
use Illuminate\Http\JsonResponse;

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
}