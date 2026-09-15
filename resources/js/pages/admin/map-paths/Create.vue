<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import MapPathMap from '@/components/admin/MapPathMap.vue';
import admin from '@/routes/admin';

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
    zones: Zone[];
}>();

const WALKING_SPEED_METERS_PER_MINUTE = 72;

const selectedZoneId = ref<number | null>(null);

const selectedZone = computed(() => {
    if (!selectedZoneId.value) {
        return null;
    }

    return (
        props.zones.find(
            (zone) => zone.id === selectedZoneId.value,
        ) ?? null
    );
});

const zoneGeometry = ref<GeoJsonGeometry | null>(null);

const zoneMapImage = ref<string | null>(null);

const zoneMapImageBounds =
    ref<MapImageBounds | null>(null);

const zoneMarkers = ref<MapMarker[]>([]);

const zoneSpeciesLocations =
    ref<SpeciesLocation[]>([]);

const loadingZone = ref(false);

const zoneError = ref<string | null>(null);

const form = useForm({
    zone_id: null as number | null,
    name: '',
    description: '',
    coordinates: {
        nodes: [],
        edges: [],
    } as PathData,
    distance: 0,
    estimated_time: 0,
    is_active: true,
    order: 0,
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
        selectedZoneId.value !== null &&
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
        ((latitude2 - latitude1) * Math.PI) / 180;

    const deltaLongitude =
        ((longitude2 - longitude1) * Math.PI) / 180;

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

    const nodeLookup = new Map<number, PathNode>();

    nodes.forEach((node) => {
        nodeLookup.set(node.id, node);
    });

    let distance = 0;

    edges.forEach((edge) => {
        const from = nodeLookup.get(edge.from);
        const to = nodeLookup.get(edge.to);

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

    form.distance = Math.round(distance);

    form.estimated_time =
        form.distance > 0
            ? Math.ceil(
                  form.distance /
                      WALKING_SPEED_METERS_PER_MINUTE,
              )
            : 0;
}

function updateDistance(distance: number): void {
    form.distance = Math.round(distance);

    form.estimated_time =
        form.distance > 0
            ? Math.ceil(
                  form.distance /
                      WALKING_SPEED_METERS_PER_MINUTE,
              )
            : 0;
}

function removeNode(nodeId: number): void {
    form.coordinates.nodes =
        form.coordinates.nodes.filter(
            (node) => node.id !== nodeId,
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
    const nodes = form.coordinates.nodes;

    if (nodes.length === 0) {
        return;
    }

    const lastNode = nodes[nodes.length - 1];

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
): Promise<void> {
    zoneGeometry.value = null;
    zoneMapImage.value = null;
    zoneMapImageBounds.value = null;
    zoneMarkers.value = [];
    zoneSpeciesLocations.value = [];
    zoneError.value = null;

    clearPath();

    if (!zoneId) {
        return;
    }

    loadingZone.value = true;

    try {
        const response = await fetch(
            admin.mapPaths.zone(zoneId).url,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
                credentials: 'same-origin',
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
            data.zone.geometry ?? null;

        zoneMapImage.value =
            data.zone.map_image ?? null;

        zoneMapImageBounds.value =
            data.zone.map_image_bounds ?? null;

        zoneMarkers.value =
            data.markers ?? [];

        zoneSpeciesLocations.value =
            data.speciesLocations ?? [];
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
    } finally {
        loadingZone.value = false;
    }
}

watch(
    selectedZoneId,
    async (zoneId) => {
        form.zone_id = zoneId;

        await loadZone(zoneId);
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
    if (!selectedZoneId.value) {
        zoneError.value =
            'Debes seleccionar una zona antes de guardar el camino.';

        return;
    }

    if (form.coordinates.nodes.length < 2) {
        zoneError.value =
            'Debes crear al menos 2 nodos.';

        return;
    }

    if (form.coordinates.edges.length < 1) {
        zoneError.value =
            'Debes crear al menos una conexión entre nodos.';

        return;
    }

    form.zone_id = selectedZoneId.value;

    form.post(
        admin.mapPaths.store().url,
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <Head title="Crear camino" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Crear camino
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Define la red de caminos internos del zoológico.
                    </p>
                </div>

                <Link
                    :href="admin.mapPaths.index().url"
                    class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    ← Regresar
                </Link>
            </div>
        </div>

        <!-- Información general -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2
                    class="text-lg font-semibold"
                >
                    Información general
                </h2>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Define la información básica del camino.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-6 md:grid-cols-2"
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
                        class="mt-1 text-sm text-red-500"
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
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Orden de visualización.
                    </p>

                    <p
                        v-if="form.errors.order"
                        class="mt-1 text-sm text-red-500"
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
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-sm text-red-500"
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

                        <span
                            class="text-sm font-medium"
                        >
                            Camino activo
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Selección de zona -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2
                    class="text-lg font-semibold"
                >
                    Zona del zoológico
                </h2>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Selecciona la zona donde vas a dibujar los caminos.
                </p>
            </div>

            <div>
                <label
                    for="zone"
                    class="mb-2 block text-sm font-medium"
                >
                    Zona
                    <span class="text-red-500">*</span>
                </label>

                <select
                    id="zone"
                    v-model="selectedZoneId"
                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                <p
                    v-if="form.errors.zone_id"
                    class="mt-1 text-sm text-red-500"
                >
                    {{ form.errors.zone_id }}
                </p>
            </div>

            <!-- Loading -->
            <div
                v-if="loadingZone"
                class="mt-4 rounded-lg border border-sidebar-border bg-accent/30 p-4 text-sm text-muted-foreground"
            >
                Cargando información de la zona...
            </div>

            <!-- Error -->
            <div
                v-if="zoneError"
                class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-600 dark:border-red-800 dark:bg-red-950/30 dark:text-red-400"
            >
                {{ zoneError }}
            </div>

            <!-- Zona seleccionada -->
            <div
                v-if="selectedZone && !loadingZone"
                class="mt-4 rounded-lg border border-sidebar-border bg-accent/30 p-4"
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

                        <p
                            class="mt-1 font-semibold"
                        >
                            {{ selectedZone.name }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Puntos de interés
                        </p>

                        <p
                            class="mt-1 font-semibold"
                        >
                            {{ zoneMarkers.length }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Especies
                        </p>

                        <p
                            class="mt-1 font-semibold"
                        >
                            {{ zoneSpeciesLocations.length }}
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
                        >
                            {{
                                zoneMapImage
                                    ? 'Disponible'
                                    : 'No configurado'
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

                <p
                    class="mt-4 text-xs text-muted-foreground"
                >
                    El plano de la zona se utiliza como referencia visual.
                    Los nodos del camino solamente están restringidos por el
                    área de la zona.
                </p>
            </div>
        </div>

        <!-- MAPA + PANEL DERECHO -->
        <div
            class="mb-2 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
        >
            <!-- MAPA -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-4">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Editor de caminos
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Haz clic sobre el mapa para crear nodos y conectar los
                        caminos.
                    </p>
                </div>

                <MapPathMap
                    v-model:coordinates="form.coordinates"
                    :zone-geometry="zoneGeometry"
                    :map-image="zoneMapImage"
                    :map-image-bounds="
                        zoneMapImageBounds
                    "
                    :markers="zoneMarkers"
                    :species-locations="
                        zoneSpeciesLocations
                    "
                    @update:distance="updateDistance"
                />
            </div>

            <!-- PANEL DERECHO -->
            <div class="space-y-6">
                <!-- Acciones -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="mb-4 text-base font-semibold"
                    >
                        Herramientas
                    </h3>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :disabled="nodeCount === 0"
                            class="rounded-lg border border-sidebar-border px-3 py-2 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                            @click="undoLastNode"
                        >
                            ↩ Deshacer
                        </button>

                        <button
                            type="button"
                            :disabled="nodeCount === 0"
                            class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-800 dark:hover:bg-red-950/30"
                            @click="clearPath"
                        >
                            🗑 Limpiar
                        </button>
                    </div>
                </div>

                <!-- Nodo seleccionado -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="mb-3 text-base font-semibold"
                    >
                        Nodo seleccionado
                    </h3>

                    <div
                        class="rounded-lg bg-accent/30 p-4"
                    >
                        <div
                            v-if="form.coordinates.nodes.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Haz clic en el mapa para crear el primer nodo.
                        </div>

                        <div
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            Selecciona un nodo directamente en el mapa para
                            conectarlo con otro.
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <h3
                        class="mb-4 text-base font-semibold"
                    >
                        Resumen del camino
                    </h3>

                    <div class="space-y-4">
                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3"
                        >
                            <span
                                class="text-sm text-muted-foreground"
                            >
                                Nodos
                            </span>

                            <span
                                class="font-semibold"
                            >
                                {{ nodeCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3"
                        >
                            <span
                                class="text-sm text-muted-foreground"
                            >
                                Conexiones
                            </span>

                            <span
                                class="font-semibold"
                            >
                                {{ connectionCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between border-b border-sidebar-border/70 pb-3"
                        >
                            <span
                                class="text-sm text-muted-foreground"
                            >
                                Distancia
                            </span>

                            <span
                                class="font-semibold"
                            >
                                {{ form.distance }} m
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="text-sm text-muted-foreground"
                            >
                                Tiempo estimado
                            </span>

                            <span
                                class="font-semibold"
                            >
                                {{ calculatedEstimatedTime }} min
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Lista de nodos -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center justify-between border-b border-sidebar-border/70 px-5 py-4"
                    >
                        <h3
                            class="text-base font-semibold"
                        >
                            Nodos
                        </h3>

                        <span
                            class="rounded-full bg-accent px-2.5 py-1 text-xs font-semibold"
                        >
                            {{ nodeCount }}
                        </span>
                    </div>

                    <div
                        v-if="nodeCount === 0"
                        class="p-5 text-center text-sm text-muted-foreground"
                    >
                        No hay nodos todavía.
                    </div>

                    <div
                        v-else
                        class="max-h-[360px] overflow-y-auto"
                    >
                        <div
                            v-for="node in form.coordinates.nodes"
                            :key="node.id"
                            class="flex items-center justify-between border-b border-sidebar-border/70 px-5 py-3 last:border-b-0"
                        >
                            <div>
                                <div
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                    >
                                        {{ node.id }}
                                    </span>

                                    <div>
                                        <p
                                            class="text-sm font-medium"
                                        >
                                            Nodo {{ node.id }}
                                        </p>

                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ node.lat.toFixed(6) }},
                                            {{ node.lng.toFixed(6) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="ml-3 text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                @click="removeNode(node.id)"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Errores -->
        <div
            v-if="form.errors.coordinates"
            class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-600 dark:border-red-800 dark:bg-red-950/30 dark:text-red-400"
        >
            {{ form.errors.coordinates }}
        </div>

        <!-- Guardar -->
        <div
            class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
        >
            <Link
                :href="admin.mapPaths.index().url"
                class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
            >
                Cancelar
            </Link>

            <button
                type="button"
                :disabled="!canSave"
                class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                @click="submit"
            >
                <span v-if="form.processing">
                    Guardando...
                </span>

                <span v-else>
                    Guardar camino
                </span>
            </button>
        </div>

        <!-- Errores generales -->
        <div
            v-if="Object.keys(form.errors).length > 0"
            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/30"
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
                    v-for="(error, key) in form.errors"
                    :key="key"
                >
                    {{ error }}
                </li>
            </ul>
        </div>
    </div>
</template>