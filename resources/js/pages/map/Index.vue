<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { zone } from '@/actions/App/Http/Controllers/MapController'

interface Zone {
    id: number
    name: string
    description?: string | null
    type?: string | null
    geometry?: GeoJsonGeometry | null
    map_image?: string | null
    map_image_bounds?: MapImageBounds | null
}

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

interface Species {
    id: number
    common_name: string
    scientific_name?: string | null
    description?: string | null
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

interface NearestPathResult {
    path: MapPath
    from: PathNode
    to: PathNode
    projectedLat: number
    projectedLng: number
    distance: number
}

interface Destination {
    type: 'marker' | 'species'
    id: number
    name: string
    latitude: number
    longitude: number
    description?: string | null
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

interface RouteResult {
    nodes: GraphNode[]
    distance: number
}

const props = defineProps<{
    zones: Zone[]
}>()

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

const locationStatus = ref<
    'idle' | 'requesting' | 'inside' | 'outside' | 'error'
>('idle')

const locationMessage = ref(
    'Activa tu ubicación para comenzar la navegación.',
)

const userLatitude = ref<number | null>(null)
const userLongitude = ref<number | null>(null)
const userAccuracy = ref<number | null>(null)

const nearestPath = ref<NearestPathResult | null>(null)

const selectedDestinationKey =
    ref<string>('')

const routeResult =
    ref<RouteResult | null>(null)

const navigationActive =
    ref(false)

const mapContainer =
    ref<HTMLElement | null>(null)

let map: L.Map | null = null

let zoneLayer: L.LayerGroup | null = null
let imageLayer: L.ImageOverlay | null = null
let pathLayer: L.LayerGroup | null = null
let markerLayer: L.LayerGroup | null = null
let speciesLayer: L.LayerGroup | null = null
let userLayer: L.LayerGroup | null = null
let nearestPathLayer: L.LayerGroup | null = null
let routeLayer: L.LayerGroup | null = null

let userMarker: L.CircleMarker | null = null
let accuracyCircle: L.Circle | null = null

let destinationMarker:
    L.Marker | null = null

let watchId: number | null = null
let firstLocationFix = true

const currentZone = computed(() => {
    return selectedZone.value
})

const selectedZoneName = computed(() => {
    return selectedZone.value?.name ??
        'Mapa del zoológico'
})

const locationStatusLabel = computed(() => {
    switch (locationStatus.value) {
        case 'requesting':
            return 'Solicitando ubicación...'

        case 'inside':
            return 'Dentro de la zona'

        case 'outside':
            return 'Fuera de la zona'

        case 'error':
            return 'Ubicación no disponible'

        default:
            return 'Ubicación desactivada'
    }
})

const locationStatusClass = computed(() => {
    switch (locationStatus.value) {
        case 'inside':
            return 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400'

        case 'outside':
            return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'

        case 'requesting':
            return 'border-yellow-500/30 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400'

        case 'error':
            return 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'

        default:
            return 'border-sidebar-border bg-background text-muted-foreground'
    }
})

const destinations = computed<Destination[]>(() => {
    const result: Destination[] = []

    for (const marker of markers.value) {
        result.push({
            type: 'marker',
            id: marker.id,
            name: marker.name,
            latitude: Number(marker.latitude),
            longitude: Number(marker.longitude),
            description: marker.description,
        })
    }

    for (
        const location of speciesLocations.value
    ) {
        result.push({
            type: 'species',
            id: location.id,
            name:
                location.name ||
                location.species?.common_name ||
                'Especie',
            latitude: Number(location.latitude),
            longitude: Number(location.longitude),
            description:
                location.description ||
                location.species?.description,
        })
    }

    return result.filter(
        (destination) =>
            Number.isFinite(destination.latitude) &&
            Number.isFinite(destination.longitude),
    )
})

const selectedDestination =
    computed<Destination | null>(() => {
        if (!selectedDestinationKey.value) {
            return null
        }

        return (
            destinations.value.find(
                (destination) =>
                    `${destination.type}-${destination.id}` ===
                    selectedDestinationKey.value,
            ) ?? null
        )
    })

const selectedDestinationDetails = computed(() => {
    const destination =
        selectedDestination.value

    if (!destination) {
        return null
    }

    if (destination.type === 'species') {
        const location =
            speciesLocations.value.find(
                (item) =>
                    item.id === destination.id,
            )

        return {
            type: 'species' as const,
            label: 'Especie',
            scientificName:
                location?.species?.scientific_name ??
                null,
        }
    }

    const marker =
        markers.value.find(
            (item) =>
                item.id === destination.id,
        )

    return {
        type: 'marker' as const,
        label:
            marker?.type ||
            'Punto de interés',
        scientificName: null,
    }
})

const straightLineDistanceToDestination =
    computed<number | null>(() => {
        if (
            !selectedDestination.value ||
            userLatitude.value === null ||
            userLongitude.value === null
        ) {
            return null
        }

        return distanceInMeters(
            userLatitude.value,
            userLongitude.value,
            selectedDestination.value.latitude,
            selectedDestination.value.longitude,
        )
    })

const formatDistance = (
    meters: number,
): string => {
    if (meters < 1000) {
        return `${Math.round(meters)} m`
    }

    return `${(meters / 1000).toFixed(2)} km`
}

const formatEstimatedTime = (
    meters: number,
): string => {
    const minutes = Math.max(
        1,
        Math.ceil(meters / 72),
    )

    if (minutes < 60) {
        return `${minutes} min`
    }

    const hours =
        Math.floor(minutes / 60)

    const remainingMinutes =
        minutes % 60

    if (remainingMinutes === 0) {
        return `${hours} h`
    }

    return `${hours} h ${remainingMinutes} min`
}

const getAssetUrl = (
    path: string | null | undefined,
): string | null => {
    if (!path) {
        return null
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path
    }

    return `/storage/${path}`
}

const normalizeGeometry = (
    geometry:
        | GeoJsonGeometry
        | null
        | undefined,
): GeoJsonGeometry | null => {
    if (!geometry) {
        return null
    }

    if (geometry.type === 'Feature') {
        return geometry.geometry ?? null
    }

    return geometry
}

const pointInRing = (
    latitude: number,
    longitude: number,
    ring: unknown,
): boolean => {
    if (!Array.isArray(ring)) {
        return false
    }

    let inside = false

    for (
        let i = 0, j = ring.length - 1;
        i < ring.length;
        j = i++
    ) {
        const pointI = ring[i]
        const pointJ = ring[j]

        if (
            !Array.isArray(pointI) ||
            !Array.isArray(pointJ) ||
            pointI.length < 2 ||
            pointJ.length < 2
        ) {
            continue
        }

        const lngI = Number(pointI[0])
        const latI = Number(pointI[1])

        const lngJ = Number(pointJ[0])
        const latJ = Number(pointJ[1])

        const intersects =
            latI > latitude !== latJ > latitude &&
            longitude <
                ((lngJ - lngI) *
                    (latitude - latI)) /
                    (latJ - latI) +
                    lngI

        if (intersects) {
            inside = !inside
        }
    }

    return inside
}

const pointInGeometry = (
    latitude: number,
    longitude: number,
    geometry:
        | GeoJsonGeometry
        | null
        | undefined,
): boolean => {
    if (!geometry) {
        return false
    }

    if (geometry.type === 'Feature') {
        return pointInGeometry(
            latitude,
            longitude,
            geometry.geometry,
        )
    }

    if (
        geometry.type === 'FeatureCollection'
    ) {
        return (
            geometry.features?.some(
                (feature) =>
                    pointInGeometry(
                        latitude,
                        longitude,
                        feature.geometry,
                    ),
            ) ?? false
        )
    }

    if (
        geometry.type === 'Polygon' &&
        Array.isArray(geometry.coordinates)
    ) {
        const rings =
            geometry.coordinates as unknown[]

        const outerRing = rings[0]

        if (
            !pointInRing(
                latitude,
                longitude,
                outerRing,
            )
        ) {
            return false
        }

        for (
            let i = 1;
            i < rings.length;
            i++
        ) {
            if (
                pointInRing(
                    latitude,
                    longitude,
                    rings[i],
                )
            ) {
                return false
            }
        }

        return true
    }

    if (
        geometry.type === 'MultiPolygon' &&
        Array.isArray(geometry.coordinates)
    ) {
        return geometry.coordinates.some(
            (polygon) =>
                pointInGeometry(
                    latitude,
                    longitude,
                    {
                        type: 'Polygon',
                        coordinates: polygon,
                    },
                ),
        )
    }

    return false
}

const getGeometryCoordinates = (
    geometry:
        | GeoJsonGeometry
        | null
        | undefined,
): unknown => {
    if (!geometry) {
        return null
    }

    if (geometry.type === 'Feature') {
        return (
            geometry.geometry?.coordinates ??
            null
        )
    }

    return geometry.coordinates ?? null
}

const geometryToLatLngs = (
    geometry:
        | GeoJsonGeometry
        | null
        | undefined,
):
    | L.LatLngExpression[]
    | L.LatLngExpression[][]
    | L.LatLngExpression[][][]
    | null => {
    const normalized =
        normalizeGeometry(geometry)

    if (!normalized) {
        return null
    }

    const coordinates =
        getGeometryCoordinates(normalized)

    if (!coordinates) {
        return null
    }

    if (normalized.type === 'Polygon') {
        const polygon =
            coordinates as unknown[]

        return polygon.map(
            (ring) =>
                (ring as number[][]).map(
                    ([lng, lat]) =>
                        [
                            lat,
                            lng,
                        ] as L.LatLngTuple,
                ),
        )
    }

    if (
        normalized.type ===
        'MultiPolygon'
    ) {
        const multiPolygon =
            coordinates as unknown[]

        return multiPolygon.map(
            (polygon) =>
                (polygon as unknown[]).map(
                    (ring) =>
                        (
                            ring as number[][]
                        ).map(
                            ([lng, lat]) =>
                                [
                                    lat,
                                    lng,
                                ] as L.LatLngTuple,
                        ),
                ),
        )
    }

    return null
}

const getGeometryBounds = (
    geometry:
        | GeoJsonGeometry
        | null
        | undefined,
): L.LatLngBounds | null => {
    const latLngs =
        geometryToLatLngs(geometry)

    if (!latLngs) {
        return null
    }

    const bounds =
        L.latLngBounds([])

    const addCoordinates = (
        value: unknown,
    ) => {
        if (
            Array.isArray(value) &&
            value.length === 2 &&
            typeof value[0] === 'number' &&
            typeof value[1] === 'number'
        ) {
            bounds.extend(
                value as L.LatLngTuple,
            )

            return
        }

        if (Array.isArray(value)) {
            for (const item of value) {
                addCoordinates(item)
            }
        }
    }

    addCoordinates(latLngs)

    return bounds.isValid()
        ? bounds
        : null
}

const clearMapLayers = () => {
    zoneLayer?.clearLayers()
    pathLayer?.clearLayers()
    markerLayer?.clearLayers()
    speciesLayer?.clearLayers()
    userLayer?.clearLayers()
    nearestPathLayer?.clearLayers()
    routeLayer?.clearLayers()

    if (imageLayer && map) {
        map.removeLayer(imageLayer)
        imageLayer = null
    }

    if (
        destinationMarker &&
        map
    ) {
        map.removeLayer(
            destinationMarker,
        )

        destinationMarker = null
    }

    userMarker = null
    accuracyCircle = null
}

const drawZone = () => {
    if (!zoneLayer) {
        return
    }

    zoneLayer.clearLayers()

    const geometry =
        selectedZone.value?.geometry

    const latLngs =
        geometryToLatLngs(geometry)

    if (!latLngs) {
        return
    }

    const polygon =
        L.polygon(
            latLngs as any,
            {
                pane: 'zooMapZonePane',
                color: '#16a34a',
                weight: 2,
                opacity: 0.9,
                fillOpacity: 0.05,
                interactive: false,
            },
        )

    polygon.addTo(zoneLayer)
}

const drawMapImage = () => {
    if (
        !map ||
        !selectedZone.value?.map_image ||
        !selectedZone.value?.map_image_bounds
    ) {
        return
    }

    const bounds =
        selectedZone.value
            .map_image_bounds

    const imageUrl =
        getAssetUrl(
            selectedZone.value.map_image,
        )

    if (!imageUrl) {
        return
    }

    imageLayer =
        L.imageOverlay(
            imageUrl,
            [
                [
                    bounds.south,
                    bounds.west,
                ],
                [
                    bounds.north,
                    bounds.east,
                ],
            ],
            {
                opacity: 1,
                interactive: false,
                crossOrigin: true,
                className:
                    'zoo-map-plan-image',
                pane: 'zooMapImagePane',
            },
        )

    imageLayer.addTo(map)

    imageLayer.bringToBack()
}

/**
 * Caminos normales.
 *
 * Se muestran en gris claro para que la ruta
 * calculada por Dijkstra tenga mayor contraste.
 */
const drawPaths = () => {
    if (!pathLayer) {
        return
    }

    pathLayer.clearLayers()

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const edges =
            path.coordinates?.edges ?? []

        const nodesById =
            new Map<number, PathNode>()

        for (const node of nodes) {
            nodesById.set(
                Number(node.id),
                node,
            )
        }

        for (const edge of edges) {
            const from =
                nodesById.get(
                    Number(edge.from),
                )

            const to =
                nodesById.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

            const line =
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
                        pane: 'zooMapPathPane',
                        weight: 4,
                        opacity: 0.95,
                        color: '#d1d5db',
                        interactive: false,
                    },
                )

            line.addTo(pathLayer)
        }
    }
}

const selectDestination =
    (
        type: 'marker' | 'species',
        id: number,
        center = true,
    ) => {
        selectedDestinationKey.value =
            `${type}-${id}`

        clearRoute()

        const destination =
            selectedDestination.value

        if (!destination) {
            return
        }

        if (
            center &&
            map
        ) {
            map.panTo(
                [
                    destination.latitude,
                    destination.longitude,
                ],
                {
                    animate: true,
                    duration: 0.5,
                },
            )
        }

        drawDestination()

        if (
            locationStatus.value ===
            'inside'
        ) {
            calculateRoute()
        }
    }

const drawMarkers = () => {
    if (!markerLayer) {
        return
    }

    markerLayer.clearLayers()

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

        const markerColor =
            marker.color || '#2563eb'

        const leafletMarker =
            L.circleMarker(
                [
                    latitude,
                    longitude,
                ],
                {
                    pane: 'zooMapNodePane',
                    radius: 8,
                    weight: 2,
                    color: '#ffffff',
                    fillColor: markerColor,
                    fillOpacity: 1,
                },
            )

        leafletMarker.on(
            'click',
            () => {
                selectDestination(
                    'marker',
                    marker.id,
                )
            },
        )

        leafletMarker.addTo(
            markerLayer,
        )
    }
}

const drawSpeciesLocations = () => {
    if (!speciesLayer) {
        return
    }

    speciesLayer.clearLayers()

    for (
        const location of speciesLocations.value
    ) {
        const latitude =
            Number(location.latitude)

        const longitude =
            Number(location.longitude)

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude)
        ) {
            continue
        }

        const leafletMarker =
            L.circleMarker(
                [
                    latitude,
                    longitude,
                ],
                {
                    pane: 'zooMapNodePane',
                    radius: 9,
                    weight: 2,
                    color: '#ffffff',
                    fillColor: '#dc2626',
                    fillOpacity: 1,
                },
            )

        leafletMarker.on(
            'click',
            () => {
                selectDestination(
                    'species',
                    location.id,
                )
            },
        )

        leafletMarker.addTo(
            speciesLayer,
        )
    }
}

const distanceInMeters = (
    lat1: number,
    lng1: number,
    lat2: number,
    lng2: number,
): number => {
    const earthRadius = 6371000

    const latitude1 =
        lat1 * Math.PI / 180

    const latitude2 =
        lat2 * Math.PI / 180

    const deltaLatitude =
        (lat2 - lat1) *
        Math.PI /
        180

    const deltaLongitude =
        (lng2 - lng1) *
        Math.PI /
        180

    const a =
        Math.sin(
            deltaLatitude / 2,
        ) ** 2 +
        Math.cos(latitude1) *
            Math.cos(latitude2) *
            Math.sin(
                deltaLongitude / 2,
            ) ** 2

    const c =
        2 *
        Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a),
        )

    return earthRadius * c
}

const projectPointOnSegment = (
    latitude: number,
    longitude: number,
    from: PathNode,
    to: PathNode,
) => {
    const referenceLatitude =
        latitude * Math.PI / 180

    const metersPerLatitude =
        111320

    const metersPerLongitude =
        111320 *
        Math.cos(referenceLatitude)

    const px =
        longitude *
        metersPerLongitude

    const py =
        latitude *
        metersPerLatitude

    const ax =
        from.lng *
        metersPerLongitude

    const ay =
        from.lat *
        metersPerLatitude

    const bx =
        to.lng *
        metersPerLongitude

    const by =
        to.lat *
        metersPerLatitude

    const abx = bx - ax
    const aby = by - ay
    const apx = px - ax
    const apy = py - ay

    const lengthSquared =
        abx * abx +
        aby * aby

    if (lengthSquared === 0) {
        return {
            lat: from.lat,
            lng: from.lng,
            distance:
                distanceInMeters(
                    latitude,
                    longitude,
                    from.lat,
                    from.lng,
                ),
        }
    }

    let t =
        (
            apx * abx +
            apy * aby
        ) /
        lengthSquared

    t =
        Math.max(
            0,
            Math.min(1, t),
        )

    const projectedX =
        ax + t * abx

    const projectedY =
        ay + t * aby

    const projectedLng =
        projectedX /
        metersPerLongitude

    const projectedLat =
        projectedY /
        metersPerLatitude

    return {
        lat: projectedLat,
        lng: projectedLng,
        distance:
            distanceInMeters(
                latitude,
                longitude,
                projectedLat,
                projectedLng,
            ),
    }
}

const findNearestPath = (
    latitude: number,
    longitude: number,
): NearestPathResult | null => {
    let nearest:
        NearestPathResult | null = null

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const edges =
            path.coordinates?.edges ?? []

        const nodesById =
            new Map<number, PathNode>()

        for (const node of nodes) {
            nodesById.set(
                Number(node.id),
                node,
            )
        }

        for (const edge of edges) {
            const from =
                nodesById.get(
                    Number(edge.from),
                )

            const to =
                nodesById.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

            const projection =
                projectPointOnSegment(
                    latitude,
                    longitude,
                    from,
                    to,
                )

            if (
                !nearest ||
                projection.distance <
                    nearest.distance
            ) {
                nearest = {
                    path,
                    from,
                    to,
                    projectedLat:
                        projection.lat,
                    projectedLng:
                        projection.lng,
                    distance:
                        projection.distance,
                }
            }
        }
    }

    return nearest
}

/**
 * Camino más cercano.
 *
 * Se pinta en blanco para distinguirlo
 * del camino normal gris y de la ruta azul.
 */
const drawNearestPath = () => {
    if (!nearestPathLayer) {
        return
    }

    nearestPathLayer.clearLayers()

    if (!nearestPath.value) {
        return
    }

    const result =
        nearestPath.value

    const line =
        L.polyline(
            [
                [
                    result.from.lat,
                    result.from.lng,
                ],
                [
                    result.to.lat,
                    result.to.lng,
                ],
            ],
            {
                pane: 'zooMapPathPane',
                weight: 6,
                opacity: 0.95,
                color: '#ffffff',
                interactive: false,
            },
        )

    line.addTo(
        nearestPathLayer,
    )

    const projectedPoint =
        L.circleMarker(
            [
                result.projectedLat,
                result.projectedLng,
            ],
            {
                pane: 'zooMapNodePane',
                radius: 7,
                weight: 2,
                color: '#ffffff',
                fillColor: '#16a34a',
                fillOpacity: 1,
                interactive: false,
            },
        )

    projectedPoint.addTo(
        nearestPathLayer,
    )
}

/**
 * Construye el grafo completo de los caminos
 * de la zona.
 */
const buildGraph = () => {
    const graph =
        new Map<string, GraphNode>()

    const adjacency =
        new Map<string, GraphEdge[]>()

    for (const path of paths.value) {
        const nodes =
            path.coordinates?.nodes ?? []

        const edges =
            path.coordinates?.edges ?? []

        for (const node of nodes) {
            const nodeId =
                `${path.id}-${Number(node.id)}`

            graph.set(
                nodeId,
                {
                    id: nodeId,
                    lat: Number(node.lat),
                    lng: Number(node.lng),
                },
            )

            if (!adjacency.has(nodeId)) {
                adjacency.set(
                    nodeId,
                    [],
                )
            }
        }

        const nodesById =
            new Map<number, PathNode>()

        for (const node of nodes) {
            nodesById.set(
                Number(node.id),
                node,
            )
        }

        for (const edge of edges) {
            const from =
                nodesById.get(
                    Number(edge.from),
                )

            const to =
                nodesById.get(
                    Number(edge.to),
                )

            if (!from || !to) {
                continue
            }

            const fromId =
                `${path.id}-${Number(from.id)}`

            const toId =
                `${path.id}-${Number(to.id)}`

            const distance =
                distanceInMeters(
                    from.lat,
                    from.lng,
                    to.lat,
                    to.lng,
                )

            adjacency
                .get(fromId)
                ?.push({
                    to: toId,
                    distance,
                })

            adjacency
                .get(toId)
                ?.push({
                    to: fromId,
                    distance,
                })
        }
    }

    /**
     * Conecta automáticamente nodos de diferentes
     * MapPath cuando están prácticamente en el
     * mismo punto.
     */
    const graphNodes =
        Array.from(graph.values())

    for (
        let i = 0;
        i < graphNodes.length;
        i++
    ) {
        for (
            let j = i + 1;
            j < graphNodes.length;
            j++
        ) {
            const nodeA =
                graphNodes[i]

            const nodeB =
                graphNodes[j]

            if (
                nodeA.id.split('-')[0] ===
                nodeB.id.split('-')[0]
            ) {
                continue
            }

            const distance =
                distanceInMeters(
                    nodeA.lat,
                    nodeA.lng,
                    nodeB.lat,
                    nodeB.lng,
                )

            if (distance <= 3) {
                adjacency
                    .get(nodeA.id)
                    ?.push({
                        to: nodeB.id,
                        distance,
                    })

                adjacency
                    .get(nodeB.id)
                    ?.push({
                        to: nodeA.id,
                        distance,
                    })
            }
        }
    }

    return {
        graph,
        adjacency,
    }
}

const findNearestGraphNode = (
    latitude: number,
    longitude: number,
    graph: Map<string, GraphNode>,
): GraphNode | null => {
    let nearest: GraphNode | null =
        null

    let nearestDistance =
        Number.POSITIVE_INFINITY

    for (const node of graph.values()) {
        const distance =
            distanceInMeters(
                latitude,
                longitude,
                node.lat,
                node.lng,
            )

        if (
            distance <
            nearestDistance
        ) {
            nearestDistance =
                distance

            nearest = node
        }
    }

    return nearest
}

/**
 * Dijkstra sobre la red de caminos.
 */
const calculateDijkstra = (
    startId: string,
    targetId: string,
    graph: Map<string, GraphNode>,
    adjacency: Map<string, GraphEdge[]>,
): RouteResult | null => {
    const distances =
        new Map<string, number>()

    const previous =
        new Map<string, string | null>()

    const unvisited =
        new Set<string>()

    for (const id of graph.keys()) {
        distances.set(
            id,
            Number.POSITIVE_INFINITY,
        )

        previous.set(
            id,
            null,
        )

        unvisited.add(id)
    }

    distances.set(
        startId,
        0,
    )

    while (
        unvisited.size > 0
    ) {
        let currentId:
            string | null = null

        let currentDistance =
            Number.POSITIVE_INFINITY

        for (const id of unvisited) {
            const distance =
                distances.get(id) ??
                Number.POSITIVE_INFINITY

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
            currentId === null
        ) {
            break
        }

        unvisited.delete(
            currentId,
        )

        if (
            currentId === targetId
        ) {
            break
        }

        const neighbors =
            adjacency.get(
                currentId,
            ) ?? []

        for (
            const edge of neighbors
        ) {
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

            const currentNeighborDistance =
                distances.get(
                    edge.to,
                ) ??
                Number.POSITIVE_INFINITY

            if (
                alternative <
                currentNeighborDistance
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

    const finalDistance =
        distances.get(targetId)

    if (
        finalDistance === undefined ||
        !Number.isFinite(
            finalDistance,
        )
    ) {
        return null
    }

    const routeIds: string[] =
        []

    let current:
        string | null =
        targetId

    while (current !== null) {
        routeIds.unshift(
            current,
        )

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
        routeIds[0] !== startId
    ) {
        return null
    }

    const routeNodes =
        routeIds
            .map(
                (id) =>
                    graph.get(id),
            )
            .filter(
                (
                    node,
                ): node is GraphNode =>
                    Boolean(node),
            )

    return {
        nodes: routeNodes,
        distance:
            finalDistance,
    }
}

/**
 * Dibuja la ruta calculada.
 *
 * La ruta tiene dos capas:
 *
 * 1. Borde blanco para darle contraste.
 * 2. Línea azul fuerte para identificar la
 *    navegación calculada por Dijkstra.
 */
const drawRoute = () => {
    if (!routeLayer) {
        return
    }

    routeLayer.clearLayers()

    if (
        !routeResult.value ||
        routeResult.value.nodes.length <
            2
    ) {
        return
    }

    const coordinates =
        routeResult.value.nodes.map(
            (node) =>
                [
                    node.lat,
                    node.lng,
                ] as L.LatLngTuple,
        )

    /**
     * Borde blanco.
     */
    const routeBase =
        L.polyline(
            coordinates,
            {
                pane: 'zooMapPathPane',
                weight: 13,
                opacity: 0.95,
                color: '#ffffff',
                interactive: false,
            },
        )

    routeBase.addTo(
        routeLayer,
    )

    /**
     * Línea principal azul.
     */
    const routeLine =
        L.polyline(
            coordinates,
            {
                pane: 'zooMapPathPane',
                weight: 8,
                opacity: 1,
                color: '#2563eb',
                interactive: false,
            },
        )

    routeLine.addTo(
        routeLayer,
    )
}

const drawDestination = () => {
    if (!map) {
        return
    }

    if (
        destinationMarker
    ) {
        map.removeLayer(
            destinationMarker,
        )

        destinationMarker = null
    }

    const destination =
        selectedDestination.value

    if (!destination) {
        return
    }

    destinationMarker =
        L.marker(
            [
                destination.latitude,
                destination.longitude,
            ],
            {
                pane: 'zooMapNodePane',
            },
        )

    destinationMarker.addTo(map)
}

const calculateRoute = () => {
    routeResult.value = null
    navigationActive.value = false

    if (
        locationStatus.value !==
        'inside'
    ) {
        drawDestination()

        return
    }

    const destination =
        selectedDestination.value

    if (!destination) {
        return
    }

    if (
        userLatitude.value === null ||
        userLongitude.value === null
    ) {
        return
    }

    const {
        graph,
        adjacency,
    } = buildGraph()

    if (graph.size === 0) {
        return
    }

    const startNode =
        findNearestGraphNode(
            userLatitude.value,
            userLongitude.value,
            graph,
        )

    const targetNode =
        findNearestGraphNode(
            destination.latitude,
            destination.longitude,
            graph,
        )

    if (
        !startNode ||
        !targetNode
    ) {
        return
    }

    const result =
        calculateDijkstra(
            startNode.id,
            targetNode.id,
            graph,
            adjacency,
        )

    if (!result) {
        return
    }

    const startDistance =
        distanceInMeters(
            userLatitude.value,
            userLongitude.value,
            startNode.lat,
            startNode.lng,
        )

    const destinationDistance =
        distanceInMeters(
            targetNode.lat,
            targetNode.lng,
            destination.latitude,
            destination.longitude,
        )

    routeResult.value = {
        nodes: [
            {
                id: 'current-position',
                lat:
                    userLatitude.value,
                lng:
                    userLongitude.value,
            },
            ...result.nodes,
            {
                id: 'destination',
                lat:
                    destination.latitude,
                lng:
                    destination.longitude,
            },
        ],
        distance:
            result.distance +
            startDistance +
            destinationDistance,
    }

    navigationActive.value =
        true

    drawDestination()
    drawRoute()
}

const clearRoute = () => {
    routeResult.value = null
    navigationActive.value = false

    routeLayer?.clearLayers()

    if (
        destinationMarker &&
        map
    ) {
        map.removeLayer(
            destinationMarker,
        )

        destinationMarker = null
    }
}

const handleDestinationChange =
    () => {
        clearRoute()

        const destination =
            selectedDestination.value

        if (!destination) {
            return
        }

        if (map) {
            map.panTo(
                [
                    destination.latitude,
                    destination.longitude,
                ],
                {
                    animate: true,
                    duration: 0.5,
                },
            )
        }

        drawDestination()

        /**
         * Si el visitante ya está dentro,
         * la ruta se calcula automáticamente.
         */
        if (
            locationStatus.value ===
            'inside'
        ) {
            calculateRoute()
        }
    }

const updateNearestPath = () => {
    if (
        userLatitude.value === null ||
        userLongitude.value === null ||
        locationStatus.value !==
            'inside'
    ) {
        nearestPath.value = null

        drawNearestPath()

        return
    }

    nearestPath.value =
        findNearestPath(
            userLatitude.value,
            userLongitude.value,
        )

    drawNearestPath()

    /**
     * Si existe un destino seleccionado,
     * mantenemos la navegación actualizada.
     */
    if (
        selectedDestination.value
    ) {
        calculateRoute()
    }
}

const updateUserLocation = (
    latitude: number,
    longitude: number,
    accuracy: number,
) => {
    userLatitude.value =
        latitude

    userLongitude.value =
        longitude

    userAccuracy.value =
        accuracy

    const inside =
        pointInGeometry(
            latitude,
            longitude,
            selectedZone.value?.geometry,
        )

    if (inside) {
        locationStatus.value =
            'inside'

        locationMessage.value =
            'Tu ubicación está dentro de la zona de navegación.'

        updateNearestPath()
    } else {
        locationStatus.value =
            'outside'

        locationMessage.value =
            'Estás fuera de la zona. La navegación está bloqueada.'

        nearestPath.value = null

        routeResult.value = null
        navigationActive.value =
            false

        drawNearestPath()
        routeLayer?.clearLayers()
    }

    if (!map || !userLayer) {
        return
    }

    if (!userMarker) {
        userMarker =
            L.circleMarker(
                [
                    latitude,
                    longitude,
                ],
                {
                    pane: 'zooMapNodePane',
                    radius: 9,
                    weight: 3,
                    color: '#ffffff',
                    fillColor: '#2563eb',
                    fillOpacity: 1,
                },
            )

        userMarker.addTo(
            userLayer,
        )
    } else {
        userMarker.setLatLng(
            [
                latitude,
                longitude,
            ],
        )
    }

    if (accuracy > 0) {
        if (!accuracyCircle) {
            accuracyCircle =
                L.circle(
                    [
                        latitude,
                        longitude,
                    ],
                    {
                        pane: 'zooMapZonePane',
                        radius: accuracy,
                        color: '#2563eb',
                        weight: 1,
                        opacity: 0.4,
                        fillOpacity: 0.08,
                        interactive: false,
                    },
                )

            accuracyCircle.addTo(
                userLayer,
            )
        } else {
            accuracyCircle.setLatLng(
                [
                    latitude,
                    longitude,
                ],
            )

            accuracyCircle.setRadius(
                accuracy,
            )
        }
    }

    if (
        firstLocationFix &&
        inside
    ) {
        firstLocationFix = false

        map.setView(
            [
                latitude,
                longitude,
            ],
            Math.max(
                map.getZoom(),
                18,
            ),
            {
                animate: true,
            },
        )
    }
}

const startLocationTracking =
    () => {
        if (
            !navigator.geolocation
        ) {
            locationStatus.value =
                'error'

            locationMessage.value =
                'Tu navegador no soporta geolocalización.'

            return
        }

        locationStatus.value =
            'requesting'

        locationMessage.value =
            'Solicitando permiso para utilizar tu ubicación...'

        watchId =
            navigator.geolocation.watchPosition(
                (position) => {
                    updateUserLocation(
                        position.coords
                            .latitude,
                        position.coords
                            .longitude,
                        position.coords
                            .accuracy,
                    )
                },
                (positionError) => {
                    locationStatus.value =
                        'error'

                    switch (
                        positionError.code
                    ) {
                        case positionError
                            .PERMISSION_DENIED:
                            locationMessage.value =
                                'Debes permitir el acceso a tu ubicación para utilizar la navegación.'
                            break

                        case positionError
                            .POSITION_UNAVAILABLE:
                            locationMessage.value =
                                'No fue posible obtener tu ubicación.'
                            break

                        case positionError
                            .TIMEOUT:
                            locationMessage.value =
                                'La ubicación tardó demasiado en responder.'
                            break

                        default:
                            locationMessage.value =
                                'No fue posible obtener tu ubicación.'
                    }
                },
                {
                    enableHighAccuracy:
                        true,
                    maximumAge: 3000,
                    timeout: 15000,
                },
            )
    }

const stopLocationTracking =
    () => {
        if (
            watchId !== null
        ) {
            navigator.geolocation.clearWatch(
                watchId,
            )

            watchId = null
        }
    }

const fitMapToZone = () => {
    if (
        !map ||
        !selectedZone.value
    ) {
        return
    }

    const bounds =
        getGeometryBounds(
            selectedZone.value.geometry,
        )

    if (bounds) {
        map.fitBounds(
            bounds,
            {
                padding: [
                    30,
                    30,
                ],
                maxZoom: 19,
            },
        )

        return
    }

    const imageBounds =
        selectedZone.value
            .map_image_bounds

    if (imageBounds) {
        map.fitBounds(
            [
                [
                    imageBounds.south,
                    imageBounds.west,
                ],
                [
                    imageBounds.north,
                    imageBounds.east,
                ],
            ],
            {
                padding: [
                    30,
                    30,
                ],
                maxZoom: 19,
            },
        )
    }
}

const fitMapToData = () => {
    if (!map) {
        return
    }

    const nodeCoordinates:
        L.LatLngExpression[] = []

    for (const path of paths.value) {
        for (
            const node of
                path.coordinates?.nodes ?? []
        ) {
            nodeCoordinates.push(
                [
                    node.lat,
                    node.lng,
                ],
            )
        }
    }

    if (
        nodeCoordinates.length > 0
    ) {
        const bounds =
            L.latLngBounds(
                nodeCoordinates,
            )

        map.fitBounds(
            bounds,
            {
                padding: [
                    30,
                    30,
                ],
                maxZoom: 19,
            },
        )

        return
    }

    fitMapToZone()
}

const loadZone = async (
    zoneId: number,
) => {
    loading.value = true
    error.value = null

    clearRoute()

    try {
        const response =
            await fetch(
                zone(zoneId).url,
                {
                    headers: {
                        Accept:
                            'application/json',
                    },
                },
            )

        if (!response.ok) {
            throw new Error(
                'No fue posible cargar la zona.',
            )
        }

        const data =
            (await response.json()) as ZoneResponse

        selectedZone.value =
            data.zone

        markers.value =
            data.markers ?? []

        speciesLocations.value =
            data.speciesLocations ?? []

        paths.value =
            data.paths ?? []

        selectedDestinationKey.value =
            ''

        clearMapLayers()

        drawMapImage()
        drawZone()
        drawPaths()
        drawMarkers()
        drawSpeciesLocations()

        fitMapToData()

        if (
            userLatitude.value !== null &&
            userLongitude.value !== null
        ) {
            const inside =
                pointInGeometry(
                    userLatitude.value,
                    userLongitude.value,
                    selectedZone.value
                        .geometry,
                )

            if (inside) {
                locationStatus.value =
                    'inside'

                locationMessage.value =
                    'Tu ubicación está dentro de la zona de navegación.'

                updateNearestPath()
            } else {
                locationStatus.value =
                    'outside'

                locationMessage.value =
                    'Estás fuera de la zona. La navegación está bloqueada.'

                nearestPath.value =
                    null

                drawNearestPath()
            }
        }
    } catch (loadError) {
        console.error(
            loadError,
        )

        error.value =
            loadError instanceof Error
                ? loadError.message
                : 'Ocurrió un error al cargar el mapa.'
    } finally {
        loading.value = false
    }
}

const initializeMap =
    async () => {
        await nextTick()

        if (
            !mapContainer.value
        ) {
            return
        }

        map =
            L.map(
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

        map.createPane(
            'zooMapImagePane',
        )

        const imagePane =
            map.getPane(
                'zooMapImagePane',
            )

        if (imagePane) {
            imagePane.style.zIndex =
                '250'

            imagePane.style.pointerEvents =
                'none'
        }

        map.createPane(
            'zooMapZonePane',
        )

        const zonePane =
            map.getPane(
                'zooMapZonePane',
            )

        if (zonePane) {
            zonePane.style.zIndex =
                '350'

            zonePane.style.pointerEvents =
                'none'
        }

        map.createPane(
            'zooMapPathPane',
        )

        const pathPane =
            map.getPane(
                'zooMapPathPane',
            )

        if (pathPane) {
            pathPane.style.zIndex =
                '450'

            pathPane.style.pointerEvents =
                'none'
        }

        map.createPane(
            'zooMapNodePane',
        )

        const nodePane =
            map.getPane(
                'zooMapNodePane',
            )

        if (nodePane) {
            nodePane.style.zIndex =
                '650'
        }

        zoneLayer =
            L.layerGroup().addTo(map)

        pathLayer =
            L.layerGroup().addTo(map)

        markerLayer =
            L.layerGroup().addTo(map)

        speciesLayer =
            L.layerGroup().addTo(map)

        userLayer =
            L.layerGroup().addTo(map)

        nearestPathLayer =
            L.layerGroup().addTo(map)

        routeLayer =
            L.layerGroup().addTo(map)

        if (
            selectedZoneId.value !== null
        ) {
            await loadZone(
                selectedZoneId.value,
            )
        }

        setTimeout(
            () => {
                map?.invalidateSize()
            },
            100,
        )
    }

const handleZoneChange =
    async () => {
        if (
            selectedZoneId.value ===
            null
        ) {
            return
        }

        firstLocationFix = true

        await loadZone(
            selectedZoneId.value,
        )
    }

const centerOnUser = () => {
    if (
        !map ||
        userLatitude.value === null ||
        userLongitude.value === null
    ) {
        return
    }

    map.setView(
        [
            userLatitude.value,
            userLongitude.value,
        ],
        Math.max(
            map.getZoom(),
            18,
        ),
        {
            animate: true,
        },
    )
}

onMounted(
    async () => {
        await initializeMap()

        startLocationTracking()
    },
)

onBeforeUnmount(() => {
    stopLocationTracking()

    if (map) {
        map.remove()
        map = null
    }
})
</script>

<template>
    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Mapa del zoológico
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Explora las instalaciones, encuentra especies y navega por los caminos internos.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    <select
                        v-model.number="selectedZoneId"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="handleZoneChange"
                    >
                        <option
                            v-for="zoneItem in zones"
                            :key="zoneItem.id"
                            :value="zoneItem.id"
                        >
                            {{ zoneItem.name }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                        @click="centerOnUser"
                    >
                        Mi ubicación
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-600 dark:text-red-400"
        >
            {{ error }}
        </div>

        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        {{ selectedZoneName }}
                    </h2>

                    <p
                        v-if="currentZone?.description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ currentZone.description }}
                    </p>
                </div>

                <span
                    class="w-fit rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="locationStatusClass"
                >
                    {{ locationStatusLabel }}
                </span>
            </div>

            <div
                ref="mapContainer"
                class="h-[650px] w-full"
            />

            <!--
                Tarjeta flotante del destino.
                Aparece directamente sobre el mapa.
            -->
            <div
                v-if="selectedDestination"
                class="absolute right-4 top-20 z-[900] w-[min(320px,calc(100%-2rem))] rounded-xl border border-sidebar-border/70 bg-background/95 p-4 shadow-xl backdrop-blur-sm dark:border-sidebar-border"
            >
                <div
                    class="flex items-start justify-between gap-3"
                >
                    <div
                        class="flex min-w-0 items-start gap-3"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                            :class="
                                selectedDestinationDetails?.type === 'species'
                                    ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                    : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                            "
                        >
                            <span class="text-lg">
                                {{
                                    selectedDestinationDetails?.type ===
                                    'species'
                                        ? '🐾'
                                        : '📍'
                                }}
                            </span>
                        </div>

                        <div class="min-w-0">
                            <div
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{
                                    selectedDestinationDetails?.label
                                }}
                            </div>

                            <div
                                class="mt-0.5 truncate text-base font-semibold"
                            >
                                {{ selectedDestination.name }}
                            </div>

                            <div
                                v-if="
                                    selectedDestinationDetails?.scientificName
                                "
                                class="mt-0.5 text-xs italic text-muted-foreground"
                            >
                                {{
                                    selectedDestinationDetails.scientificName
                                }}
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-md px-2 py-1 text-sm text-muted-foreground transition hover:bg-accent hover:text-foreground"
                        aria-label="Cerrar destino"
                        @click="
                            selectedDestinationKey = '';
                            clearRoute();
                        "
                    >
                        ✕
                    </button>
                </div>

                <p
                    v-if="selectedDestination.description"
                    class="mt-3 line-clamp-3 text-sm text-muted-foreground"
                >
                    {{ selectedDestination.description }}
                </p>

                <div
                    v-if="
                        straightLineDistanceToDestination !== null &&
                        locationStatus === 'inside'
                    "
                    class="mt-3 rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground"
                >
                    Aproximadamente
                    <span
                        class="font-semibold text-foreground"
                    >
                        {{
                            formatDistance(
                                straightLineDistanceToDestination,
                            )
                        }}
                    </span>
                    desde tu ubicación.
                </div>

                <div
                    v-if="navigationActive && routeResult"
                    class="mt-3 grid grid-cols-2 gap-2"
                >
                    <div
                        class="rounded-lg border border-blue-500/20 bg-blue-500/5 p-2.5"
                    >
                        <div
                            class="text-[11px] text-muted-foreground"
                        >
                            Ruta
                        </div>

                        <div
                            class="mt-0.5 text-sm font-semibold text-blue-600 dark:text-blue-400"
                        >
                            {{
                                formatDistance(
                                    routeResult.distance,
                                )
                            }}
                        </div>
                    </div>

                    <div
                        class="rounded-lg border border-blue-500/20 bg-blue-500/5 p-2.5"
                    >
                        <div
                            class="text-[11px] text-muted-foreground"
                        >
                            Caminando
                        </div>

                        <div
                            class="mt-0.5 text-sm font-semibold text-blue-600 dark:text-blue-400"
                        >
                            {{
                                formatEstimatedTime(
                                    routeResult.distance,
                                )
                            }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="
                        locationStatus === 'inside' &&
                        paths.length > 0 &&
                        navigationActive
                    "
                    class="mt-3 flex items-center gap-2 text-xs text-green-600 dark:text-green-400"
                >
                    <span
                        class="h-2 w-2 rounded-full bg-green-500"
                    />

                    Ruta activa por los caminos internos.
                </div>

                <div
                    v-else-if="
                        locationStatus === 'outside'
                    "
                    class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 p-2.5 text-xs text-red-600 dark:text-red-400"
                >
                    Debes estar dentro de la zona para utilizar la navegación.
                </div>

                <div
                    v-else-if="
                        locationStatus === 'inside' &&
                        paths.length === 0
                    "
                    class="mt-3 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-2.5 text-xs text-yellow-600 dark:text-yellow-400"
                >
                    Esta zona todavía no tiene caminos configurados.
                </div>

                <div
                    v-else-if="
                        locationStatus === 'requesting'
                    "
                    class="mt-3 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-2.5 text-xs text-yellow-600 dark:text-yellow-400"
                >
                    Esperando tu ubicación para calcular la ruta.
                </div>

                <button
                    v-if="navigationActive"
                    type="button"
                    class="mt-3 w-full rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                    @click="clearRoute"
                >
                    Limpiar ruta
                </button>
            </div>

            <div
                v-if="loading"
                class="absolute inset-0 z-[1000] flex items-center justify-center bg-background/70 backdrop-blur-sm"
            >
                <div
                    class="rounded-xl border border-sidebar-border bg-background px-5 py-4 text-sm shadow-lg"
                >
                    Cargando mapa...
                </div>
            </div>
        </div>

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h2 class="text-lg font-semibold">
                Navegación
            </h2>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Selecciona una especie o punto de interés. La ruta se calculará automáticamente por los caminos internos.
            </p>

            <div
                class="mt-4 flex flex-col gap-3 md:flex-row"
            >
                <select
                    v-model="selectedDestinationKey"
                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    @change="handleDestinationChange"
                >
                    <option value="">
                        Selecciona un destino
                    </option>

                    <optgroup
                        v-if="speciesLocations.length > 0"
                        label="Especies"
                    >
                        <option
                            v-for="destination in destinations.filter(
                                (item) =>
                                    item.type === 'species',
                            )"
                            :key="`species-${destination.id}`"
                            :value="`species-${destination.id}`"
                        >
                            {{ destination.name }}
                        </option>
                    </optgroup>

                    <optgroup
                        v-if="markers.length > 0"
                        label="Puntos de interés"
                    >
                        <option
                            v-for="destination in destinations.filter(
                                (item) =>
                                    item.type === 'marker',
                            )"
                            :key="`marker-${destination.id}`"
                            :value="`marker-${destination.id}`"
                        >
                            {{ destination.name }}
                        </option>
                    </optgroup>
                </select>

                <button
                    v-if="navigationActive"
                    type="button"
                    class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    @click="clearRoute"
                >
                    Limpiar ruta
                </button>
            </div>

            <div
                v-if="selectedDestination"
                class="mt-4 rounded-lg border border-sidebar-border/70 bg-background p-4"
            >
                <div
                    class="flex items-start gap-3"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="
                            selectedDestinationDetails?.type === 'species'
                                ? 'bg-red-500/10 text-red-600 dark:text-red-400'
                                : 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
                        "
                    >
                        {{
                            selectedDestinationDetails?.type ===
                            'species'
                                ? '🐾'
                                : '📍'
                        }}
                    </div>

                    <div>
                        <div
                            class="text-xs font-medium text-muted-foreground"
                        >
                            {{
                                selectedDestinationDetails?.label
                            }}
                        </div>

                        <div
                            class="mt-1 text-lg font-semibold"
                        >
                            {{ selectedDestination.name }}
                        </div>

                        <div
                            v-if="
                                selectedDestinationDetails?.scientificName
                            "
                            class="mt-1 text-sm italic text-muted-foreground"
                        >
                            {{
                                selectedDestinationDetails.scientificName
                            }}
                        </div>

                        <div
                            v-if="selectedDestination.description"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ selectedDestination.description }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="routeResult && navigationActive"
                class="mt-4 grid gap-3 sm:grid-cols-3"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-xs text-muted-foreground"
                    >
                        Destino
                    </div>

                    <div
                        class="mt-1 text-lg font-semibold"
                    >
                        {{ selectedDestination?.name }}
                    </div>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-xs text-muted-foreground"
                    >
                        Distancia
                    </div>

                    <div
                        class="mt-1 text-lg font-semibold"
                    >
                        {{
                            formatDistance(
                                routeResult.distance,
                            )
                        }}
                    </div>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-xs text-muted-foreground"
                    >
                        Caminando
                    </div>

                    <div
                        class="mt-1 text-lg font-semibold"
                    >
                        {{
                            formatEstimatedTime(
                                routeResult.distance,
                            )
                        }}
                    </div>
                </div>
            </div>

            <div
                v-if="
                    locationStatus === 'outside'
                "
                class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-600 dark:text-red-400"
            >
                Debes estar dentro de la zona para calcular una ruta.
            </div>

            <div
                v-if="
                    selectedDestination &&
                    locationStatus === 'inside' &&
                    paths.length === 0
                "
                class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-600 dark:text-yellow-400"
            >
                Esta zona todavía no tiene caminos configurados.
            </div>

            <div
                v-if="
                    selectedDestination &&
                    locationStatus === 'requesting'
                "
                class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-600 dark:text-yellow-400"
            >
                Esperando tu ubicación para calcular automáticamente la ruta.
            </div>

            <div
                v-if="
                    selectedDestination &&
                    locationStatus === 'inside' &&
                    paths.length > 0 &&
                    !navigationActive
                "
                class="mt-4 rounded-lg border border-sidebar-border/70 bg-background p-3 text-sm text-muted-foreground"
            >
                La ruta se calculará automáticamente cuando tu ubicación esté disponible.
            </div>
        </div>

        <div
            class="grid gap-4 md:grid-cols-2"
        >
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <h2 class="text-lg font-semibold">
                    Mi ubicación
                </h2>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ locationMessage }}
                </p>

                <div
                    v-if="
                        userLatitude !== null &&
                        userLongitude !== null
                    "
                    class="mt-4 space-y-2 text-sm"
                >
                    <div>
                        <span
                            class="font-medium"
                        >
                            Latitud:
                        </span>

                        {{ userLatitude.toFixed(6) }}
                    </div>

                    <div>
                        <span
                            class="font-medium"
                        >
                            Longitud:
                        </span>

                        {{ userLongitude.toFixed(6) }}
                    </div>

                    <div
                        v-if="
                            userAccuracy !== null
                        "
                    >
                        <span
                            class="font-medium"
                        >
                            Precisión:
                        </span>

                        {{ Math.round(userAccuracy) }} m
                    </div>
                </div>

                <div
                    v-if="
                        locationStatus ===
                        'outside'
                    "
                    class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-600 dark:text-red-400"
                >
                    La navegación está bloqueada porque tu ubicación se encuentra fuera de la zona seleccionada.
                </div>
            </div>

            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <h2 class="text-lg font-semibold">
                    Camino más cercano
                </h2>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    El sistema busca automáticamente el segmento de camino más cercano a tu ubicación.
                </p>

                <div
                    v-if="
                        nearestPath &&
                        locationStatus === 'inside'
                    "
                    class="mt-4 space-y-3"
                >
                    <div>
                        <div
                            class="text-sm font-medium"
                        >
                            {{ nearestPath.path.name }}
                        </div>

                        <div
                            v-if="
                                nearestPath.path.description
                            "
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ nearestPath.path.description }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-3"
                    >
                        <div
                            class="rounded-lg border border-sidebar-border/70 p-3"
                        >
                            <div
                                class="text-xs text-muted-foreground"
                            >
                                Distancia
                            </div>

                            <div
                                class="mt-1 text-lg font-semibold"
                            >
                                {{
                                    formatDistance(
                                        nearestPath.distance,
                                    )
                                }}
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border/70 p-3"
                        >
                            <div
                                class="text-xs text-muted-foreground"
                            >
                                Caminando
                            </div>

                            <div
                                class="mt-1 text-lg font-semibold"
                            >
                                {{
                                    formatEstimatedTime(
                                        nearestPath.distance,
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="
                        locationStatus ===
                        'outside'
                    "
                    class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-600 dark:text-red-400"
                >
                    No se puede determinar el camino más cercano mientras estés fuera de la zona.
                </div>

                <div
                    v-else-if="
                        locationStatus === 'inside' &&
                        paths.length === 0
                    "
                    class="mt-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-3 text-sm text-yellow-600 dark:text-yellow-400"
                >
                    Esta zona todavía no tiene caminos configurados.
                </div>

                <div
                    v-else
                    class="mt-4 rounded-lg border border-sidebar-border/70 bg-background p-3 text-sm text-muted-foreground"
                >
                    Activa tu ubicación para detectar el camino más cercano.
                </div>
            </div>
        </div>

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h2 class="text-lg font-semibold">
                Información del mapa
            </h2>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Los elementos del zoológico se muestran directamente sobre el plano.
            </p>

            <div
                class="mt-4 grid gap-3 sm:grid-cols-3"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-2xl font-semibold"
                    >
                        {{ paths.length }}
                    </div>

                    <div
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Caminos
                    </div>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-2xl font-semibold"
                    >
                        {{ markers.length }}
                    </div>

                    <div
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Puntos de interés
                    </div>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4"
                >
                    <div
                        class="text-2xl font-semibold"
                    >
                        {{ speciesLocations.length }}
                    </div>

                    <div
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Especies
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.zoo-map-plan-image) {
    pointer-events: none !important;
}

:deep(.leaflet-container) {
    font-family: inherit;
}
</style>