<?php

namespace App\Http\Controllers;

use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\SpeciesLocation;
use App\Models\ZooZone;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    /**
     * Mapa público del zoológico.
     */
    public function index(): Response
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

        return Inertia::render(
            'map/Index',
            [
                'zones' => $zones,
            ]
        );
    }

    /**
     * Obtener toda la información necesaria para
     * utilizar una zona como mapa de navegación.
     */
    public function zone(
        ZooZone $zooZone
    ): JsonResponse {
        if (! $zooZone->is_active) {
            return response()->json(
                [
                    'message' => 'La zona no está disponible.',
                ],
                404
            );
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

            'speciesLocations' => $speciesLocations,

            'paths' => $paths,
        ]);
    }
}
