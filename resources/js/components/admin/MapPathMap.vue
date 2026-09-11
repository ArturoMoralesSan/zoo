<script setup lang="ts">
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

interface PathNode {
    id: number;
    lat: number;
    lng: number;
}

interface PathEdge {
    from: number;
    to: number;
}

interface PathData {
    nodes: PathNode[];
    edges: PathEdge[];
}

interface GeoJsonGeometry {
    type: string;
    coordinates: unknown;
}

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

interface MapMarker {
    id: number;
    name: string;
    description: string | null;
    type: string | null;
    latitude: number | string;
    longitude: number | string;
    icon: string | null;
    color: string | null;
}

interface SpeciesLocation {
    id: number;
    species_id: number;
    zone_id: number;
    name: string;
    latitude: number | string;
    longitude: number | string;
    description: string | null;
    species?: {
        id: number;
        common_name: string;
        scientific_name: string | null;
        description: string | null;
    } | null;
}

const props = withDefaults(
    defineProps<{
        coordinates:
            | PathData
            | PathNode[]
            | null;

        readonly?: boolean;

        zoneGeometry?:
            | GeoJsonGeometry
            | null;

        mapImage?: string | null;

        mapImageBounds?:
            | MapImageBounds
            | null;

        markers?: MapMarker[];

        speciesLocations?: SpeciesLocation[];
    }>(),
    {
        readonly: false,
        zoneGeometry: null,
        mapImage: null,
        mapImageBounds: null,
        markers: () => [],
        speciesLocations: () => [],
    },
);

const emit = defineEmits<{
    'update:coordinates': [value: PathData];

    'update:distance': [value: number];
}>();

const mapContainer =
    ref<HTMLElement | null>(null);

let map: L.Map | null = null;

let zoneLayer: L.GeoJSON | null = null;

let imageLayer: L.ImageOverlay | null = null;

let referenceLayer: L.LayerGroup | null = null;

let edgesLayer: L.LayerGroup | null = null;

let nodesLayer: L.LayerGroup | null = null;

const selectedNodeId =
    ref<number | null>(null);

let originalDragPosition:
    | L.LatLng
    | null = null;

/*
|--------------------------------------------------------------------------
| Normalización
|--------------------------------------------------------------------------
*/

function normalizeCoordinates(
    value:
        | PathData
        | PathNode[]
        | null,
): PathData {
    if (!value) {
        return {
            nodes: [],
            edges: [],
        };
    }

    if (Array.isArray(value)) {
        const nodes = value.map(
            (node, index) => ({
                id: Number(
                    node.id ?? index + 1,
                ),
                lat: Number(node.lat),
                lng: Number(node.lng),
            }),
        );

        const edges: PathEdge[] = [];

        for (
            let index = 1;
            index < nodes.length;
            index++
        ) {
            edges.push({
                from:
                    nodes[index - 1].id,
                to: nodes[index].id,
            });
        }

        return {
            nodes,
            edges,
        };
    }

    return {
        nodes: Array.isArray(value.nodes)
            ? value.nodes.map((node) => ({
                  id: Number(node.id),
                  lat: Number(node.lat),
                  lng: Number(node.lng),
              }))
            : [],

        edges: Array.isArray(value.edges)
            ? value.edges.map((edge) => ({
                  from: Number(edge.from),
                  to: Number(edge.to),
              }))
            : [],
    };
}

const pathData = ref<PathData>(
    normalizeCoordinates(
        props.coordinates,
    ),
);

/*
|--------------------------------------------------------------------------
| Sincronización
|--------------------------------------------------------------------------
*/

function syncCoordinates(): void {
    emit('update:coordinates', {
        nodes: pathData.value.nodes.map(
            (node) => ({
                id: node.id,
                lat: node.lat,
                lng: node.lng,
            }),
        ),

        edges: pathData.value.edges.map(
            (edge) => ({
                from: edge.from,
                to: edge.to,
            }),
        ),
    });
}

/*
|--------------------------------------------------------------------------
| Geometría de zona
|--------------------------------------------------------------------------
*/

function pointInRing(
    latitude: number,
    longitude: number,
    ring: number[][],
): boolean {
    let inside = false;

    for (
        let i = 0,
            j = ring.length - 1;
        i < ring.length;
        j = i++
    ) {
        const xi = Number(
            ring[i][0],
        );

        const yi = Number(
            ring[i][1],
        );

        const xj = Number(
            ring[j][0],
        );

        const yj = Number(
            ring[j][1],
        );

        const intersects =
            yi > latitude !==
                yj > latitude &&
            longitude <
                ((xj - xi) *
                    (latitude - yi)) /
                    (yj - yi) +
                    xi;

        if (intersects) {
            inside = !inside;
        }
    }

    return inside;
}

function pointInPolygon(
    latitude: number,
    longitude: number,
    polygon: number[][][],
): boolean {
    if (!polygon.length) {
        return false;
    }

    if (
        !pointInRing(
            latitude,
            longitude,
            polygon[0],
        )
    ) {
        return false;
    }

    for (
        let index = 1;
        index < polygon.length;
        index++
    ) {
        if (
            pointInRing(
                latitude,
                longitude,
                polygon[index],
            )
        ) {
            return false;
        }
    }

    return true;
}

function extractPolygonCoordinates(
    geometry:
        | GeoJsonGeometry
        | null,
): number[][][][] {
    if (!geometry) {
        return [];
    }

    if (
        geometry.type ===
        'Polygon'
    ) {
        return [
            geometry.coordinates as number[][][],
        ];
    }

    if (
        geometry.type ===
        'MultiPolygon'
    ) {
        return geometry.coordinates as number[][][][];
    }

    if (
        geometry.type ===
        'Feature'
    ) {
        const featureGeometry =
            (
                geometry as unknown as {
                    geometry?:
                        | GeoJsonGeometry
                        | null;
                }
            ).geometry;

        return extractPolygonCoordinates(
            featureGeometry ?? null,
        );
    }

    if (
        geometry.type ===
        'FeatureCollection'
    ) {
        const features =
            (
                geometry as unknown as {
                    features?: Array<{
                        geometry?:
                            | GeoJsonGeometry
                            | null;
                    }>;
                }
            ).features ?? [];

        return features.flatMap(
            (feature) =>
                extractPolygonCoordinates(
                    feature.geometry ?? null,
                ),
        );
    }

    return [];
}

function isInsideZone(
    latitude: number,
    longitude: number,
): boolean {
    if (!props.zoneGeometry) {
        return true;
    }

    const polygons =
        extractPolygonCoordinates(
            props.zoneGeometry,
        );

    if (!polygons.length) {
        return true;
    }

    return polygons.some(
        (polygon) =>
            pointInPolygon(
                latitude,
                longitude,
                polygon,
            ),
    );
}

/*
|--------------------------------------------------------------------------
| Imagen del plano
|--------------------------------------------------------------------------
*/

function getMapImageUrl(): string | null {
    const image =
        props.mapImage;

    if (!image) {
        return null;
    }

    if (
        image.startsWith(
            'http://',
        ) ||
        image.startsWith(
            'https://',
        ) ||
        image.startsWith(
            'blob:',
        ) ||
        image.startsWith(
            'data:',
        ) ||
        image.startsWith('/')
    ) {
        return image;
    }

    return `/storage/${image}`;
}

function drawMapImage(): void {
    if (!map) {
        return;
    }

    if (imageLayer) {
        imageLayer.remove();

        imageLayer = null;
    }

    const imageUrl =
        getMapImageUrl();

    const bounds =
        props.mapImageBounds;

    if (
        !imageUrl ||
        !bounds
    ) {
        return;
    }

    const imageBounds:
        L.LatLngBoundsExpression = [
        [
            Number(bounds.south),
            Number(bounds.west),
        ],
        [
            Number(bounds.north),
            Number(bounds.east),
        ],
    ];

    imageLayer =
        L.imageOverlay(
            imageUrl,
            imageBounds,
            {
                opacity: 1,

                /*
                 * MUY IMPORTANTE:
                 *
                 * El plano no debe recibir
                 * eventos del mouse.
                 *
                 * Así podemos hacer clic
                 * directamente sobre la imagen
                 * para crear nodos.
                 */
                interactive: false,

                crossOrigin: true,

                className:
                    'zoo-map-plan-image',

                pane:
                    'zooMapImagePane',
            },
        ).addTo(map);
}

/*
|--------------------------------------------------------------------------
| Nodos y conexiones
|--------------------------------------------------------------------------
*/

function getNextNodeId(): number {
    if (
        pathData.value.nodes
            .length === 0
    ) {
        return 1;
    }

    return (
        Math.max(
            ...pathData.value.nodes.map(
                (node) => node.id,
            ),
        ) + 1
    );
}

function areNodesConnected(
    firstId: number,
    secondId: number,
): boolean {
    return pathData.value.edges.some(
        (edge) =>
            (edge.from ===
                firstId &&
                edge.to ===
                    secondId) ||
            (edge.from ===
                secondId &&
                edge.to ===
                    firstId),
    );
}

function addConnection(
    firstId: number,
    secondId: number,
): void {
    if (
        firstId === secondId
    ) {
        return;
    }

    if (
        areNodesConnected(
            firstId,
            secondId,
        )
    ) {
        return;
    }

    pathData.value.edges.push({
        from: firstId,
        to: secondId,
    });

    syncCoordinates();

    emitDistance();

    drawMap();
}

function handleNodeClick(
    node: PathNode,
): void {
    if (props.readonly) {
        return;
    }

    if (
        selectedNodeId.value ===
        null
    ) {
        selectedNodeId.value =
            node.id;

        drawNodes();

        return;
    }

    if (
        selectedNodeId.value ===
        node.id
    ) {
        return;
    }

    addConnection(
        selectedNodeId.value,
        node.id,
    );

    selectedNodeId.value =
        node.id;

    drawNodes();
}

function handleMapClick(
    event: L.LeafletMouseEvent,
): void {
    if (props.readonly) {
        return;
    }

    const latitude =
        event.latlng.lat;

    const longitude =
        event.latlng.lng;

    /*
     * La zona es la única
     * restricción geográfica.
     *
     * El plano NO restringe.
     */
    if (
        !isInsideZone(
            latitude,
            longitude,
        )
    ) {
        window.alert(
            'El nodo debe estar dentro de la zona seleccionada.',
        );

        return;
    }

    const newNode: PathNode = {
        id: getNextNodeId(),
        lat: latitude,
        lng: longitude,
    };

    pathData.value.nodes.push(
        newNode,
    );

    if (
        selectedNodeId.value !==
        null
    ) {
        addConnection(
            selectedNodeId.value,
            newNode.id,
        );
    }

    selectedNodeId.value =
        newNode.id;

    syncCoordinates();

    emitDistance();

    drawMap();
}

/*
|--------------------------------------------------------------------------
| Drag de nodos
|--------------------------------------------------------------------------
*/

function handleNodeDragStart(
    event: L.DragEvent,
): void {
    originalDragPosition =
        event.target.getLatLng();
}

function handleNodeDrag(
    node: PathNode,
    event: L.DragEvent,
): void {
    const marker =
        event.target as L.Marker;

    const position =
        marker.getLatLng();

    if (
        !isInsideZone(
            position.lat,
            position.lng,
        )
    ) {
        if (
            originalDragPosition
        ) {
            marker.setLatLng(
                originalDragPosition,
            );
        }

        return;
    }

    node.lat =
        position.lat;

    node.lng =
        position.lng;

    syncCoordinates();

    emitDistance();

    drawEdges();
}

function handleNodeDragEnd(
    node: PathNode,
    event: L.DragEndEvent,
): void {
    const marker =
        event.target as L.Marker;

    const position =
        marker.getLatLng();

    if (
        !isInsideZone(
            position.lat,
            position.lng,
        )
    ) {
        if (
            originalDragPosition
        ) {
            marker.setLatLng(
                originalDragPosition,
            );

            node.lat =
                originalDragPosition.lat;

            node.lng =
                originalDragPosition.lng;
        }
    } else {
        node.lat =
            position.lat;

        node.lng =
            position.lng;
    }

    originalDragPosition =
        null;

    syncCoordinates();

    emitDistance();

    drawMap();
}

/*
|--------------------------------------------------------------------------
| Zona
|--------------------------------------------------------------------------
*/

function drawZone(): void {
    if (!map) {
        return;
    }

    if (zoneLayer) {
        zoneLayer.remove();

        zoneLayer = null;
    }

    if (!props.zoneGeometry) {
        return;
    }

    zoneLayer =
        L.geoJSON(
            props.zoneGeometry as any,
            {
                pane:
                    'zooMapZonePane',

                style: {
                    color: '#2563eb',
                    weight: 3,
                    opacity: 0.8,
                    fillOpacity: 0.08,
                    dashArray: '8 6',
                },
            },
        ).addTo(map);
}

/*
|--------------------------------------------------------------------------
| Markers y especies
|--------------------------------------------------------------------------
*/

function escapeHtml(
    value: string,
): string {
    return value
        .replaceAll(
            '&',
            '&amp;',
        )
        .replaceAll(
            '<',
            '&lt;',
        )
        .replaceAll(
            '>',
            '&gt;',
        )
        .replaceAll(
            '"',
            '&quot;',
        )
        .replaceAll(
            "'",
            '&#039;',
        );
}

function drawReferenceMarkers(): void {
    if (!map) {
        return;
    }

    if (referenceLayer) {
        referenceLayer.clearLayers();
    } else {
        referenceLayer =
            L.layerGroup().addTo(
                map,
            );
    }

    /*
     * MapMarkers
     */
    props.markers.forEach(
        (item) => {
            const latitude =
                Number(
                    item.latitude,
                );

            const longitude =
                Number(
                    item.longitude,
                );

            if (
                Number.isNaN(
                    latitude,
                ) ||
                Number.isNaN(
                    longitude,
                )
            ) {
                return;
            }

            const iconText =
                item.icon ||
                '📍';

            const color =
                item.color ||
                '#7c3aed';

            const icon =
                L.divIcon({
                    className:
                        'zoo-map-reference-marker',

                    html: `
                        <div
                            style="
                                width: 32px;
                                height: 32px;
                                border-radius: 50%;
                                background: ${color};
                                border: 3px solid white;
                                box-shadow: 0 2px 6px rgba(0,0,0,.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 16px;
                            "
                        >
                            ${escapeHtml(
                                iconText,
                            )}
                        </div>
                    `,

                    iconSize: [
                        32,
                        32,
                    ],

                    iconAnchor: [
                        16,
                        16,
                    ],
                });

            const marker =
                L.marker(
                    [
                        latitude,
                        longitude,
                    ],
                    {
                        icon,
                    },
                );

            let popup = `
                <div style="min-width:180px">
                    <strong>
                        ${escapeHtml(
                            item.name,
                        )}
                    </strong>
            `;

            if (item.type) {
                popup += `
                    <div
                        style="
                            margin-top:4px;
                            font-size:12px;
                            color:#666;
                        "
                    >
                        ${escapeHtml(
                            item.type,
                        )}
                    </div>
                `;
            }

            if (
                item.description
            ) {
                popup += `
                    <div style="margin-top:8px">
                        ${escapeHtml(
                            item.description,
                        )}
                    </div>
                `;
            }

            popup +=
                '</div>';

            marker.bindPopup(
                popup,
            );

            marker.bindTooltip(
                item.name,
                {
                    direction: 'top',

                    offset: [
                        0,
                        -16,
                    ],
                },
            );

            referenceLayer?.addLayer(
                marker,
            );
        },
    );

    /*
     * SpeciesLocation
     */
    props.speciesLocations.forEach(
        (item) => {
            const latitude =
                Number(
                    item.latitude,
                );

            const longitude =
                Number(
                    item.longitude,
                );

            if (
                Number.isNaN(
                    latitude,
                ) ||
                Number.isNaN(
                    longitude,
                )
            ) {
                return;
            }

            const icon =
                L.divIcon({
                    className:
                        'zoo-map-species-marker',

                    html: `
                        <div
                            style="
                                width: 34px;
                                height: 34px;
                                border-radius: 50%;
                                background: #16a34a;
                                border: 3px solid white;
                                box-shadow: 0 2px 6px rgba(0,0,0,.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 17px;
                            "
                        >
                            🐾
                        </div>
                    `,

                    iconSize: [
                        34,
                        34,
                    ],

                    iconAnchor: [
                        17,
                        17,
                    ],
                });

            const marker =
                L.marker(
                    [
                        latitude,
                        longitude,
                    ],
                    {
                        icon,
                    },
                );

            const speciesName =
                item.species
                    ?.common_name ??
                item.name;

            let popup = `
                <div style="min-width:200px">
                    <strong>
                        ${escapeHtml(
                            speciesName,
                        )}
                    </strong>
            `;

            if (
                item.species
                    ?.scientific_name
            ) {
                popup += `
                    <div
                        style="
                            margin-top:4px;
                            font-size:12px;
                            font-style:italic;
                            color:#666;
                        "
                    >
                        ${escapeHtml(
                            item.species
                                .scientific_name,
                        )}
                    </div>
                `;
            }

            if (item.name) {
                popup += `
                    <div
                        style="
                            margin-top:8px;
                            font-size:13px;
                        "
                    >
                        ${escapeHtml(
                            item.name,
                        )}
                    </div>
                `;
            }

            if (
                item.description
            ) {
                popup += `
                    <div style="margin-top:8px">
                        ${escapeHtml(
                            item.description,
                        )}
                    </div>
                `;
            }

            popup +=
                '</div>';

            marker.bindPopup(
                popup,
            );

            marker.bindTooltip(
                speciesName,
                {
                    direction: 'top',

                    offset: [
                        0,
                        -17,
                    ],
                },
            );

            referenceLayer?.addLayer(
                marker,
            );
        },
    );
}

/*
|--------------------------------------------------------------------------
| Líneas
|--------------------------------------------------------------------------
*/

function drawEdges(): void {
    if (!map) {
        return;
    }

    if (edgesLayer) {
        edgesLayer.clearLayers();
    } else {
        edgesLayer =
            L.layerGroup().addTo(
                map,
            );
    }

    const nodeLookup =
        new Map<
            number,
            PathNode
        >();

    pathData.value.nodes.forEach(
        (node) => {
            nodeLookup.set(
                node.id,
                node,
            );
        },
    );

    pathData.value.edges.forEach(
        (edge) => {
            const from =
                nodeLookup.get(
                    edge.from,
                );

            const to =
                nodeLookup.get(
                    edge.to,
                );

            if (!from || !to) {
                return;
            }

            const polyline =
                L.polyline(
                    [
                        [
                            from.lat,
                            from.lng,
                        ],
                        [
                            to.lat,
                            to.lng,
                        ],
                    ],
                    {
                        color: '#2563eb',
                        weight: 5,
                        opacity: 0.85,

                        pane:
                            'zooMapPathPane',
                    },
                );

            edgesLayer?.addLayer(
                polyline,
            );
        },
    );
}

/*
|--------------------------------------------------------------------------
| Nodos visuales
|--------------------------------------------------------------------------
*/

function drawNodes(): void {
    if (!map) {
        return;
    }

    if (nodesLayer) {
        nodesLayer.clearLayers();
    } else {
        nodesLayer =
            L.layerGroup().addTo(
                map,
            );
    }

    pathData.value.nodes.forEach(
        (node) => {
            const selected =
                selectedNodeId.value ===
                node.id;

            const icon =
                L.divIcon({
                    className:
                        'zoo-map-path-node',

                    html: `
                        <div
                            style="
                                width: 28px;
                                height: 28px;
                                border-radius: 50%;
                                background: ${
                                    selected
                                        ? '#dc2626'
                                        : '#2563eb'
                                };
                                border: 3px solid white;
                                box-shadow: 0 2px 6px rgba(0,0,0,.35);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                color: white;
                                font-size: 12px;
                                font-weight: 700;
                                cursor: ${
                                    props.readonly
                                        ? 'default'
                                        : 'pointer'
                                };
                            "
                        >
                            ${node.id}
                        </div>
                    `,

                    iconSize: [
                        28,
                        28,
                    ],

                    iconAnchor: [
                        14,
                        14,
                    ],
                });

            const marker =
                L.marker(
                    [
                        node.lat,
                        node.lng,
                    ],
                    {
                        icon,

                        draggable:
                            !props.readonly,

                        pane:
                            'zooMapNodePane',
                    },
                );

            marker.on(
                'click',
                () => {
                    handleNodeClick(
                        node,
                    );
                },
            );

            marker.bindTooltip(
                `Nodo ${node.id}`,
                {
                    direction: 'top',

                    offset: [
                        0,
                        -14,
                    ],
                },
            );

            if (!props.readonly) {
                marker.on(
                    'dragstart',
                    handleNodeDragStart,
                );

                marker.on(
                    'drag',
                    (event) => {
                        handleNodeDrag(
                            node,
                            event,
                        );
                    },
                );

                marker.on(
                    'dragend',
                    (event) => {
                        handleNodeDragEnd(
                            node,
                            event,
                        );
                    },
                );
            }

            nodesLayer?.addLayer(
                marker,
            );
        },
    );
}

/*
|--------------------------------------------------------------------------
| Mapa
|--------------------------------------------------------------------------
*/

function createMapPanes(): void {
    if (!map) {
        return;
    }

    /*
     * Plano:
     * debajo de todo el contenido
     * dinámico, pero encima de los
     * tiles de OpenStreetMap.
     */
    if (
        !map.getPane(
            'zooMapImagePane',
        )
    ) {
        const pane =
            map.createPane(
                'zooMapImagePane',
            );

        pane.style.zIndex =
            '250';

        pane.style.pointerEvents =
            'none';
    }

    /*
     * Zona.
     */
    if (
        !map.getPane(
            'zooMapZonePane',
        )
    ) {
        const pane =
            map.createPane(
                'zooMapZonePane',
            );

        pane.style.zIndex =
            '350';

        pane.style.pointerEvents =
            'none';
    }

    /*
     * Caminos.
     */
    if (
        !map.getPane(
            'zooMapPathPane',
        )
    ) {
        const pane =
            map.createPane(
                'zooMapPathPane',
            );

        pane.style.zIndex =
            '450';

        pane.style.pointerEvents =
            'none';
    }

    /*
     * Nodos.
     */
    if (
        !map.getPane(
            'zooMapNodePane',
        )
    ) {
        const pane =
            map.createPane(
                'zooMapNodePane',
            );

        pane.style.zIndex =
            '650';
    }
}

function drawMap(): void {
    if (!map) {
        return;
    }

    /*
     * Orden visual:
     *
     * 1. Tiles
     * 2. Plano
     * 3. Zona
     * 4. Caminos
     * 5. Referencias
     * 6. Nodos
     */

    drawMapImage();

    drawZone();

    drawReferenceMarkers();

    drawEdges();

    drawNodes();

    nextTick(() => {
        if (!map) {
            return;
        }

        const nodes =
            pathData.value.nodes;

        /*
         * Si existen nodos,
         * centramos en ellos.
         */
        if (
            nodes.length > 0
        ) {
            const bounds =
                L.latLngBounds(
                    nodes.map(
                        (node) =>
                            [
                                node.lat,
                                node.lng,
                            ] as [
                                number,
                                number,
                            ],
                    ),
                );

            if (
                bounds.isValid()
            ) {
                map.fitBounds(
                    bounds,
                    {
                        padding: [
                            40,
                            40,
                        ],

                        maxZoom: 19,
                    },
                );
            }

            return;
        }

        /*
         * Si no hay nodos,
         * centramos en la zona.
         */
        if (zoneLayer) {
            const bounds =
                zoneLayer.getBounds();

            if (
                bounds.isValid()
            ) {
                map.fitBounds(
                    bounds,
                    {
                        padding: [
                            30,
                            30,
                        ],

                        maxZoom: 19,
                    },
                );
            }

            return;
        }

        /*
         * Si no hay zona pero sí
         * existe un plano.
         */
        if (imageLayer) {
            const bounds =
                imageLayer.getBounds();

            if (
                bounds.isValid()
            ) {
                map.fitBounds(
                    bounds,
                    {
                        padding: [
                            30,
                            30,
                        ],

                        maxZoom: 19,
                    },
                );
            }
        }
    });
}

/*
|--------------------------------------------------------------------------
| Distancia
|--------------------------------------------------------------------------
*/

function calculateDistance(): number {
    const nodeLookup =
        new Map<
            number,
            PathNode
        >();

    pathData.value.nodes.forEach(
        (node) => {
            nodeLookup.set(
                node.id,
                node,
            );
        },
    );

    let distance = 0;

    pathData.value.edges.forEach(
        (edge) => {
            const from =
                nodeLookup.get(
                    edge.from,
                );

            const to =
                nodeLookup.get(
                    edge.to,
                );

            if (!from || !to) {
                return;
            }

            distance +=
                haversineDistance(
                    from.lat,
                    from.lng,
                    to.lat,
                    to.lng,
                );
        },
    );

    return Math.round(
        distance,
    );
}

function haversineDistance(
    latitude1: number,
    longitude1: number,
    latitude2: number,
    longitude2: number,
): number {
    const earthRadius =
        6371000;

    const latitude1Radians =
        (latitude1 *
            Math.PI) /
        180;

    const latitude2Radians =
        (latitude2 *
            Math.PI) /
        180;

    const deltaLatitude =
        ((latitude2 -
            latitude1) *
            Math.PI) /
        180;

    const deltaLongitude =
        ((longitude2 -
            longitude1) *
            Math.PI) /
        180;

    const a =
        Math.sin(
            deltaLatitude / 2,
        ) **
            2 +
        Math.cos(
            latitude1Radians,
        ) *
            Math.cos(
                latitude2Radians,
            ) *
            Math.sin(
                deltaLongitude / 2,
            ) **
                2;

    const c =
        2 *
        Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a),
        );

    return (
        earthRadius * c
    );
}

function emitDistance(): void {
    emit(
        'update:distance',
        calculateDistance(),
    );
}

/*
|--------------------------------------------------------------------------
| Watchers
|--------------------------------------------------------------------------
*/

watch(
    () => props.coordinates,
    (value) => {
        pathData.value =
            normalizeCoordinates(
                value,
            );

        drawMap();

        emitDistance();
    },
    {
        deep: true,
    },
);

watch(
    () => props.zoneGeometry,
    () => {
        drawMap();
    },
    {
        deep: true,
    },
);

watch(
    () => props.mapImage,
    () => {
        drawMap();
    },
);

watch(
    () => props.mapImageBounds,
    () => {
        drawMap();
    },
    {
        deep: true,
    },
);

watch(
    () => props.markers,
    () => {
        drawMap();
    },
    {
        deep: true,
    },
);

watch(
    () => props.speciesLocations,
    () => {
        drawMap();
    },
    {
        deep: true,
    },
);

watch(
    selectedNodeId,
    () => {
        drawNodes();
    },
);

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onMounted(() => {
    if (!mapContainer.value) {
        return;
    }

    map = L.map(
        mapContainer.value,
        {
            zoomControl: true,
            attributionControl: true,
        },
    );

    /*
     * Tiles base.
     */
    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',

            maxZoom: 22,
        },
    ).addTo(map);

    /*
     * Creamos las capas
     * personalizadas antes de
     * dibujar el contenido.
     */
    createMapPanes();

    map.on(
        'click',
        handleMapClick,
    );

    drawMap();

    setTimeout(() => {
        map?.invalidateSize();

        drawMap();
    }, 200);
});

onBeforeUnmount(() => {
    if (map) {
        map.off(
            'click',
            handleMapClick,
        );

        map.remove();

        map = null;
    }
});
</script>

<template>
    <div class="space-y-3">
        <!-- Instrucciones -->
        <div
            v-if="!readonly"
            class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
        >
            <div class="font-semibold">
                Cómo dibujar el camino
            </div>

            <ul
                class="mt-2 list-inside list-disc space-y-1"
            >
                <li>
                    Haz clic sobre un punto del mapa
                    para crear un nodo.
                </li>

                <li>
                    Si existe un nodo seleccionado,
                    el nuevo nodo se conectará
                    automáticamente con él.
                </li>

                <li>
                    Haz clic sobre otro nodo para
                    conectar ambos.
                </li>

                <li>
                    Puedes arrastrar los nodos para
                    ajustar su posición.
                </li>

                <li>
                    Los nodos solamente pueden estar
                    dentro de la zona.
                </li>

                <li>
                    El plano de la zona sirve como
                    referencia visual para dibujar los
                    caminos internos.
                </li>
            </ul>
        </div>

        <!-- Nodo seleccionado -->
        <div
            v-if="!readonly"
            class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="text-sm">
                <span
                    class="text-gray-500 dark:text-gray-400"
                >
                    Nodo seleccionado:
                </span>

                <span
                    v-if="selectedNodeId !== null"
                    class="ml-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{ selectedNodeId }}
                </span>

                <span
                    v-else
                    class="ml-2 text-gray-500 dark:text-gray-400"
                >
                    Ninguno
                </span>
            </div>

            <button
                v-if="selectedNodeId !== null"
                type="button"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                @click="selectedNodeId = null"
            >
                Quitar selección
            </button>
        </div>

        <!-- Mapa -->
        <div
            ref="mapContainer"
            class="h-[600px] w-full overflow-hidden rounded-xl border border-gray-300 dark:border-gray-600"
        />
    </div>
</template>

<style>
.zoo-map-plan-image {
    pointer-events: none !important;
}

.zoo-map-reference-marker,
.zoo-map-species-marker,
.zoo-map-path-node {
    background: transparent;
    border: none;
}
</style>