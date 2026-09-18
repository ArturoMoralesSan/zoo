<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MapMarker;
use App\Models\MapPath;
use App\Models\SpeciesLocation;
use App\Models\ZooZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapPathController extends Controller
{
    private const WALKING_SPEED_METERS_PER_MINUTE = 72;

    /**
     * Listado de caminos.
     */
    public function index(Request $request): Response
    {
        $mapPaths = MapPath::query()
            ->with('zone:id,name')
            ->when(
                $request->search,
                fn ($query, $search) => $query->where(
                    fn ($query) => $query
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                )
            )
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/map-paths/Index',
            [
                'mapPaths' => $mapPaths,
                'filters' => [
                    'search' => $request->search,
                ],
            ],
        );
    }

    /**
     * Mostrar formulario para crear un camino.
     */
    public function create(): Response
    {
        return Inertia::render(
            'admin/map-paths/Create',
            [
                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'description',
                        'geometry',
                        'map_image',
                        'map_image_bounds',
                    ]),
            ],
        );
    }

    /**
     * Obtener la información de una zona
     * para utilizarla como área de trabajo
     * del editor de caminos.
     */
    public function zone(
        ZooZone $zooZone
    ): JsonResponse {
        /**
         * -------------------------------------------------
         * Marcadores
         * -------------------------------------------------
         */
        $markers = MapMarker::query()
            ->where(
                'zone_id',
                $zooZone->id
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'type',
                'latitude',
                'longitude',
                'icon',
                'color',
                'zone_id',
            ]);

        /**
         * -------------------------------------------------
         * Ubicaciones de especies
         * -------------------------------------------------
         *
         * Cargamos únicamente la imagen de tipo
         * thumbnail de cada especie.
         */
        $speciesLocations = SpeciesLocation::query()
            ->with([
                'species' => function ($query) {
                    $query->select([
                        'id',
                        'common_name',
                        'scientific_name',
                        'description',
                    ]);
                },

                'species.images' => function ($query) {
                    $query
                        ->where(
                            'type',
                            'thumbnail'
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->select([
                            'id',
                            'species_id',
                            'type',
                            'path',
                            'alt_text',
                            'is_active',
                            'sort_order',
                        ]);
                },
            ])
            ->where(
                'zone_id',
                $zooZone->id
            )
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'species',
                function ($query) {
                    $query->where(
                        'is_active',
                        true
                    );
                }
            )
            ->orderBy('name')
            ->get([
                'id',
                'species_id',
                'zone_id',
                'name',
                'latitude',
                'longitude',
                'description',
            ]);

        /**
         * -------------------------------------------------
         * Respuesta
         * -------------------------------------------------
         */
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
        ]);
    }

    /**
     * Guardar un nuevo camino.
     */
    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate(
            $this->rules()
        );

        $zone = ZooZone::query()
            ->where(
                'is_active',
                true
            )
            ->findOrFail(
                $validated['zone_id']
            );

        $coordinates = $this->validateGraph(
            $validated['coordinates']
        );

        $this->validateNodesInsideZone(
            $coordinates['nodes'],
            $zone
        );

        $distance = $this->calculateDistance(
            $coordinates
        );

        $estimatedTime = $this->calculateEstimatedTime(
            $distance
        );

        MapPath::create([
            'zone_id' => $zone->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'coordinates' => $coordinates,
            'distance' => $distance,
            'estimated_time' => $estimatedTime,
            'is_active' => $validated['is_active'] ?? true,
            'order' => $validated['order'] ?? 0,
        ]);

        return redirect()
            ->route(
                'admin.map-paths.index'
            )
            ->with(
                'success',
                'Camino creado correctamente.'
            );
    }

    /**
     * Mostrar un camino.
     */
    public function show(
        MapPath $mapPath
    ): Response {
        $mapPath->load(
            'zone:id,name,description,type,geometry,map_image,map_image_bounds'
        );

        return Inertia::render(
            'admin/map-paths/Show',
            [
                'mapPath' => $mapPath,
            ],
        );
    }

    /**
     * Mostrar formulario para editar
     * un camino existente.
     */
    public function edit(
        MapPath $mapPath
    ): Response {
        $mapPath->load(
            'zone:id,name,description,type,geometry,map_image,map_image_bounds'
        );

        return Inertia::render(
            'admin/map-paths/Edit',
            [
                'mapPath' => $mapPath,

                'zones' => ZooZone::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'description',
                        'geometry',
                        'map_image',
                        'map_image_bounds',
                    ]),
            ],
        );
    }

    /**
     * Actualizar un camino.
     */
    public function update(
        Request $request,
        MapPath $mapPath
    ): RedirectResponse {
        $validated = $request->validate(
            $this->rules()
        );

        $zone = ZooZone::query()
            ->where(
                'is_active',
                true
            )
            ->findOrFail(
                $validated['zone_id']
            );

        $coordinates = $this->validateGraph(
            $validated['coordinates']
        );

        $this->validateNodesInsideZone(
            $coordinates['nodes'],
            $zone
        );

        $distance = $this->calculateDistance(
            $coordinates
        );

        $estimatedTime = $this->calculateEstimatedTime(
            $distance
        );

        $mapPath->update([
            'zone_id' => $zone->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'coordinates' => $coordinates,
            'distance' => $distance,
            'estimated_time' => $estimatedTime,
            'is_active' => $validated['is_active'] ?? true,
            'order' => $validated['order'] ?? 0,
        ]);

        return redirect()
            ->route(
                'admin.map-paths.index'
            )
            ->with(
                'success',
                'Camino actualizado correctamente.'
            );
    }

    /**
     * Eliminar un camino.
     */
    public function destroy(
        MapPath $mapPath
    ): RedirectResponse {
        $mapPath->delete();

        return redirect()
            ->route(
                'admin.map-paths.index'
            )
            ->with(
                'success',
                'Camino eliminado correctamente.'
            );
    }

    /**
     * Reglas básicas de validación
     * del request.
     */
    private function rules(): array
    {
        return [
            'zone_id' => [
                'required',
                'integer',
                'exists:zoo_zones,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'coordinates' => [
                'required',
                'array',
            ],

            'coordinates.nodes' => [
                'required',
                'array',
                'min:2',
            ],

            'coordinates.nodes.*.id' => [
                'required',
                'integer',
                'min:1',
            ],

            'coordinates.nodes.*.lat' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'coordinates.nodes.*.lng' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'coordinates.edges' => [
                'required',
                'array',
                'min:1',
            ],

            'coordinates.edges.*.from' => [
                'required',
                'integer',
                'min:1',
            ],

            'coordinates.edges.*.to' => [
                'required',
                'integer',
                'min:1',
            ],

            'distance' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'estimated_time' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'boolean',
            ],

            'order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Valida la estructura lógica del grafo.
     */
    private function validateGraph(
        array $coordinates
    ): array {
        $nodes = $coordinates['nodes'] ?? [];
        $edges = $coordinates['edges'] ?? [];

        $nodeIds = array_map(
            fn ($node) => (int) $node['id'],
            $nodes
        );

        if (
            count($nodeIds) !==
            count(array_unique($nodeIds))
        ) {
            abort(
                422,
                'Existen nodos con IDs duplicados.'
            );
        }

        $nodeIdLookup = array_fill_keys(
            $nodeIds,
            true
        );

        $edgeKeys = [];

        foreach (
            $edges as $index => $edge
        ) {
            $from = (int) $edge['from'];
            $to = (int) $edge['to'];

            if (
                ! isset(
                    $nodeIdLookup[$from]
                )
            ) {
                abort(
                    422,
                    "La conexión #{$index} apunta al nodo origen {$from}, pero ese nodo no existe."
                );
            }

            if (
                ! isset(
                    $nodeIdLookup[$to]
                )
            ) {
                abort(
                    422,
                    "La conexión #{$index} apunta al nodo destino {$to}, pero ese nodo no existe."
                );
            }

            if ($from === $to) {
                abort(
                    422,
                    "La conexión #{$index} no puede conectar un nodo consigo mismo."
                );
            }

            $edgeKey =
                min($from, $to)
                . '-'
                . max($from, $to);

            if (
                isset(
                    $edgeKeys[$edgeKey]
                )
            ) {
                abort(
                    422,
                    "La conexión entre los nodos {$from} y {$to} está duplicada."
                );
            }

            $edgeKeys[$edgeKey] = true;
        }

        return [
            'nodes' => array_values(
                $nodes
            ),

            'edges' => array_values(
                array_map(
                    fn ($edge) => [
                        'from' => (int) $edge['from'],
                        'to' => (int) $edge['to'],
                    ],
                    $edges
                )
            ),
        ];
    }

    /**
     * Valida que todos los nodos del camino
     * estén dentro de la geometría de la zona.
     */
    private function validateNodesInsideZone(
        array $nodes,
        ZooZone $zone
    ): void {
        $geometry = $zone->geometry;

        if (
            ! is_array($geometry) ||
            empty($geometry)
        ) {
            abort(
                422,
                'La zona seleccionada no tiene una geometría válida.'
            );
        }

        foreach ($nodes as $node) {
            $latitude = (float) $node['lat'];
            $longitude = (float) $node['lng'];

            if (
                ! $this->pointInGeometry(
                    $latitude,
                    $longitude,
                    $geometry
                )
            ) {
                $nodeId = (int) $node['id'];

                abort(
                    422,
                    "El nodo {$nodeId} está fuera de la zona seleccionada."
                );
            }
        }
    }

    /**
     * Comprueba si un punto está dentro
     * de una geometría GeoJSON.
     */
    private function pointInGeometry(
        float $latitude,
        float $longitude,
        array $geometry
    ): bool {
        $type = $geometry['type'] ?? null;

        if ($type === 'Feature') {
            $featureGeometry =
                $geometry['geometry'] ?? null;

            if (
                ! is_array(
                    $featureGeometry
                )
            ) {
                return false;
            }

            return $this->pointInGeometry(
                $latitude,
                $longitude,
                $featureGeometry
            );
        }

        if (
            $type ===
            'FeatureCollection'
        ) {
            $features =
                $geometry['features'] ?? [];

            foreach (
                $features as $feature
            ) {
                if (
                    is_array($feature) &&
                    $this->pointInGeometry(
                        $latitude,
                        $longitude,
                        $feature
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'Polygon') {
            $coordinates =
                $geometry['coordinates'] ?? [];

            if (
                empty($coordinates)
            ) {
                return false;
            }

            $outerRing =
                $coordinates[0] ?? [];

            if (
                ! $this->pointInRing(
                    $latitude,
                    $longitude,
                    $outerRing
                )
            ) {
                return false;
            }

            for (
                $index = 1;
                $index < count($coordinates);
                $index++
            ) {
                if (
                    $this->pointInRing(
                        $latitude,
                        $longitude,
                        $coordinates[$index]
                    )
                ) {
                    return false;
                }
            }

            return true;
        }

        if (
            $type ===
            'MultiPolygon'
        ) {
            $polygons =
                $geometry['coordinates'] ?? [];

            foreach (
                $polygons as $polygon
            ) {
                $polygonGeometry = [
                    'type' => 'Polygon',
                    'coordinates' => $polygon,
                ];

                if (
                    $this->pointInGeometry(
                        $latitude,
                        $longitude,
                        $polygonGeometry
                    )
                ) {
                    return true;
                }
            }

            return false;
        }

        if (
            isset(
                $geometry['coordinates']
            ) &&
            is_array(
                $geometry['coordinates']
            )
        ) {
            return $this->pointInGeometry(
                $latitude,
                $longitude,
                [
                    'type' => 'Polygon',
                    'coordinates' => $geometry['coordinates'],
                ]
            );
        }

        return false;
    }

    /**
     * Point in Polygon utilizando
     * Ray Casting.
     */
    private function pointInRing(
        float $latitude,
        float $longitude,
        array $ring
    ): bool {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for (
            $i = 0,
            $j = $count - 1;
            $i < $count;
            $j = $i++
        ) {
            $pointI =
                $ring[$i] ?? null;

            $pointJ =
                $ring[$j] ?? null;

            if (
                ! is_array($pointI) ||
                ! is_array($pointJ) ||
                count($pointI) < 2 ||
                count($pointJ) < 2
            ) {
                continue;
            }

            $longitudeI =
                (float) $pointI[0];

            $latitudeI =
                (float) $pointI[1];

            $longitudeJ =
                (float) $pointJ[0];

            $latitudeJ =
                (float) $pointJ[1];

            $intersects =
                (
                    ($latitudeI > $latitude) !==
                    ($latitudeJ > $latitude)
                )
                &&
                (
                    $longitude <
                    (
                        (
                            $longitudeJ -
                            $longitudeI
                        )
                        *
                        (
                            $latitude -
                            $latitudeI
                        )
                        /
                        (
                            $latitudeJ -
                            $latitudeI
                        )
                    )
                    +
                    $longitudeI
                );

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Calcula la distancia total del grafo.
     */
    private function calculateDistance(
        array $coordinates
    ): int {
        $nodes =
            $coordinates['nodes'] ?? [];

        $edges =
            $coordinates['edges'] ?? [];

        $nodeLookup = [];

        foreach (
            $nodes as $node
        ) {
            $nodeLookup[
                (int) $node['id']
            ] = $node;
        }

        $distance = 0.0;

        foreach (
            $edges as $edge
        ) {
            $from =
                $nodeLookup[
                    (int) $edge['from']
                ] ?? null;

            $to =
                $nodeLookup[
                    (int) $edge['to']
                ] ?? null;

            if (
                ! $from ||
                ! $to
            ) {
                continue;
            }

            $distance +=
                $this->haversineDistance(
                    (float) $from['lat'],
                    (float) $from['lng'],
                    (float) $to['lat'],
                    (float) $to['lng'],
                );
        }

        return (int) round(
            $distance
        );
    }

    /**
     * Distancia entre dos coordenadas
     * utilizando la fórmula de Haversine.
     */
    private function haversineDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2,
    ): float {
        $earthRadius =
            6371000;

        $latitude1Radians =
            deg2rad(
                $latitude1
            );

        $latitude2Radians =
            deg2rad(
                $latitude2
            );

        $deltaLatitude =
            deg2rad(
                $latitude2 -
                $latitude1
            );

        $deltaLongitude =
            deg2rad(
                $longitude2 -
                $longitude1
            );

        $a =
            sin(
                $deltaLatitude / 2
            ) ** 2
            +
            cos(
                $latitude1Radians
            )
            *
            cos(
                $latitude2Radians
            )
            *
            sin(
                $deltaLongitude / 2
            ) ** 2;

        $c =
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );

        return
            $earthRadius *
            $c;
    }

    /**
     * Calcula el tiempo estimado de caminata.
     */
    private function calculateEstimatedTime(
        int $distance
    ): int {
        if ($distance <= 0) {
            return 0;
        }

        return (int) ceil(
            $distance /
            self::WALKING_SPEED_METERS_PER_MINUTE
        );
    }
}