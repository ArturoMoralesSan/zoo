<script setup lang="ts">
import L from 'leaflet';
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
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

interface SpeciesImage {
    id: number;
    species_id: number;
    type: string;
    path: string;
    alt_text: string | null;
    is_active?: boolean;
    sort_order?: number;
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
        images?: SpeciesImage[];
    } | null;
}

const props = withDefaults(
    defineProps<{
        coordinates: PathData | PathNode[] | null;
        readonly?: boolean;
        zoneGeometry?: GeoJsonGeometry | null;
        mapImage?: string | null;
        mapImageBounds?: MapImageBounds | null;
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

const mapContainer = ref<HTMLElement | null>(null);

let map: L.Map | null = null;
let zoneLayer: L.GeoJSON | null = null;
let imageLayer: L.ImageOverlay | null = null;
let referenceLayer: L.LayerGroup | null = null;
let edgesLayer: L.LayerGroup | null = null;
let nodesLayer: L.LayerGroup | null = null;

const selectedNodeId = ref<number | null>(null);

let originalDragPosition: L.LatLng | null = null;

/*
|--------------------------------------------------------------------------
| Normalización
|--------------------------------------------------------------------------
*/

function normalizeCoordinates(
    value: PathData | PathNode[] | null,
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
                from: nodes[index - 1].id,
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
            ? value.nodes.map(
                  (node) => ({
                      id: Number(node.id),
                      lat: Number(node.lat),
                      lng: Number(node.lng),
                  }),
              )
            : [],

        edges: Array.isArray(value.edges)
            ? value.edges.map(
                  (edge) => ({
                      from: Number(edge.from),
                      to: Number(edge.to),
                  }),
              )
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
    geometry: GeoJsonGeometry | null,
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
                    feature.geometry ??
                        null,
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
                (node) =>
                    node.id,
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
            (
                edge.from ===
                    firstId &&
                edge.to ===
                    secondId
            ) ||
            (
                edge.from ===
                    secondId &&
                edge.to ===
                    firstId
            ),
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
| Utilidades HTML
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

/*
|--------------------------------------------------------------------------
| URL de MapMarker
|--------------------------------------------------------------------------
*/

function getMarkerIconUrl(
    icon: string | null,
): string {
    if (
        !icon ||
        !icon.trim()
    ) {
        return '/storage/markers/poi.svg';
    }

    const value =
        icon.trim();

    if (
        value.startsWith(
            'http://',
        ) ||
        value.startsWith(
            'https://',
        )
    ) {
        return value;
    }

    if (
        value.startsWith(
            'data:image/',
        )
    ) {
        return value;
    }

    if (
        value.startsWith(
            'blob:',
        )
    ) {
        return value;
    }

    if (
        value.startsWith('/')
    ) {
        return value;
    }

    if (
        value.startsWith(
            'storage/',
        )
    ) {
        return `/${value}`;
    }

    if (
        value.startsWith(
            'markers/',
        )
    ) {
        return `/storage/${value}`;
    }

    if (
        value.startsWith(
            'map-markers/',
        )
    ) {
        return `/storage/${value}`;
    }

    return `/storage/markers/${value}`;
}

function getMarkerIconHtml(
    icon: string | null,
): string {
    const imageUrl =
        getMarkerIconUrl(icon);

    return `
        <img
            src="${escapeHtml(
                imageUrl,
            )}"
            alt=""
            draggable="false"
            class="zoo-map-marker-image"
            onerror="
                this.style.display='none';
                this.nextElementSibling.style.display='flex';
            "
        />

        <span
            class="zoo-map-marker-fallback"
            style="
                display:none;
                width:22px;
                height:22px;
                align-items:center;
                justify-content:center;
                font-size:17px;
                line-height:1;
            "
        >
            📍
        </span>
    `;
}

/*
|--------------------------------------------------------------------------
| Thumbnail de especie
|--------------------------------------------------------------------------
*/

function getSpeciesThumbnail(
    item: SpeciesLocation,
): string | null {
    const images =
        item.species?.images ?? [];

    /**
     * Buscamos específicamente thumbnail.
     *
     * Aunque el backend ya filtra type=thumbnail,
     * dejamos la validación aquí también para mayor
     * seguridad.
     */
    const thumbnail =
        images.find(
            (image) =>
                image.type ===
                'thumbnail',
        ) ??
        images[0] ??
        null;

    if (
        !thumbnail ||
        !thumbnail.path ||
        !thumbnail.path.trim()
    ) {
        return null;
    }

    const value =
        thumbnail.path.trim();

    /**
     * URL absoluta.
     */
    if (
        value.startsWith(
            'http://',
        ) ||
        value.startsWith(
            'https://',
        )
    ) {
        return value;
    }

    /**
     * Data URI.
     */
    if (
        value.startsWith(
            'data:image/',
        )
    ) {
        return value;
    }

    /**
     * Blob.
     */
    if (
        value.startsWith(
            'blob:',
        )
    ) {
        return value;
    }

    /**
     * Ruta absoluta.
     *
     * Ejemplo:
     * /storage/species/leon.jpg
     */
    if (
        value.startsWith('/')
    ) {
        return value;
    }

    /**
     * Ya contiene storage/.
     *
     * Ejemplo:
     * storage/species/leon.jpg
     */
    if (
        value.startsWith(
            'storage/',
        )
    ) {
        return `/${value}`;
    }

    /**
     * Ruta normal almacenada por Laravel.
     *
     * Ejemplo:
     * species/leon.jpg
     *
     * Resultado:
     * /storage/species/leon.jpg
     */
    return `/storage/${value}`;
}

function getSpeciesThumbnailHtml(
    item: SpeciesLocation,
): string {
    const imageUrl =
        getSpeciesThumbnail(
            item,
        );

    if (!imageUrl) {
        return `
            <span
                class="zoo-map-species-fallback"
            >
                🐾
            </span>
        `;
    }

    const speciesName =
        item.species
            ?.common_name ??
        item.name;

    return `
        <img
            src="${escapeHtml(
                imageUrl,
            )}"
            alt="${escapeHtml(
                speciesName,
            )}"
            draggable="false"
            class="zoo-map-species-thumbnail"
            onerror="
                this.style.display='none';
                this.nextElementSibling.style.display='flex';
            "
        />

        <span
            class="zoo-map-species-fallback"
            style="
                display:none;
            "
        >
            🐾
        </span>
    `;
}

/*
|--------------------------------------------------------------------------
| Markers y especies
|--------------------------------------------------------------------------
*/

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
    |--------------------------------------------------------------------------
    | MapMarkers
    |--------------------------------------------------------------------------
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

            const iconHtml =
                getMarkerIconHtml(
                    item.icon,
                );

            const color =
                item.color ||
                '#7c3aed';

            const icon =
                L.divIcon({
                    className:
                        'zoo-map-reference-marker',

                    html: `
                        <div
                            class="zoo-map-marker-pin"
                            style="
                                width:32px;
                                height:32px;
                                border-radius:50%;
                                background:${escapeHtml(
                                    color,
                                )};
                                border:3px solid white;
                                box-shadow:0 2px 6px rgba(0,0,0,.35);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                overflow:hidden;
                                box-sizing:border-box;
                            "
                        >
                            ${iconHtml}
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
                        zIndexOffset: 5000,
                    },
                );

            let popup = `
                <div
                    style="
                        min-width:180px;
                    "
                >
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
                    <div
                        style="
                            margin-top:8px;
                        "
                    >
                        ${escapeHtml(
                            item.description,
                        )}
                    </div>
                `;
            }

            popup += `
                </div>
            `;

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
    |--------------------------------------------------------------------------
    | SpeciesLocation
    |--------------------------------------------------------------------------
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

            const speciesName =
                item.species
                    ?.common_name ??
                item.name;

            const thumbnailUrl =
                getSpeciesThumbnail(
                    item,
                );

            const thumbnailHtml =
                getSpeciesThumbnailHtml(
                    item,
                );

            const icon =
                L.divIcon({
                    className:
                        'zoo-map-species-marker',

                    html: `
                        <div
                            class="zoo-map-species-pin"
                        >
                            ${thumbnailHtml}
                        </div>
                    `,

                    iconSize: [
                        40,
                        40,
                    ],

                    iconAnchor: [
                        20,
                        20,
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
                        zIndexOffset: 4000,
                    },
                );

            /*
            |--------------------------------------------------------------------------
            | Popup de especie
            |--------------------------------------------------------------------------
            */

            let popup = `
                <div
                    class="zoo-map-species-popup"
                    style="
                        min-width:220px;
                        max-width:280px;
                    "
                >
            `;

            if (thumbnailUrl) {
                popup += `
                    <div
                        style="
                            width:100%;
                            height:120px;
                            margin-bottom:10px;
                            border-radius:10px;
                            overflow:hidden;
                            background:#f3f4f6;
                        "
                    >
                        <img
                            src="${escapeHtml(
                                thumbnailUrl,
                            )}"
                            alt="${escapeHtml(
                                speciesName,
                            )}"
                            style="
                                width:100%;
                                height:100%;
                                object-fit:cover;
                                display:block;
                            "
                            onerror="
                                this.style.display='none';
                            "
                        />
                    </div>
                `;
            }

            popup += `
                <strong
                    style="
                        font-size:16px;
                    "
                >
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
                    <div
                        style="
                            margin-top:8px;
                            font-size:13px;
                            color:#4b5563;
                        "
                    >
                        ${escapeHtml(
                            item.description,
                        )}
                    </div>
                `;
            }

            popup += `
                </div>
            `;

            marker.bindPopup(
                popup,
            );

            marker.bindTooltip(
                speciesName,
                {
                    direction: 'top',
                    offset: [
                        0,
                        -20,
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
        new Map<number, PathNode>();

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
                                width:28px;
                                height:28px;
                                border-radius:50%;
                                background:${
                                    selected
                                        ? '#dc2626'
                                        : '#2563eb'
                                };
                                border:3px solid white;
                                box-shadow:0 2px 6px rgba(0,0,0,.35);
                                display:flex;
                                align-items:center;
                                justify-content:center;
                                color:white;
                                font-size:12px;
                                font-weight:700;
                                cursor:${
                                    props.readonly
                                        ? 'default'
                                        : 'pointer'
                                };
                                box-sizing:border-box;
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

                        zIndexOffset: 10000,
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

        pane.style.pointerEvents =
            'auto';
    }
}

function drawMap(): void {
    if (!map) {
        return;
    }

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

        if (nodes.length > 0) {
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

            if (bounds.isValid()) {
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

        if (zoneLayer) {
            const bounds =
                zoneLayer.getBounds();

            if (bounds.isValid()) {
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

        if (imageLayer) {
            const bounds =
                imageLayer.getBounds();

            if (bounds.isValid()) {
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
        new Map<number, PathNode>();

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
        (latitude1 * Math.PI) /
        180;

    const latitude2Radians =
        (latitude2 * Math.PI) /
        180;

    const deltaLatitude =
        ((latitude2 - latitude1) *
            Math.PI) /
        180;

    const deltaLongitude =
        ((longitude2 - longitude1) *
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

    return earthRadius * c;
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
    (value) => {
        console.log(
            'MapMarkers recibidos:',
            value,
        );

        console.log(
            'MapMarker icons:',
            value.map(
                (marker) => ({
                    id: marker.id,
                    name: marker.name,
                    icon: marker.icon,
                    iconUrl:
                        getMarkerIconUrl(
                            marker.icon,
                        ),
                    color: marker.color,
                }),
            ),
        );

        drawMap();
    },
    {
        deep: true,
        immediate: true,
    },
);

watch(
    () => props.speciesLocations,
    (value) => {
        console.log(
            'SpeciesLocations recibidas:',
            value,
        );

        console.log(
            'Species thumbnails:',
            value.map(
                (item) => ({
                    id: item.id,

                    species:
                        item.species
                            ?.common_name,

                    images:
                        item.species
                            ?.images,

                    thumbnail:
                        getSpeciesThumbnail(
                            item,
                        ),
                }),
            ),
        );

        drawMap();
    },
    {
        deep: true,
        immediate: true,
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

    map =
        L.map(
            mapContainer.value,
            {
                zoomControl: true,
                attributionControl: true,
            },
        );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution:
                '&copy; OpenStreetMap contributors',
            maxZoom: 22,
        },
    ).addTo(map);

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
    background: transparent !important;
    border: none !important;
}

.zoo-map-reference-marker {
    width: 32px !important;
    height: 32px !important;
    pointer-events: auto !important;
}

.zoo-map-reference-marker img {
    width: 22px !important;
    height: 22px !important;
    object-fit: contain !important;
    display: block;
    pointer-events: none;
}

.zoo-map-reference-marker .zoo-map-marker-pin {
    pointer-events: none;
}

.zoo-map-reference-marker span {
    pointer-events: none;
}

/*
|--------------------------------------------------------------------------
| Species marker
|--------------------------------------------------------------------------
*/

.zoo-map-species-marker {
    width: 40px !important;
    height: 40px !important;
    pointer-events: auto !important;
}

.zoo-map-species-pin {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    border: 3px solid #16a34a;
    box-shadow: 0 2px 7px rgba(0, 0, 0, 0.35);

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 0;
    margin: 0;

    overflow: hidden;
    box-sizing: border-box;
}

.zoo-map-species-thumbnail {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
}

.zoo-map-species-fallback {
    width: 30px !important;
    height: 30px !important;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 0;
    margin: 0;

    font-size: 17px;
    line-height: 30px;
    text-align: center;

    box-sizing: border-box;
    pointer-events: none;
}

.zoo-map-path-node {
    width: 28px !important;
    height: 28px !important;
    pointer-events: auto !important;
}
</style>