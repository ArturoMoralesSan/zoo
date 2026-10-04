<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    CircleDot,
    Clock3,
    GitBranch,
    Info,
    Map,
    MapPin,
    Route,
    Ruler,
    Save,
    Sparkles,
    Trash2,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import MapPathMap from '@/components/admin/MapPathMap.vue';
import admin from '@/routes/admin';

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

interface GeoJsonGeometry {
    type: string;
    coordinates: unknown;
}

interface Zone {
    id: number;
    name: string;
    description: string | null;
    geometry: GeoJsonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
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

const zoneError =
    ref<string | null>(null);

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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Route class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar camino
                        </h1>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Modifica la red de caminos
                            internos del zoológico.
                        </p>
                    </div>
                </div>

                <Link
                    :href="
                        admin.mapPaths.index().url
                    "
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft
                        class="h-4 w-4"
                    />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Información general -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Info class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Información general
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Configura los datos básicos
                        del camino.
                    </p>
                </div>
            </div>

            <div
                class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2"
            >
                <!-- Nombre -->
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
                    >
                        Nombre del camino
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ej. Camino principal"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Orden -->
                <div>
                    <label
                        for="order"
                        class="mb-2 block text-sm font-medium"
                    >
                        Orden
                    </label>

                    <input
                        id="order"
                        v-model.number="form.order"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.order"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.order }}
                    </p>
                </div>

                <!-- Descripción -->
                <div class="md:col-span-2">
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        placeholder="Descripción del camino..."
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="
                            form.errors.description
                        "
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Estado -->
                <div class="md:col-span-2">
                    <label
                        class="inline-flex cursor-pointer items-center gap-3"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-sidebar-border text-primary focus:ring-primary"
                        />

                        <span class="text-sm font-medium">
                            Camino activo
                        </span>
                    </label>

                    <p
                        class="mt-1 ml-7 text-xs text-muted-foreground"
                    >
                        Los caminos inactivos no se
                        consideran disponibles para
                        la navegación pública.
                    </p>
                </div>
            </div>
        </div>

        <!-- Zona -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <MapPin class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Zona del zoológico
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Selecciona la zona donde pertenece
                        este camino.
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <label
                    for="zone"
                    class="mb-2 block text-sm font-medium"
                >
                    Zona
                    <span class="text-red-500">*</span>
                </label>

                <div class="relative">
                    <select
                        id="zone"
                        v-model="selectedZoneId"
                        class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option :value="null">
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

                    <svg
                        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </div>

                <p
                    v-if="form.errors.zone_id"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.zone_id }}
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingZone"
                class="mt-4 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
            >
                <Sparkles
                    class="h-4 w-4 animate-pulse"
                />

                Cargando información de la zona...
            </div>

            <!-- Error -->
            <div
                v-if="zoneError"
                class="mt-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300"
            >
                <Info
                    class="mt-0.5 h-4 w-4 shrink-0"
                />

                <span>{{ zoneError }}</span>
            </div>

            <!-- Zona seleccionada -->
            <div
                v-if="
                    selectedZone &&
                    !loadingZone
                "
                class="mt-4 rounded-lg border border-sidebar-border bg-muted/20 p-4"
            >
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-4"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Zona
                        </p>

                        <p class="mt-1 font-semibold">
                            {{ selectedZone.name }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Puntos de interés
                        </p>

                        <p class="mt-1 font-semibold">
                            {{ zoneMarkers.length }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Especies
                        </p>

                        <p class="mt-1 font-semibold">
                            {{
                                zoneSpeciesLocations.length
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
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
                    v-if="selectedZone.description"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    {{ selectedZone.description }}
                </p>
            </div>
        </div>

        <!-- MAPA + PANEL DERECHO -->
        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
        >
            <!-- MAPA -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Map class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Editor de caminos
                        </h2>

                        <p
                            class="text-sm text-muted-foreground"
                        >
                            Haz clic sobre el mapa para crear
                            nodos y conectar los caminos.
                        </p>
                    </div>
                </div>

                <div class="mt-6">
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
                            form.errors.coordinates
                        "
                        class="mt-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.coordinates }}
                    </p>

                    <p
                        v-if="
                            selectedZone &&
                            !zoneMapImage
                        "
                        class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
                    >
                        <Info
                            class="mt-0.5 h-4 w-4 shrink-0"
                        />

                        <span>
                            Esta zona no tiene un plano
                            configurado. El camino se dibuja
                            directamente sobre el área
                            delimitada por la zona.
                        </span>
                    </p>

                    <p
                        v-else-if="
                            selectedZone &&
                            zoneMapImage
                        "
                        class="mt-3 flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
                    >
                        <Info
                            class="mt-0.5 h-4 w-4 shrink-0"
                        />

                        <span>
                            El plano es una referencia visual.
                            Los nodos del camino pueden colocarse
                            en cualquier punto dentro de la zona.
                        </span>
                    </p>
                </div>
            </div>

            <!-- PANEL DERECHO -->
            <div class="space-y-6">
                <!-- Herramientas -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Route
                                class="h-5 w-5"
                            />
                        </div>

                        <div>
                            <h3 class="text-base font-semibold">
                                Herramientas
                            </h3>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Gestiona los nodos del camino.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 grid grid-cols-2 gap-2"
                    >
                        <button
                            type="button"
                            :disabled="nodeCount === 0"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-3 py-2.5 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="undoLastNode"
                        >
                            <Undo2
                                class="h-4 w-4"
                            />
                            Deshacer
                        </button>

                        <button
                            type="button"
                            :disabled="nodeCount === 0"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-500/30 px-3 py-2.5 text-sm font-medium text-red-500 transition hover:bg-red-500/10 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="clearPath"
                        >
                            <Trash2
                                class="h-4 w-4"
                            />
                            Limpiar
                        </button>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Info
                                class="h-5 w-5"
                            />
                        </div>

                        <div>
                            <h3 class="text-base font-semibold">
                                Resumen del camino
                            </h3>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Información calculada del recorrido.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3 dark:border-sidebar-border"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <CircleDot
                                    class="h-4 w-4"
                                />
                                Nodos
                            </span>

                            <span class="font-semibold">
                                {{ nodeCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3 dark:border-sidebar-border"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <GitBranch
                                    class="h-4 w-4"
                                />
                                Conexiones
                            </span>

                            <span class="font-semibold">
                                {{ connectionCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3 dark:border-sidebar-border"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Ruler
                                    class="h-4 w-4"
                                />
                                Distancia
                            </span>

                            <span class="font-semibold">
                                {{ form.distance }} m
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <Clock3
                                    class="h-4 w-4"
                                />
                                Tiempo estimado
                            </span>

                            <span class="font-semibold">
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
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center justify-between border-b border-sidebar-border/70 px-5 py-4 dark:border-sidebar-border"
                    >
                        <div
                            class="flex items-center gap-3"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <CircleDot
                                    class="h-5 w-5"
                                />
                            </div>

                            <div>
                                <h3 class="text-base font-semibold">
                                    Nodos del camino
                                </h3>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ connectionCount }}
                                    conexiones
                                </p>
                            </div>
                        </div>

                        <span
                            class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-semibold"
                        >
                            {{ nodeCount }}
                        </span>
                    </div>

                    <div
                        v-if="nodeCount === 0"
                        class="p-8 text-center"
                    >
                        <div
                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-muted text-muted-foreground"
                        >
                            <CircleDot
                                class="h-5 w-5"
                            />
                        </div>

                        <p
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            No hay nodos todavía.
                        </p>
                    </div>

                    <div
                        v-else
                        class="max-h-[420px] overflow-y-auto"
                    >
                        <div
                            v-for="node in form.coordinates.nodes"
                            :key="node.id"
                            class="flex items-center justify-between border-b border-sidebar-border/70 px-5 py-3 last:border-b-0 dark:border-sidebar-border"
                        >
                            <div
                                class="flex min-w-0 items-center gap-3"
                            >
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                >
                                    {{ node.id }}
                                </span>

                                <div class="min-w-0">
                                    <p class="text-sm font-medium">
                                        Nodo {{ node.id }}
                                    </p>

                                    <p
                                        class="truncate text-xs text-muted-foreground"
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
                                class="ml-3 inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-red-500/30 px-2.5 py-1.5 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                @click="
                                    removeNode(
                                        node.id,
                                    )
                                "
                            >
                                <Trash2
                                    class="h-3.5 w-3.5"
                                />
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Errores generales -->
        <div
            v-if="
                Object.keys(form.errors).length > 0
            "
            class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/30"
        >
            <div class="flex items-start gap-3">
                <Info
                    class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400"
                />

                <div>
                    <p
                        class="text-sm font-semibold text-red-700 dark:text-red-300"
                    >
                        Hay errores en el formulario:
                    </p>

                    <ul
                        class="mt-2 list-inside list-disc text-sm text-red-600 dark:text-red-400"
                    >
                        <li
                            v-for="(error, key) in form.errors"
                            :key="key"
                        >
                            {{ error }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
        >
            <Link
                :href="
                    admin.mapPaths.index().url
                "
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
            >
                <X class="h-4 w-4" />
                Cancelar
            </Link>

            <button
                type="button"
                :disabled="!canSave"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit"
            >
                <Sparkles
                    v-if="form.processing"
                    class="h-4 w-4 animate-pulse"
                />

                <Save
                    v-else
                    class="h-4 w-4"
                />

                {{
                    form.processing
                        ? 'Actualizando...'
                        : 'Actualizar camino'
                }}
            </button>
        </div>
    </div>
</template>