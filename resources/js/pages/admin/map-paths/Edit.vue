<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import admin from '@/routes/admin';
import MapPathMap from '@/components/admin/MapPathMap.vue';

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

interface Zone {
    id: number;
    name: string;
    description: string | null;
    geometry: GeoJsonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
}

interface GeoJsonGeometry {
    type: string;
    coordinates: unknown;
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
    zone_id: number;
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

interface MapPath {
    id: number;
    zone_id: number | null;
    name: string;
    description: string | null;
    coordinates: PathData | PathNode[] | null;
    distance: number | null;
    estimated_time: number | null;
    is_active: boolean;
    order: number;
    zone?: Zone | null;
}

interface ZoneResponse {
    zone: {
        id: number;
        name: string;
        description: string | null;
        type: string | null;
        geometry: GeoJsonGeometry | null;
        map_image: string | null;
        map_image_bounds: MapImageBounds | null;
    };
    markers: MapMarker[];
    speciesLocations: SpeciesLocation[];
}

const props = defineProps<{
    mapPath: MapPath;
    zones: Zone[];
}>();

const WALKING_SPEED_METERS_PER_MINUTE = 72;

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
        const nodes = value.map((node, index) => ({
            id: Number(node.id ?? index + 1),
            lat: Number(node.lat),
            lng: Number(node.lng),
        }));

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

const initialZoneId =
    props.mapPath.zone_id ??
    props.mapPath.zone?.id ??
    null;

const selectedZoneId = ref<number | null>(
    initialZoneId
        ? Number(initialZoneId)
        : null,
);

const selectedZone = computed(() => {
    if (!selectedZoneId.value) {
        return null;
    }

    return (
        props.zones.find(
            (zone) =>
                zone.id ===
                selectedZoneId.value,
        ) ??
        props.mapPath.zone ??
        null
    );
});

const zoneGeometry =
    ref<GeoJsonGeometry | null>(
        props.mapPath.zone?.geometry ??
            null,
    );

const zoneMapImage =
    ref<string | null>(
        props.mapPath.zone?.map_image ??
            null,
    );

const zoneMapImageBounds =
    ref<MapImageBounds | null>(
        props.mapPath.zone
            ?.map_image_bounds ??
            null,
    );

const zoneMarkers =
    ref<MapMarker[]>([]);

const zoneSpeciesLocations =
    ref<SpeciesLocation[]>([]);

const loadingZone = ref(false);

const zoneError = ref<string | null>(null);

const form = useForm({
    zone_id: selectedZoneId.value,

    name: props.mapPath.name,

    description:
        props.mapPath.description ?? '',

    coordinates:
        normalizeCoordinates(
            props.mapPath.coordinates,
        ),

    distance:
        props.mapPath.distance ?? 0,

    estimated_time:
        props.mapPath.estimated_time ?? 0,

    is_active:
        props.mapPath.is_active,

    order:
        props.mapPath.order ?? 0,
});

const nodeCount = computed(() => {
    return form.coordinates.nodes.length;
});

const connectionCount = computed(() => {
    return form.coordinates.edges.length;
});

const calculatedEstimatedTime = computed(() => {
    if (form.distance <= 0) {
        return 0;
    }

    return Math.ceil(
        form.distance /
            WALKING_SPEED_METERS_PER_MINUTE,
    );
});

const canSave = computed(() => {
    return (
        !form.processing &&
        form.zone_id !== null &&
        form.name.trim().length > 0 &&
        nodeCount.value >= 2 &&
        connectionCount.value >= 1
    );
});

function haversineDistance(
    latitude1: number,
    longitude1: number,
    latitude2: number,
    longitude2: number,
): number {
    const earthRadius = 6371000;

    const latitude1Radians =
        (latitude1 * Math.PI) / 180;

    const latitude2Radians =
        (latitude2 * Math.PI) / 180;

    const deltaLatitude =
        ((latitude2 - latitude1) * Math.PI) /
        180;

    const deltaLongitude =
        ((longitude2 - longitude1) * Math.PI) /
        180;

    const a =
        Math.sin(deltaLatitude / 2) ** 2 +
        Math.cos(latitude1Radians) *
            Math.cos(latitude2Radians) *
            Math.sin(deltaLongitude / 2) ** 2;

    const c =
        2 *
        Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a),
        );

    return earthRadius * c;
}

function updateDistanceFromGraph(): void {
    const nodes = form.coordinates.nodes;
    const edges = form.coordinates.edges;

    const nodeLookup = new Map<
        number,
        PathNode
    >();

    nodes.forEach((node) => {
        nodeLookup.set(node.id, node);
    });

    let distance = 0;

    edges.forEach((edge) => {
        const from =
            nodeLookup.get(edge.from);

        const to =
            nodeLookup.get(edge.to);

        if (!from || !to) {
            return;
        }

        distance += haversineDistance(
            from.lat,
            from.lng,
            to.lat,
            to.lng,
        );
    });

    form.distance =
        Math.round(distance);

    form.estimated_time =
        form.distance > 0
            ? Math.ceil(
                  form.distance /
                      WALKING_SPEED_METERS_PER_MINUTE,
              )
            : 0;
}

function updateDistance(
    distance: number,
): void {
    form.distance =
        Math.round(distance);

    form.estimated_time =
        form.distance > 0
            ? Math.ceil(
                  form.distance /
                      WALKING_SPEED_METERS_PER_MINUTE,
              )
            : 0;
}

function removeNode(
    nodeId: number,
): void {
    form.coordinates.nodes =
        form.coordinates.nodes.filter(
            (node) =>
                node.id !== nodeId,
        );

    form.coordinates.edges =
        form.coordinates.edges.filter(
            (edge) =>
                edge.from !== nodeId &&
                edge.to !== nodeId,
        );

    updateDistanceFromGraph();
}

function undoLastNode(): void {
    const nodes =
        form.coordinates.nodes;

    if (nodes.length === 0) {
        return;
    }

    const lastNode =
        nodes[nodes.length - 1];

    removeNode(lastNode.id);
}

function clearPath(): void {
    form.coordinates = {
        nodes: [],
        edges: [],
    };

    form.distance = 0;
    form.estimated_time = 0;
}

async function loadZone(
    zoneId: number | null,
    preservePath = false,
): Promise<void> {
    zoneGeometry.value = null;
    zoneMapImage.value = null;
    zoneMapImageBounds.value = null;
    zoneMarkers.value = [];
    zoneSpeciesLocations.value = [];
    zoneError.value = null;

    if (!zoneId) {
        if (!preservePath) {
            clearPath();
        }

        return;
    }

    loadingZone.value = true;

    try {
        const response = await fetch(
            admin.mapPaths.zone(
                zoneId,
            ).url,
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
        );

        if (!response.ok) {
            throw new Error(
                `HTTP ${response.status}`,
            );
        }

        const data =
            (await response.json()) as ZoneResponse;

        zoneGeometry.value =
            data.zone.geometry ??
            null;

        zoneMapImage.value =
            data.zone.map_image ??
            null;

        zoneMapImageBounds.value =
            data.zone.map_image_bounds ??
            null;

        zoneMarkers.value =
            data.markers ?? [];

        zoneSpeciesLocations.value =
            data.speciesLocations ?? [];

        if (!preservePath) {
            clearPath();
        }
    } catch (error) {
        console.error(
            'Error cargando la zona:',
            error,
        );

        zoneError.value =
            'No fue posible cargar la información de la zona.';

        zoneGeometry.value = null;
        zoneMapImage.value = null;
        zoneMapImageBounds.value = null;
        zoneMarkers.value = [];
        zoneSpeciesLocations.value = [];

        if (!preservePath) {
            clearPath();
        }
    } finally {
        loadingZone.value = false;
    }
}

let initialized = false;

onMounted(async () => {
    if (selectedZoneId.value) {
        await loadZone(
            selectedZoneId.value,
            true,
        );
    }

    initialized = true;
});

watch(
    selectedZoneId,
    async (zoneId) => {
        form.zone_id = zoneId;

        if (!initialized) {
            return;
        }

        await loadZone(
            zoneId,
            false,
        );
    },
);

watch(
    () => form.coordinates,
    () => {
        updateDistanceFromGraph();
    },
    {
        deep: true,
    },
);

function submit(): void {
    if (!form.zone_id) {
        zoneError.value =
            'Debes seleccionar una zona antes de actualizar el camino.';

        return;
    }

    if (
        form.coordinates.nodes.length <
        2
    ) {
        zoneError.value =
            'Debes tener al menos 2 nodos.';

        return;
    }

    if (
        form.coordinates.edges.length <
        1
    ) {
        zoneError.value =
            'Debes tener al menos una conexión entre nodos.';

        return;
    }

    form.put(
        admin.mapPaths.update(
            props.mapPath.id,
        ).url,
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head
        :title="`Editar camino: ${mapPath.name}`"
    />

    <div class="p-6">
        <!-- Encabezado -->
        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold text-gray-900 dark:text-white"
                >
                    Editar camino
                </h1>

                <p
                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                >
                    Modifica la red de caminos internos del zoológico.
                </p>
            </div>

            <Link
                :href="
                    admin.mapPaths.index()
                        .url
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                ← Regresar
            </Link>
        </div>

        <!-- Información general -->
        <div
            class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <h2
                class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
            >
                Información general
            </h2>

            <div
                class="grid grid-cols-1 gap-6 md:grid-cols-2"
            >
                <!-- Nombre -->
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Nombre del camino
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Orden -->
                <div>
                    <label
                        for="order"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Orden
                    </label>

                    <input
                        id="order"
                        v-model.number="
                            form.order
                        "
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />

                    <p
                        v-if="form.errors.order"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.order }}
                    </p>
                </div>

                <!-- Descripción -->
                <div
                    class="md:col-span-2"
                >
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="
                            form.description
                        "
                        rows="3"
                        placeholder="Descripción del camino..."
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors
                                .description
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            form.errors
                                .description
                        }}
                    </p>
                </div>

                <!-- Estado -->
                <div
                    class="md:col-span-2"
                >
                    <label
                        class="inline-flex cursor-pointer items-center gap-3"
                    >
                        <input
                            v-model="
                                form.is_active
                            "
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />

                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Camino activo
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Zona -->
        <div
            class="mb-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="mb-4">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Zona del zoológico
                </h2>

                <p
                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                >
                    Selecciona la zona donde pertenece este camino.
                </p>
            </div>

            <div>
                <label
                    for="zone"
                    class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Zona
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="zone"
                    v-model="
                        selectedZoneId
                    "
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                >
                    <option
                        :value="null"
                    >
                        Selecciona una zona...
                    </option>

                    <option
                        v-for="zone in props.zones"
                        :key="zone.id"
                        :value="zone.id"
                    >
                        {{ zone.name }}
                    </option>
                </select>

                <p
                    v-if="form.errors.zone_id"
                    class="mt-1 text-sm text-red-600"
                >
                    {{
                        form.errors
                            .zone_id
                    }}
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingZone"
                class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
            >
                Cargando información de la zona...
            </div>

            <!-- Error -->
            <div
                v-if="zoneError"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ zoneError }}
            </div>

            <!-- Zona seleccionada -->
            <div
                v-if="
                    selectedZone &&
                    !loadingZone
                "
                class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
            >
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-4"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Zona
                        </p>

                        <p
                            class="mt-1 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                selectedZone.name
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Puntos de interés
                        </p>

                        <p
                            class="mt-1 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                zoneMarkers.length
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Especies
                        </p>

                        <p
                            class="mt-1 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                zoneSpeciesLocations.length
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Plano
                        </p>

                        <p
                            class="mt-1 font-semibold"
                            :class="
                                zoneMapImage
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-amber-600 dark:text-amber-400'
                            "
                        >
                            {{
                                zoneMapImage
                                    ? 'Configurado'
                                    : 'Sin plano'
                            }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="
                        selectedZone.description
                    "
                    class="mt-4 text-sm text-gray-600 dark:text-gray-400"
                >
                    {{
                        selectedZone.description
                    }}
                </p>
            </div>
        </div>

        <!-- MAPA + PANEL DERECHO -->
        <div
            class="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
        >
            <!-- MAPA -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div
                    class="mb-4"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Editor de caminos
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Haz clic sobre el mapa para crear nodos y conectar los
                        caminos.
                    </p>
                </div>

                <MapPathMap
                    v-model:coordinates="
                        form.coordinates
                    "
                    :zone-geometry="
                        zoneGeometry
                    "
                    :map-image="
                        zoneMapImage
                    "
                    :map-image-bounds="
                        zoneMapImageBounds
                    "
                    :markers="
                        zoneMarkers
                    "
                    :species-locations="
                        zoneSpeciesLocations
                    "
                    @update:distance="
                        updateDistance
                    "
                />

                <p
                    v-if="
                        form.errors
                            .coordinates
                    "
                    class="mt-2 text-sm text-red-600"
                >
                    {{
                        form.errors
                            .coordinates
                    }}
                </p>

                <p
                    v-if="
                        selectedZone &&
                        !zoneMapImage
                    "
                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    Esta zona no tiene un plano configurado. El camino se
                    dibuja directamente sobre el área delimitada por la zona.
                </p>

                <p
                    v-else-if="
                        selectedZone &&
                        zoneMapImage
                    "
                    class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
                >
                    El plano es una referencia visual. Los nodos del camino
                    pueden colocarse en cualquier punto dentro de la zona.
                </p>
            </div>

            <!-- PANEL DERECHO -->
            <div
                class="space-y-6"
            >
                <!-- Herramientas -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3
                        class="mb-4 text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Herramientas
                    </h3>

                    <div
                        class="grid grid-cols-2 gap-2"
                    >
                        <button
                            type="button"
                            :disabled="
                                nodeCount ===
                                0
                            "
                            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-700"
                            @click="
                                undoLastNode
                            "
                        >
                            ↩ Deshacer
                        </button>

                        <button
                            type="button"
                            :disabled="
                                nodeCount ===
                                0
                            "
                            class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-950/30"
                            @click="
                                clearPath
                            "
                        >
                            🗑 Limpiar
                        </button>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3
                        class="mb-4 text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Resumen del camino
                    </h3>

                    <div
                        class="space-y-4"
                    >
                        <div
                            class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700"
                        >
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Nodos
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    nodeCount
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700"
                        >
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Conexiones
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    connectionCount
                                }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700"
                        >
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Distancia
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    form.distance
                                }}
                                m
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                Tiempo estimado
                            </span>

                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    calculatedEstimatedTime
                                }}
                                min
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Nodos -->
                <div
                    class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700"
                    >
                        <div>
                            <h3
                                class="text-base font-semibold text-gray-900 dark:text-white"
                            >
                                Nodos del camino
                            </h3>

                            <p
                                class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    connectionCount
                                }}
                                conexiones
                            </p>
                        </div>

                        <span
                            class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
                        >
                            {{
                                nodeCount
                            }}
                        </span>
                    </div>

                    <div
                        v-if="
                            nodeCount ===
                            0
                        "
                        class="p-5 text-center text-sm text-gray-500 dark:text-gray-400"
                    >
                        No hay nodos todavía.
                    </div>

                    <div
                        v-else
                        class="max-h-[420px] overflow-y-auto"
                    >
                        <div
                            v-for="node in form.coordinates.nodes"
                            :key="
                                node.id
                            "
                            class="flex items-center justify-between border-b border-gray-100 px-5 py-3 last:border-b-0 dark:border-gray-700"
                        >
                            <div
                                class="flex min-w-0 items-center gap-2"
                            >
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white"
                                >
                                    {{
                                        node.id
                                    }}
                                </span>

                                <div
                                    class="min-w-0"
                                >
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        Nodo
                                        {{
                                            node.id
                                        }}
                                    </p>

                                    <p
                                        class="truncate text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            node.lat.toFixed(
                                                6,
                                            )
                                        }},
                                        {{
                                            node.lng.toFixed(
                                                6,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="ml-3 shrink-0 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                @click="
                                    removeNode(
                                        node.id,
                                    )
                                "
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guardar -->
        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    admin.mapPaths.index()
                        .url
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Cancelar
            </Link>

            <button
                type="button"
                :disabled="
                    !canSave
                "
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit"
            >
                <span
                    v-if="
                        form.processing
                    "
                >
                    Actualizando...
                </span>

                <span v-else>
                    Actualizar camino
                </span>
            </button>
        </div>

        <!-- Errores generales -->
        <div
            v-if="
                Object.keys(
                    form.errors,
                ).length > 0
            "
            class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/30"
        >
            <p
                class="text-sm font-semibold text-red-700 dark:text-red-300"
            >
                Hay errores en el formulario:
            </p>

            <ul
                class="mt-2 list-inside list-disc text-sm text-red-600 dark:text-red-400"
            >
                <li
                    v-for="(
                        error,
                        key
                    ) in form.errors"
                    :key="key"
                >
                    {{ error }}
                </li>
            </ul>
        </div>
    </div>
</template>