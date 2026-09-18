<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
} from 'vue'
import { zone } from '@/actions/App/Http/Controllers/MapController'

interface MapImageBounds {
    north: number
    south: number
    east: number
    west: number
}

interface GeoJsonGeometry {
    type:
        | 'Polygon'
        | 'MultiPolygon'
        | 'Feature'
        | 'FeatureCollection'
    coordinates?: unknown
    geometry?: GeoJsonGeometry | null
    features?: Array<{
        geometry?: GeoJsonGeometry | null
    }>
}

interface Zone {
    id: number
    name: string
    description?: string | null
    type?: string | null
    geometry?: GeoJsonGeometry | null
    map_image?: string | null
    map_image_bounds?: MapImageBounds | null
}

interface MapMarker {
    id: number
    zone_id: number
    name: string
    description?: string | null
    type?: string | null
    latitude: number
    longitude: number
    icon?: string | null
    color?: string | null
}

interface SpeciesImage {
    id: number
    species_id: number
    type: string
    path: string
    alt_text?: string | null
    sort_order?: number
}

interface Species {
    id: number
    common_name: string
    scientific_name?: string | null
    description?: string | null
    images?: SpeciesImage[]
}

interface SpeciesLocation {
    id: number
    species_id: number
    zone_id: number
    name?: string | null
    latitude: number
    longitude: number
    description?: string | null
    is_active: boolean
    species?: Species | null
}

interface PathNode {
    id: number
    lat: number
    lng: number
}

interface PathEdge {
    from: number
    to: number
}

interface PathCoordinates {
    nodes: PathNode[]
    edges: PathEdge[]
}

interface MapPath {
    id: number
    zone_id: number
    name: string
    description?: string | null
    coordinates: PathCoordinates
    distance?: number | null
    estimated_time?: number | null
    is_active: boolean
    order: number
}

interface ZoneResponse {
    zone: Zone
    markers: MapMarker[]
    speciesLocations: SpeciesLocation[]
    paths: MapPath[]
}

interface GraphNode {
    id: string
    lat: number
    lng: number
}

interface GraphEdge {
    to: string
    distance: number
}

interface ProjectedPoint {
    point: L.LatLng
    pathId: number
    fromId: string
    toId: string
    distance: number
}

interface RouteResult {
    points: L.LatLng[]
    distance: number
}

const props = defineProps<{
    zones: Zone[]
}>()

const mapContainer = ref<HTMLElement | null>(null)

const selectedZoneId = ref<number | null>(
    props.zones.length > 0
        ? props.zones[0].id
        : null,
)

const selectedZone = ref<Zone | null>(null)

const markers = ref<MapMarker[]>([])
const speciesLocations = ref<SpeciesLocation[]>([])
const paths = ref<MapPath[]>([])

const loading = ref(false)
const error = ref<string | null>(null)
const mapReady = ref(false)

const selectedDestination = ref<{
    type: 'marker' | 'species'
    id: number
    name: string
    latitude: number
    longitude: number
    description?: string | null
} | null>(null)

const routeDistance = ref<number | null>(null)
const routeTime = ref<number | null>(null)

let map: L.Map | null = null
let mapImageLayer: L.ImageOverlay | null = null
let zoneLayer: L.LayerGroup | null = null
let pathLayer: L.LayerGroup | null = null
let markerLayer: L.LayerGroup | null = null
let speciesLayer: L.LayerGroup | null = null

let userMarker: L.CircleMarker | null = null
let accuracyCircle: L.Circle | null = null
let destinationMarker: L.Marker | null = null
let routeLayer: L.LayerGroup | null = null

let watchId: number | null = null

const WALKING_SPEED = 1.4

const hasData = computed(() => {
    return (
        markers.value.length > 0 ||
        speciesLocations.value.length > 0 ||
        paths.value.length > 0
    )
})

const activeZoneName = computed(() => {
    return selectedZone.value?.name ?? ''
})

const formattedRouteDistance = computed(() => {
    if (routeDistance.value === null) {
        return ''
    }

    if (routeDistance.value < 1000) {
        return `${Math.round(routeDistance.value)} m`
    }

    return `${(routeDistance.value / 1000).toFixed(2)} km`
})

const formattedRouteTime = computed(() => {
    if (routeTime.value === null) {
        return ''
    }

    const minutes = Math.max(
        1,
        Math.round(routeTime.value / 60),
    )

    if (minutes < 60) {
        return `${minutes} min`
    }

    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60

    return remainingMinutes > 0
        ? `${hours} h ${remainingMinutes} min`
        : `${hours} h`
})

function normalizeImageUrl(
    path: string | null | undefined,
): string | null {
    if (!path || !path.trim()) {
        return null
    }

    const value = path.trim()

    if (
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('data:image/') ||
        value.startsWith('blob:')
    ) {
        return value
    }

    if (value.startsWith('/')) {
        return value
    }

    if (value.startsWith('storage/')) {
        return `/${value}`
    }

    return `/storage/${value}`
}

function getMarkerIconUrl(
    icon: string | null | undefined,
): string {
    if (!icon || !icon.trim()) {
        return ''
    }

    const value = icon.trim()

    if (
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('data:image/') ||
        value.startsWith('blob:')
    ) {
        return value
    }

    if (value.startsWith('/')) {
        return value
    }

    if (value.startsWith('storage/')) {
        return `/${value}`
    }

    if (value.startsWith('markers/')) {
        return `/storage/${value}`
    }

    if (value.startsWith('map-markers/')) {
        return `/storage/${value}`
    }

    return `/storage/markers/${value}`
}

function getSpeciesThumbnail(
    item: SpeciesLocation,
): string | null {
    const image =
        item.species?.images?.find(
            (image) =>
                image.type === 'thumbnail' &&
                image.path?.trim(),
        ) ??
        item.species?.images?.find(
            (image) => image.path?.trim(),
        ) ??
        null

    return normalizeImageUrl(image?.path)
}

function createMarkerIcon(
    marker: MapMarker,
): L.DivIcon {
    const color =
        marker.color?.trim() || '#2563eb'

    const iconUrl = getMarkerIconUrl(
        marker.icon,
    )

    const iconContent = iconUrl
        ? `
            <img
                src="${iconUrl}"
                class="zoo-map-marker-image"
                alt=""
                onerror="
                    this.style.display='none';
                    this.nextElementSibling.style.display='flex';
                "
            />
        `
        : ''

    return L.divIcon({
        className:
            'zoo-map-marker-icon-wrapper',

        html: `
            <div
                class="zoo-map-marker-pin"
                style="--marker-color: ${color};"
            >
                ${iconContent}

                <span
                    class="zoo-map-marker-fallback"
                    style="display:${iconUrl ? 'none' : 'flex'};"
                >
                    📍
                </span>
            </div>
        `,

        iconSize: [40, 40],
        iconAnchor: [20, 20],
        popupAnchor: [0, -20],
    })
}

function createSpeciesIcon(
    item: SpeciesLocation,
): L.DivIcon {
    const thumbnail =
        getSpeciesThumbnail(item)

    if (thumbnail) {
        return L.divIcon({
            className:
                'zoo-map-species-icon-wrapper',

            html: `
                <div class="zoo-map-species-pin">
                    <img
                        src="${thumbnail}"
                        class="zoo-map-species-image"
                        alt=""
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    />

                    <span
                        class="zoo-map-species-fallback"
                        style="display:none;"
                    >
                        🐾
                    </span>
                </div>
            `,

            iconSize: [44, 44],
            iconAnchor: [22, 22],
            popupAnchor: [0, -22],
        })
    }

    return L.divIcon({
        className:
            'zoo-map-species-icon-wrapper',

        html: `
            <div class="zoo-map-species-pin">
                <span class="zoo-map-species-fallback">
                    🐾
                </span>
            </div>
        `,

        iconSize: [44, 44],
        iconAnchor: [22, 22],
        popupAnchor: [0, -22],
    })
}

function createDestinationIcon(): L.DivIcon {
    return L.divIcon({
        className:
            'zoo-map-destination-icon-wrapper',

        html: `
            <div class="zoo-map-destination-pin">
                <span>📍</span>
            </div>
        `,

        iconSize: [42, 42],
        iconAnchor: [21, 21],
        popupAnchor: [0, -21],
    })
}

function createPanes() {
    if (!map) {
        return
    }

    const imagePane =
        map.getPane('zooMapImagePane') ??
        map.createPane('zooMapImagePane')

    imagePane.style.zIndex = '250'

    const zonePane =
        map.getPane('zooMapZonePane') ??
        map.createPane('zooMapZonePane')

    zonePane.style.zIndex = '350'

    const pathPane =
        map.getPane('zooMapPathPane') ??
        map.createPane('zooMapPathPane')

    pathPane.style.zIndex = '400'

    const routePane =
        map.getPane('zooMapRoutePane') ??
        map.createPane('zooMapRoutePane')

    routePane.style.zIndex = '500'

    const markerPane =
        map.getPane('zooMapMarkerPane') ??
        map.createPane('zooMapMarkerPane')

    markerPane.style.zIndex = '600'

    const tooltipPane =
        map.getPane('tooltipPane')

    if (tooltipPane) {
        tooltipPane.style.zIndex = '1000'
    }

    const popupPane =
        map.getPane('popupPane')

    if (popupPane) {
        popupPane.style.zIndex = '1100'
    }
}

function clearRoute() {
    routeLayer?.clearLayers()
    routeLayer = null

    routeDistance.value = null
    routeTime.value = null
}

function clearMapLayers() {
    mapImageLayer?.remove()
    mapImageLayer = null

    zoneLayer?.clearLayers()
    pathLayer?.clearLayers()
    markerLayer?.clearLayers()
    speciesLayer?.clearLayers()

    clearRoute()

    destinationMarker?.remove()
    destinationMarker = null

    userMarker?.remove()
    userMarker = null

    accuracyCircle?.remove()
    accuracyCircle = null
}

function drawMapImage() {
    if (
        !map ||
        !selectedZone.value?.map_image ||
        !selectedZone.value?.map_image_bounds
    ) {
        return
    }

    const imageUrl =
        normalizeImageUrl(
            selectedZone.value.map_image,
        )

    if (!imageUrl) {
        return
    }

    const boundsData =
        selectedZone.value.map_image_bounds

    const bounds:
        L.LatLngBoundsExpression = [
            [
                boundsData.south,
                boundsData.west,
            ],
            [
                boundsData.north,
                boundsData.east,
            ],
        ]

    mapImageLayer =
        L.imageOverlay(
            imageUrl,
            bounds,
            {
                pane:
                    'zooMapImagePane',
                opacity: 1,
                interactive: false,
            },
        ).addTo(map)
}

function geometryToLayer(
    geometry: GeoJsonGeometry,
): L.Layer | null {
    if (!map) {
        return null
    }

    if (
        geometry.type === 'Feature' ||
        geometry.type ===
            'FeatureCollection'
    ) {
        return L.geoJSON(
            geometry as any,
            {
                pane:
                    'zooMapZonePane',

                style: {
                    color: '#16a34a',
                    weight: 3,
                    fillColor:
                        '#22c55e',
                    fillOpacity: 0.08,
                },
            },
        )
    }

    if (
        geometry.type === 'Polygon' ||
        geometry.type ===
            'MultiPolygon'
    ) {
        return L.geoJSON(
            {
                type: 'Feature',
                properties: {},
                geometry:
                    geometry as any,
            } as any,
            {
                pane:
                    'zooMapZonePane',

                style: {
                    color: '#16a34a',
                    weight: 3,
                    fillColor:
                        '#22c55e',
                    fillOpacity: 0.08,
                },
            },
        )
    }

    return null
}

function drawZone() {
    if (
        !selectedZone.value?.geometry
    ) {
        return
    }

    const layer =
        geometryToLayer(
            selectedZone.value.geometry,
        )

    if (!layer) {
        return
    }

    zoneLayer =
        L.layerGroup([
            layer,
        ]).addTo(map!)
}

/**
 * Dibuja únicamente los segmentos
 * que existen realmente en la base de datos.
 */
function drawPaths() {
    if (!map) {
        return
    }

    pathLayer =
        L.layerGroup().addTo(map)

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const edges =
            path.coordinates?.edges ?? []

        const nodeMap =
            new Map<number, PathNode>()

        for (const node of nodes) {
            const lat = Number(node.lat)
            const lng = Number(node.lng)

            if (
                Number.isFinite(lat) &&
                Number.isFinite(lng)
            ) {
                nodeMap.set(
                    Number(node.id),
                    {
                        ...node,
                        lat,
                        lng,
                    },
                )
            }
        }

        for (const edge of edges) {
            const from =
                nodeMap.get(
                    Number(edge.from),
                )

            const to =
                nodeMap.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

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
                    pane:
                        'zooMapPathPane',

                    color:
                        '#ffffff',

                    weight: 6,
                    opacity: 0.95,

                    lineCap:
                        'round',

                    lineJoin:
                        'round',

                    interactive: false,
                },
            ).addTo(
                pathLayer,
            )
        }
    }
}

/**
 * Construye el grafo.
 *
 * Cada nodo conserva el ID del camino:
 *
 * camino:nodo
 */
function buildGraph(): Map<
    string,
    GraphNode
> {
    const graph =
        new Map<
            string,
            GraphNode
        >()

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        for (const node of nodes) {
            const lat =
                Number(node.lat)

            const lng =
                Number(node.lng)

            if (
                !Number.isFinite(lat) ||
                !Number.isFinite(lng)
            ) {
                continue
            }

            const id =
                `${path.id}:${node.id}`

            graph.set(
                id,
                {
                    id,
                    lat,
                    lng,
                },
            )
        }
    }

    return graph
}

/**
 * Construye las conexiones reales.
 *
 * Los edges de BD son bidireccionales.
 *
 * Caminos diferentes solamente se conectan
 * cuando sus nodos están prácticamente
 * en el mismo punto.
 */
function buildGraphEdges(
    graph: Map<string, GraphNode>,
): Map<string, GraphEdge[]> {
    const edges =
        new Map<
            string,
            GraphEdge[]
        >()

    for (const id of graph.keys()) {
        edges.set(id, [])
    }

    /*
     * Edges reales de cada camino.
     */
    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const pathEdges =
            path.coordinates?.edges ?? []

        const nodeMap =
            new Map<number, PathNode>()

        for (const node of nodes) {
            const lat =
                Number(node.lat)

            const lng =
                Number(node.lng)

            if (
                Number.isFinite(lat) &&
                Number.isFinite(lng)
            ) {
                nodeMap.set(
                    Number(node.id),
                    {
                        ...node,
                        lat,
                        lng,
                    },
                )
            }
        }

        for (const edge of pathEdges) {
            const from =
                nodeMap.get(
                    Number(edge.from),
                )

            const to =
                nodeMap.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

            const fromId =
                `${path.id}:${from.id}`

            const toId =
                `${path.id}:${to.id}`

            const distance =
                L.latLng(
                    from.lat,
                    from.lng,
                ).distanceTo(
                    L.latLng(
                        to.lat,
                        to.lng,
                    ),
                )

            if (!edges.has(fromId)) {
                edges.set(
                    fromId,
                    [],
                )
            }

            if (!edges.has(toId)) {
                edges.set(
                    toId,
                    [],
                )
            }

            edges.get(fromId)!.push({
                to: toId,
                distance,
            })

            edges.get(toId)!.push({
                to: fromId,
                distance,
            })
        }
    }

    /*
     * Unión entre caminos diferentes.
     *
     * SOLO si los nodos están prácticamente
     * en el mismo lugar.
     *
     * Esto evita crear atajos artificiales.
     */
    const graphEntries =
        Array.from(
            graph.values(),
        )

    for (
        let i = 0;
        i < graphEntries.length;
        i++
    ) {
        for (
            let j = i + 1;
            j < graphEntries.length;
            j++
        ) {
            const a =
                graphEntries[i]

            const b =
                graphEntries[j]

            const pathA =
                a.id.split(':')[0]

            const pathB =
                b.id.split(':')[0]

            if (
                pathA === pathB
            ) {
                continue
            }

            const distance =
                L.latLng(
                    a.lat,
                    a.lng,
                ).distanceTo(
                    L.latLng(
                        b.lat,
                        b.lng,
                    ),
                )

            /*
             * 1.5 metros.
             *
             * Solo se considera unión cuando
             * los nodos prácticamente coinciden.
             */
            if (distance <= 1.5) {
                edges.get(a.id)?.push({
                    to: b.id,
                    distance,
                })

                edges.get(b.id)?.push({
                    to: a.id,
                    distance,
                })
            }
        }
    }

    return edges
}

/**
 * Calcula el punto más cercano
 * SOBRE un segmento real.
 */
function projectPointToSegment(
    point: L.LatLng,
    segmentA: L.LatLng,
    segmentB: L.LatLng,
): L.LatLng {
    const latRad =
        (point.lat *
            Math.PI) /
        180

    const cosLat =
        Math.cos(latRad)

    const x =
        point.lng *
        cosLat

    const y =
        point.lat

    const ax =
        segmentA.lng *
        cosLat

    const ay =
        segmentA.lat

    const bx =
        segmentB.lng *
        cosLat

    const by =
        segmentB.lat

    const dx =
        bx - ax

    const dy =
        by - ay

    const lengthSquared =
        dx * dx +
        dy * dy

    if (
        lengthSquared === 0
    ) {
        return segmentA
    }

    let t =
        (
            (x - ax) * dx +
            (y - ay) * dy
        ) /
        lengthSquared

    t =
        Math.max(
            0,
            Math.min(
                1,
                t,
            ),
        )

    const projectedX =
        ax +
        t * dx

    const projectedY =
        ay +
        t * dy

    return L.latLng(
        projectedY,
        projectedX /
            cosLat,
    )
}

/**
 * Busca el segmento REAL más cercano.
 */
function getNearestPathSegment(
    latitude: number,
    longitude: number,
): ProjectedPoint | null {
    const position =
        L.latLng(
            latitude,
            longitude,
        )

    let nearest:
        ProjectedPoint | null = null

    let nearestDistance =
        Infinity

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const pathEdges =
            path.coordinates?.edges ?? []

        const nodeMap =
            new Map<number, PathNode>()

        for (const node of nodes) {
            const lat =
                Number(node.lat)

            const lng =
                Number(node.lng)

            if (
                Number.isFinite(lat) &&
                Number.isFinite(lng)
            ) {
                nodeMap.set(
                    Number(node.id),
                    {
                        ...node,
                        lat,
                        lng,
                    },
                )
            }
        }

        for (const edge of pathEdges) {
            const from =
                nodeMap.get(
                    Number(edge.from),
                )

            const to =
                nodeMap.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

            const projected =
                projectPointToSegment(
                    position,
                    L.latLng(
                        from.lat,
                        from.lng,
                    ),
                    L.latLng(
                        to.lat,
                        to.lng,
                    ),
                )

            const distance =
                position.distanceTo(
                    projected,
                )

            if (
                distance <
                nearestDistance
            ) {
                nearestDistance =
                    distance

                nearest = {
                    point:
                        projected,

                    pathId:
                        path.id,

                    fromId:
                        `${path.id}:${from.id}`,

                    toId:
                        `${path.id}:${to.id}`,

                    distance,
                }
            }
        }
    }

    return nearest
}

function dijkstra(
    edges: Map<string, GraphEdge[]>,
    startId: string,
    endId: string,
): string[] {
    const distances =
        new Map<
            string,
            number
        >()

    const previous =
        new Map<
            string,
            string | null
        >()

    const unvisited =
        new Set<string>()

    for (const id of edges.keys()) {
        distances.set(
            id,
            id === startId
                ? 0
                : Infinity,
        )

        previous.set(
            id,
            null,
        )

        unvisited.add(id)
    }

    while (
        unvisited.size > 0
    ) {
        let currentId:
            | string
            | null = null

        let currentDistance =
            Infinity

        for (const id of unvisited) {
            const distance =
                distances.get(id) ??
                Infinity

            if (
                distance <
                currentDistance
            ) {
                currentDistance =
                    distance

                currentId = id
            }
        }

        if (
            currentId === null ||
            currentDistance === Infinity
        ) {
            break
        }

        unvisited.delete(
            currentId,
        )

        if (
            currentId === endId
        ) {
            break
        }

        const currentEdges =
            edges.get(
                currentId,
            ) ?? []

        for (const edge of currentEdges) {
            if (
                !unvisited.has(
                    edge.to,
                )
            ) {
                continue
            }

            const alternative =
                currentDistance +
                edge.distance

            const currentBest =
                distances.get(
                    edge.to,
                ) ?? Infinity

            if (
                alternative <
                currentBest
            ) {
                distances.set(
                    edge.to,
                    alternative,
                )

                previous.set(
                    edge.to,
                    currentId,
                )
            }
        }
    }

    /*
     * IMPORTANTE:
     * La comprobación correcta de una ruta
     * es mediante Infinity.
     */
    if (
        (
            distances.get(endId) ??
            Infinity
        ) === Infinity
    ) {
        return []
    }

    const route: string[] = []

    let current:
        | string
        | null = endId

    while (current) {
        route.unshift(current)

        if (
            current === startId
        ) {
            break
        }

        current =
            previous.get(
                current,
            ) ?? null
    }

    if (
        route[0] !== startId
    ) {
        return []
    }

    return route
}

function connectVirtualPoint(
    edges: Map<string, GraphEdge[]>,
    virtualId: string,
    projection: ProjectedPoint,
) {
    if (!edges.has(virtualId)) {
        edges.set(
            virtualId,
            [],
        )
    }

    const virtualToFrom =
        projection.point.distanceTo(
            graphNodeLatLng(
                projection.fromId,
            ),
        )

    const virtualToTo =
        projection.point.distanceTo(
            graphNodeLatLng(
                projection.toId,
            ),
        )

    edges.get(
        virtualId,
    )!.push({
        to:
            projection.fromId,
        distance:
            virtualToFrom,
    })

    edges.get(
        virtualId,
    )!.push({
        to:
            projection.toId,
        distance:
            virtualToTo,
    })

    if (
        !edges.has(
            projection.fromId,
        )
    ) {
        edges.set(
            projection.fromId,
            [],
        )
    }

    if (
        !edges.has(
            projection.toId,
        )
    ) {
        edges.set(
            projection.toId,
            [],
        )
    }

    edges.get(
        projection.fromId,
    )!.push({
        to: virtualId,
        distance:
            virtualToFrom,
    })

    edges.get(
        projection.toId,
    )!.push({
        to: virtualId,
        distance:
            virtualToTo,
    })
}

let currentGraph:
    Map<string, GraphNode> =
    new Map()

function graphNodeLatLng(
    id: string,
): L.LatLng {
    const node =
        currentGraph.get(id)

    if (!node) {
        return L.latLng(
            0,
            0,
        )
    }

    return L.latLng(
        node.lat,
        node.lng,
    )
}

/**
 * Calcula la ruta usando únicamente
 * los caminos reales.
 *
 * La ubicación GPS se proyecta sobre
 * el segmento real más cercano.
 *
 * El destino también se proyecta sobre
 * el segmento real más cercano.
 *
 * IMPORTANTE:
 *
 * La línea que se dibuja después NO incluye
 * la línea GPS -> camino ni camino -> destino.
 *
 * Por lo tanto la ruta visible permanece
 * completamente sobre los rieles existentes.
 */
function calculateDijkstraRoute(): RouteResult | null {
    if (
        !map ||
        !selectedDestination.value
    ) {
        return null
    }

    const userLocation =
        userMarker?.getLatLng()

    if (!userLocation) {
        return null
    }

    const graph =
        buildGraph()

    if (
        graph.size === 0
    ) {
        return null
    }

    currentGraph =
        graph

    const baseEdges =
        buildGraphEdges(
            graph,
        )

    /*
     * Clonamos el grafo para no modificar
     * permanentemente las conexiones reales.
     */
    const edges =
        new Map<
            string,
            GraphEdge[]
        >()

    for (
        const [
            id,
            graphEdges,
        ] of baseEdges
    ) {
        edges.set(
            id,
            graphEdges.map(
                edge => ({
                    ...edge,
                }),
            ),
        )
    }

    /*
     * Ubicación:
     * buscar segmento REAL más cercano.
     */
    const startProjection =
        getNearestPathSegment(
            userLocation.lat,
            userLocation.lng,
        )

    /*
     * Destino:
     * buscar segmento REAL más cercano.
     */
    const endProjection =
        getNearestPathSegment(
            selectedDestination.value.latitude,
            selectedDestination.value.longitude,
        )

    if (
        !startProjection ||
        !endProjection
    ) {
        return null
    }

    const START =
        '__START__'

    const END =
        '__END__'

    /*
     * Nodo virtual de inicio:
     * está exactamente sobre el riel.
     */
    connectVirtualPoint(
        edges,
        START,
        startProjection,
    )

    /*
     * Nodo virtual de destino:
     * está exactamente sobre el riel.
     */
    connectVirtualPoint(
        edges,
        END,
        endProjection,
    )

    /*
     * Si ambos están en el mismo segmento,
     * conectamos directamente los dos puntos.
     *
     * Esa línea pertenece exactamente
     * al mismo segmento real.
     */
    const sameSegment =
        startProjection.pathId ===
            endProjection.pathId &&
        (
            (
                startProjection.fromId ===
                    endProjection.fromId &&
                startProjection.toId ===
                    endProjection.toId
            ) ||
            (
                startProjection.fromId ===
                    endProjection.toId &&
                startProjection.toId ===
                    endProjection.fromId
            )
        )

    if (sameSegment) {
        const directDistance =
            startProjection.point.distanceTo(
                endProjection.point,
            )

        edges.get(START)!.push({
            to: END,
            distance:
                directDistance,
        })

        edges.get(END)!.push({
            to: START,
            distance:
                directDistance,
        })
    }

    const routeIds =
        dijkstra(
            edges,
            START,
            END,
        )

    if (
        routeIds.length === 0
    ) {
        return null
    }

    /*
     * AQUÍ ESTÁ EL CAMBIO IMPORTANTE.
     *
     * La ruta comienza en el punto proyectado
     * sobre el camino.
     *
     * NO comienza en userLocation.
     *
     * Así no se dibuja una diagonal nueva
     * desde la posición GPS hasta el riel.
     */
    const points: L.LatLng[] = []

    points.push(
        startProjection.point,
    )

    for (const id of routeIds) {
        if (
            id === START ||
            id === END
        ) {
            continue
        }

        const node =
            graph.get(id)

        if (!node) {
            continue
        }

        points.push(
            L.latLng(
                node.lat,
                node.lng,
            ),
        )
    }

    /*
     * La ruta termina en el punto proyectado
     * sobre el camino.
     *
     * NO termina directamente en el marker.
     */
    points.push(
        endProjection.point,
    )

    /*
     * Eliminamos puntos duplicados.
     */
    const cleanPoints:
        L.LatLng[] = []

    for (const point of points) {
        const previous =
            cleanPoints[
                cleanPoints.length - 1
            ]

        if (
            !previous ||
            previous.distanceTo(
                point,
            ) > 0.2
        ) {
            cleanPoints.push(
                point,
            )
        }
    }

    if (
        cleanPoints.length < 2
    ) {
        return null
    }

    /*
     * La distancia corresponde a la ruta
     * SOBRE LOS RIELES.
     */
    let totalDistance = 0

    for (
        let i = 1;
        i < cleanPoints.length;
        i++
    ) {
        totalDistance +=
            cleanPoints[
                i - 1
            ].distanceTo(
                cleanPoints[i],
            )
    }

    return {
        points:
            cleanPoints,

        distance:
            totalDistance,
    }
}

function drawDijkstraRoute() {
    if (
        !map ||
        !selectedDestination.value
    ) {
        return
    }

    clearRoute()

    const result =
        calculateDijkstraRoute()

    if (!result) {
        error.value =
            'No se encontró un camino conectado hasta este destino.'

        return
    }

    error.value = null

    routeDistance.value =
        result.distance

    routeTime.value =
        result.distance /
        WALKING_SPEED

    routeLayer =
        L.layerGroup().addTo(map)

    /*
     * Capa blanca.
     */
    L.polyline(
        result.points,
        {
            pane:
                'zooMapRoutePane',

            color:
                '#ffffff',

            weight: 13,
            opacity: 1,

            lineCap:
                'round',

            lineJoin:
                'round',

            interactive: false,
        },
    ).addTo(
        routeLayer,
    )

    /*
     * Ruta azul.
     */
    L.polyline(
        result.points,
        {
            pane:
                'zooMapRoutePane',

            color:
                '#2563eb',

            weight: 7,
            opacity: 1,

            lineCap:
                'round',

            lineJoin:
                'round',

            interactive: false,
        },
    ).addTo(
        routeLayer,
    )

    /*
     * Ajustamos el mapa solamente
     * a la ruta real.
     */
    const routeBounds =
        L.latLngBounds(
            result.points,
        )

    if (
        routeBounds.isValid()
    ) {
        map.fitBounds(
            routeBounds,
            {
                padding: [
                    70,
                    70,
                ],

                maxZoom: 19,

                animate: true,
            },
        )
    }
}

function drawMarkers() {
    if (!map) {
        return
    }

    markerLayer =
        L.layerGroup().addTo(map)

    for (const marker of markers.value) {
        const latitude =
            Number(marker.latitude)

        const longitude =
            Number(marker.longitude)

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude)
        ) {
            continue
        }

        const leafletMarker =
            L.marker(
                [
                    latitude,
                    longitude,
                ],
                {
                    pane:
                        'zooMapMarkerPane',

                    icon:
                        createMarkerIcon(
                            marker,
                        ),

                    zIndexOffset: 100,
                },
            )

        leafletMarker.bindPopup(`
            <div class="zoo-map-popup">
                <strong>
                    ${escapeHtml(marker.name)}
                </strong>

                ${
                    marker.type
                        ? `
                            <div class="zoo-map-popup-type">
                                ${escapeHtml(marker.type)}
                            </div>
                        `
                        : ''
                }

                ${
                    marker.description
                        ? `
                            <p>
                                ${escapeHtml(
                                    marker.description,
                                )}
                            </p>
                        `
                        : ''
                }
            </div>
        `)

        leafletMarker.on(
            'click',
            () => {
                selectedDestination.value = {
                    type: 'marker',
                    id: marker.id,
                    name: marker.name,
                    latitude,
                    longitude,
                    description:
                        marker.description,
                }

                drawDestination(
                    latitude,
                    longitude,
                    marker.name,
                )

                drawDijkstraRoute()
            },
        )

        leafletMarker.addTo(
            markerLayer!,
        )
    }
}

function drawSpeciesLocations() {
    if (!map) {
        return
    }

    speciesLayer =
        L.layerGroup().addTo(map)

    for (const item of speciesLocations.value) {
        const latitude =
            Number(item.latitude)

        const longitude =
            Number(item.longitude)

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude)
        ) {
            continue
        }

        const speciesName =
            item.name ||
            item.species
                ?.common_name ||
            'Especie'

        const scientificName =
            item.species
                ?.scientific_name

        const leafletMarker =
            L.marker(
                [
                    latitude,
                    longitude,
                ],
                {
                    pane:
                        'zooMapMarkerPane',

                    icon:
                        createSpeciesIcon(
                            item,
                        ),

                    zIndexOffset: 200,
                },
            )

        leafletMarker.bindPopup(`
            <div class="zoo-map-popup zoo-map-species-popup">
                <strong>
                    ${escapeHtml(speciesName)}
                </strong>

                ${
                    scientificName
                        ? `
                            <em>
                                ${escapeHtml(
                                    scientificName,
                                )}
                            </em>
                        `
                        : ''
                }

                ${
                    item.description ||
                    item.species
                        ?.description
                        ? `
                            <p>
                                ${escapeHtml(
                                    item.description ||
                                        item.species
                                            ?.description ||
                                        '',
                                )}
                            </p>
                        `
                        : ''
                }
            </div>
        `)

        leafletMarker.on(
            'click',
            () => {
                selectedDestination.value = {
                    type: 'species',
                    id: item.id,
                    name: speciesName,
                    latitude,
                    longitude,
                    description:
                        item.description ||
                        item.species
                            ?.description,
                }

                drawDestination(
                    latitude,
                    longitude,
                    speciesName,
                )

                drawDijkstraRoute()
            },
        )

        leafletMarker.addTo(
            speciesLayer!,
        )
    }
}

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
        )
}

function drawDestination(
    latitude: number,
    longitude: number,
    name: string,
) {
    if (!map) {
        return
    }

    destinationMarker?.remove()

    destinationMarker =
        L.marker(
            [
                latitude,
                longitude,
            ],
            {
                icon:
                    createDestinationIcon(),

                zIndexOffset: 1000,
            },
        )
            .bindTooltip(
                name,
            )
            .addTo(map)
}

function getAllMapPoints(): L.LatLng[] {
    const points: L.LatLng[] = []

    for (const marker of markers.value) {
        const lat =
            Number(marker.latitude)

        const lng =
            Number(marker.longitude)

        if (
            Number.isFinite(lat) &&
            Number.isFinite(lng)
        ) {
            points.push(
                L.latLng(
                    lat,
                    lng,
                ),
            )
        }
    }

    for (
        const species of speciesLocations.value
    ) {
        const lat =
            Number(species.latitude)

        const lng =
            Number(species.longitude)

        if (
            Number.isFinite(lat) &&
            Number.isFinite(lng)
        ) {
            points.push(
                L.latLng(
                    lat,
                    lng,
                ),
            )
        }
    }

    for (const path of paths.value) {
        for (
            const node of
            path.coordinates?.nodes ?? []
        ) {
            const lat =
                Number(node.lat)

            const lng =
                Number(node.lng)

            if (
                Number.isFinite(lat) &&
                Number.isFinite(lng)
            ) {
                points.push(
                    L.latLng(
                        lat,
                        lng,
                    ),
                )
            }
        }
    }

    return points
}

function getZoneBounds():
    L.LatLngBounds | null {
    if (
        selectedZone.value
            ?.map_image_bounds
    ) {
        const bounds =
            selectedZone.value
                .map_image_bounds

        return L.latLngBounds(
            [
                bounds.south,
                bounds.west,
            ],
            [
                bounds.north,
                bounds.east,
            ],
        )
    }

    if (
        selectedZone.value
            ?.geometry
    ) {
        const layer =
            geometryToLayer(
                selectedZone.value
                    .geometry,
            )

        if (layer) {
            const bounds = (
                layer as any
            ).getBounds?.()

            if (
                bounds?.isValid?.()
            ) {
                return bounds
            }
        }
    }

    return null
}

function fitMapToData() {
    if (!map) {
        return
    }

    const points =
        getAllMapPoints()

    if (
        points.length > 0
    ) {
        map.fitBounds(
            L.latLngBounds(
                points,
            ),
            {
                padding: [
                    40,
                    40,
                ],

                maxZoom: 19,
            },
        )

        return
    }

    const zoneBounds =
        getZoneBounds()

    if (
        zoneBounds?.isValid()
    ) {
        map.fitBounds(
            zoneBounds,
            {
                padding: [
                    30,
                    30,
                ],
            },
        )
    }
}

async function loadZone(
    zoneId: number,
) {
    if (!map) {
        return
    }

    loading.value = true
    error.value = null

    clearMapLayers()

    markers.value = []
    speciesLocations.value = []
    paths.value = []

    selectedDestination.value =
        null

    try {
        const response =
            await fetch(
                zone(zoneId).url,
                {
                    method: 'GET',

                    headers: {
                        Accept:
                            'application/json',

                        'X-Requested-With':
                            'XMLHttpRequest',
                    },

                    credentials:
                        'same-origin',
                },
            )

        if (!response.ok) {
            throw new Error(
                `HTTP ${response.status}`,
            )
        }

        const data =
            (await response.json()) as ZoneResponse

        if (!data.zone) {
            throw new Error(
                'El servidor no devolvió la zona.',
            )
        }

        selectedZone.value =
            data.zone

        markers.value =
            Array.isArray(
                data.markers,
            )
                ? data.markers
                : []

        speciesLocations.value =
            Array.isArray(
                data.speciesLocations,
            )
                ? data.speciesLocations
                : []

        paths.value =
            Array.isArray(
                data.paths,
            )
                ? data.paths
                : []

        console.log(
            'MAPA ZONA:',
            data.zone,
        )

        console.log(
            'MAP MARKERS:',
            markers.value,
        )

        console.log(
            'MAP SPECIES:',
            speciesLocations.value,
        )

        console.log(
            'MAP PATHS:',
            paths.value,
        )

        drawMapImage()
        drawZone()
        drawPaths()
        drawMarkers()
        drawSpeciesLocations()

        await nextTick()

        map.invalidateSize()

        fitMapToData()
    } catch (exception) {
        console.error(
            'Error cargando mapa:',
            exception,
        )

        error.value =
            'No fue posible cargar la información del mapa.'
    } finally {
        loading.value = false
    }
}

function initializeMap() {
    if (
        !mapContainer.value ||
        map
    ) {
        return
    }

    map = L.map(
        mapContainer.value,
        {
            zoomControl: true,
            attributionControl:
                true,
        },
    )

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 22,

            attribution:
                '&copy; OpenStreetMap contributors',
        },
    ).addTo(map)

    createPanes()

    mapReady.value = true

    if (
        selectedZoneId.value
    ) {
        loadZone(
            selectedZoneId.value,
        )
    }
}

function handleZoneChange() {
    if (
        selectedZoneId.value ===
        null
    ) {
        return
    }

    loadZone(
        selectedZoneId.value,
    )
}

function locateUser() {
    if (!map) {
        return
    }

    if (
        !navigator.geolocation
    ) {
        error.value =
            'Tu navegador no permite obtener tu ubicación.'

        return
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const latitude =
                position.coords
                    .latitude

            const longitude =
                position.coords
                    .longitude

            const accuracy =
                position.coords
                    .accuracy

            userMarker?.remove()
            accuracyCircle?.remove()

            userMarker =
                L.circleMarker(
                    [
                        latitude,
                        longitude,
                    ],
                    {
                        pane:
                            'zooMapMarkerPane',

                        radius: 9,

                        color:
                            '#ffffff',

                        weight: 3,

                        fillColor:
                            '#2563eb',

                        fillOpacity: 1,
                    },
                )
                    .bindTooltip(
                        'Mi ubicación',
                    )
                    .addTo(map!)

            accuracyCircle =
                L.circle(
                    [
                        latitude,
                        longitude,
                    ],
                    {
                        pane:
                            'zooMapPathPane',

                        radius:
                            accuracy,

                        color:
                            '#2563eb',

                        weight: 1,

                        fillColor:
                            '#2563eb',

                        fillOpacity:
                            0.1,

                        interactive:
                            false,
                    },
                ).addTo(map!)

            map!.setView(
                [
                    latitude,
                    longitude,
                ],
                Math.max(
                    map!.getZoom(),
                    18,
                ),
                {
                    animate: true,
                },
            )

            if (
                selectedDestination.value
            ) {
                drawDijkstraRoute()
            }
        },
        () => {
            error.value =
                'No fue posible obtener tu ubicación.'
        },
        {
            enableHighAccuracy:
                true,

            timeout: 15000,

            maximumAge: 5000,
        },
    )
}

onMounted(
    async () => {
        await nextTick()

        initializeMap()
    },
)

onBeforeUnmount(() => {
    if (
        watchId !== null
    ) {
        navigator.geolocation.clearWatch(
            watchId,
        )
    }

    map?.remove()

    map = null
})
</script>

<template>
    <div class="space-y-5">

        <div
            class="flex flex-col gap-4 rounded-2xl border bg-white p-5 shadow-sm md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold text-gray-900"
                >
                    Mapa del zoológico
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    Explora las zonas, caminos,
                    instalaciones y especies.
                </p>
            </div>

            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center"
            >
                <select
                    v-model="selectedZoneId"
                    class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    @change="handleZoneChange"
                >
                    <option
                        v-for="item in props.zones"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>

                <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    @click="locateUser"
                >
                    📍 Mi ubicación
                </button>
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ error }}
        </div>

        <div
            class="relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-100 shadow-sm"
        >
            <div
                ref="mapContainer"
                class="h-[650px] w-full"
            ></div>

            <div
                v-if="loading"
                class="absolute inset-0 z-[1000] flex items-center justify-center bg-white/70 backdrop-blur-sm"
            >
                <div
                    class="rounded-xl bg-white px-5 py-4 text-sm font-medium text-gray-700 shadow-lg"
                >
                    Cargando mapa...
                </div>
            </div>

            <div
                v-if="
                    !loading &&
                    selectedZone
                "
                class="absolute left-4 top-4 z-[900] max-w-xs rounded-xl bg-white/95 px-4 py-3 shadow-lg backdrop-blur"
            >
                <div
                    class="text-sm font-bold text-gray-900"
                >
                    {{ activeZoneName }}
                </div>

                <div
                    class="mt-1 text-xs text-gray-500"
                >
                    {{ markers.length }} marcadores ·
                    {{ speciesLocations.length }}
                    especies ·
                    {{ paths.length }} caminos
                </div>
            </div>

            <div
                v-if="
                    !loading &&
                    selectedDestination
                "
                class="absolute bottom-4 left-4 right-4 z-[900] max-w-md rounded-2xl bg-white p-4 shadow-xl"
            >
                <div
                    class="text-xs font-semibold uppercase tracking-wide text-blue-600"
                >
                    Destino
                </div>

                <div
                    class="mt-1 text-lg font-bold text-gray-900"
                >
                    {{ selectedDestination.name }}
                </div>

                <p
                    v-if="
                        selectedDestination.description
                    "
                    class="mt-1 text-sm text-gray-500"
                >
                    {{
                        selectedDestination.description
                    }}
                </p>

                <div
                    v-if="routeDistance !== null"
                    class="mt-3 grid grid-cols-2 gap-3"
                >
                    <div
                        class="rounded-xl bg-gray-50 p-3"
                    >
                        <div
                            class="text-xs text-gray-500"
                        >
                            Distancia
                        </div>

                        <div
                            class="mt-1 text-base font-bold text-gray-900"
                        >
                            {{ formattedRouteDistance }}
                        </div>
                    </div>

                    <div
                        class="rounded-xl bg-gray-50 p-3"
                    >
                        <div
                            class="text-xs text-gray-500"
                        >
                            Tiempo caminando
                        </div>

                        <div
                            class="mt-1 text-base font-bold text-gray-900"
                        >
                            {{ formattedRouteTime }}
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-3 text-sm font-semibold text-blue-600 hover:text-blue-800"
                    @click="
                        selectedDestination = null;
                        clearRoute();
                        destinationMarker?.remove();
                        destinationMarker = null;
                    "
                >
                    Cerrar
                </button>
            </div>
        </div>

        <div
            v-if="selectedZone"
            class="grid gap-4 md:grid-cols-4"
        >
            <div
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div
                    class="text-sm text-gray-500"
                >
                    Marcadores
                </div>

                <div
                    class="mt-1 text-2xl font-bold text-gray-900"
                >
                    {{ markers.length }}
                </div>
            </div>

            <div
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div
                    class="text-sm text-gray-500"
                >
                    Especies
                </div>

                <div
                    class="mt-1 text-2xl font-bold text-gray-900"
                >
                    {{ speciesLocations.length }}
                </div>
            </div>

            <div
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div
                    class="text-sm text-gray-500"
                >
                    Caminos
                </div>

                <div
                    class="mt-1 text-2xl font-bold text-gray-900"
                >
                    {{ paths.length }}
                </div>
            </div>

            <div
                class="rounded-2xl border bg-white p-5 shadow-sm"
            >
                <div
                    class="text-sm text-gray-500"
                >
                    Estado
                </div>

                <div
                    class="mt-1 text-2xl font-bold text-green-600"
                >
                    {{
                        hasData
                            ? 'Activo'
                            : 'Sin datos'
                    }}
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
:deep(.leaflet-container) {
    font-family: inherit;
    z-index: 0;
}

:deep(.leaflet-marker-icon) {
    background: transparent !important;
    border: 0 !important;
}

:deep(.zoo-map-marker-icon-wrapper),
:deep(.zoo-map-species-icon-wrapper),
:deep(.zoo-map-destination-icon-wrapper) {
    background: transparent !important;
    border: 0 !important;
}

:deep(.zoo-map-marker-pin) {
    position: relative;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    border: 3px solid var(--marker-color);
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-sizing: border-box;
}

:deep(.zoo-map-marker-image) {
    display: block;
    width: 25px;
    height: 25px;
    object-fit: contain;
}

:deep(.zoo-map-marker-fallback) {
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 20px;
    line-height: 1;
}

:deep(.zoo-map-species-pin) {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: white;
    border: 3px solid #16a34a;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35);
    overflow: hidden;
    box-sizing: border-box;
}

:deep(.zoo-map-species-image) {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

:deep(.zoo-map-species-fallback) {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    line-height: 1;
    pointer-events: none;
}

:deep(.zoo-map-destination-pin) {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: white;
    border: 3px solid #dc2626;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    box-sizing: border-box;
}

:deep(.leaflet-tooltip-pane) {
    z-index: 1000 !important;
}

:deep(.leaflet-popup-pane) {
    z-index: 1100 !important;
}

:deep(.zoo-map-popup strong) {
    display: block;
    font-size: 15px;
    margin-bottom: 3px;
}

:deep(.zoo-map-popup em) {
    display: block;
    margin-bottom: 5px;
    font-size: 12px;
    color: #6b7280;
}

:deep(.zoo-map-popup p) {
    margin: 6px 0 0;
    font-size: 12px;
    line-height: 1.4;
    color: #4b5563;
}

:deep(.zoo-map-popup-type) {
    font-size: 11px;
    color: #6b7280;
}
</style>
