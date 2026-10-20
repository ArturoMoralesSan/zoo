<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
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

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import MapPathMap from '@/components/admin/MapPathMap.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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
        return { nodes: [], edges: [] };
    }

    if (Array.isArray(value)) {
        const nodes = value.map((node, index) => ({
            id: Number(node.id ?? index + 1),
            lat: Number(node.lat),
            lng: Number(node.lng),
        }));

        const edges: PathEdge[] = [];

        for (let index = 1; index < nodes.length; index++) {
            edges.push({
                from: nodes[index - 1].id,
                to: nodes[index].id,
            });
        }

        return { nodes, edges };
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
    initialZoneId ? Number(initialZoneId) : null,
);

const selectedZone = computed(
    () =>
        props.zones.find(
            (zone) => zone.id === selectedZoneId.value,
        ) ??
        (props.mapPath.zone?.id === selectedZoneId.value
            ? props.mapPath.zone
            : null) ??
        null,
);

const zoneGeometry = ref<GeoJsonGeometry | null>(
    props.mapPath.zone?.geometry ?? null,
);
const zoneMapImage = ref<string | null>(
    props.mapPath.zone?.map_image ?? null,
);
const zoneMapImageBounds = ref<MapImageBounds | null>(
    props.mapPath.zone?.map_image_bounds ?? null,
);
const zoneMarkers = ref<MapMarker[]>([]);
const zoneSpeciesLocations = ref<SpeciesLocation[]>([]);
const loadingZone = ref(false);
const zoneError = ref<string | null>(null);

const form = useForm({
    zone_id: selectedZoneId.value,
    name: props.mapPath.name,
    description: props.mapPath.description ?? '',
    coordinates: normalizeCoordinates(props.mapPath.coordinates),
    distance: props.mapPath.distance ?? 0,
    estimated_time: props.mapPath.estimated_time ?? 0,
    is_active: props.mapPath.is_active,
    order: props.mapPath.order ?? 0,
});

const nodeCount = computed(() => form.coordinates.nodes.length);
const connectionCount = computed(() => form.coordinates.edges.length);

const calculatedEstimatedTime = computed(() =>
    form.distance > 0
        ? Math.ceil(form.distance / WALKING_SPEED_METERS_PER_MINUTE)
        : 0,
);

const canSave = computed(
    () =>
        !form.processing &&
        form.zone_id !== null &&
        form.name.trim().length > 0 &&
        nodeCount.value >= 2 &&
        connectionCount.value >= 1,
);

function haversineDistance(
    latitude1: number,
    longitude1: number,
    latitude2: number,
    longitude2: number,
): number {
    const earthRadius = 6371000;
    const latitude1Radians = (latitude1 * Math.PI) / 180;
    const latitude2Radians = (latitude2 * Math.PI) / 180;
    const deltaLatitude = ((latitude2 - latitude1) * Math.PI) / 180;
    const deltaLongitude = ((longitude2 - longitude1) * Math.PI) / 180;

    const a =
        Math.sin(deltaLatitude / 2) ** 2 +
        Math.cos(latitude1Radians) *
            Math.cos(latitude2Radians) *
            Math.sin(deltaLongitude / 2) ** 2;

    return (
        earthRadius *
        2 *
        Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    );
}

function updateDistanceFromGraph(): void {
    const nodeLookup = new Map<number, PathNode>();

    form.coordinates.nodes.forEach((node) => {
        nodeLookup.set(node.id, node);
    });

    let distance = 0;

    form.coordinates.edges.forEach((edge) => {
        const from = nodeLookup.get(edge.from);
        const to = nodeLookup.get(edge.to);

        if (!from || !to) return;

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
            ? Math.ceil(form.distance / WALKING_SPEED_METERS_PER_MINUTE)
            : 0;
}

function updateDistance(distance: number): void {
    form.distance = Math.round(distance);
    form.estimated_time =
        form.distance > 0
            ? Math.ceil(form.distance / WALKING_SPEED_METERS_PER_MINUTE)
            : 0;
}

function removeNode(nodeId: number): void {
    form.coordinates.nodes = form.coordinates.nodes.filter(
        (node) => node.id !== nodeId,
    );

    form.coordinates.edges = form.coordinates.edges.filter(
        (edge) => edge.from !== nodeId && edge.to !== nodeId,
    );

    updateDistanceFromGraph();
}

function undoLastNode(): void {
    const nodes = form.coordinates.nodes;

    if (nodes.length > 0) {
        removeNode(nodes[nodes.length - 1].id);
    }
}

function clearPath(): void {
    form.coordinates = { nodes: [], edges: [] };
    form.distance = 0;
    form.estimated_time = 0;
}

let zoneRequestId = 0;

async function loadZone(
    zoneId: number | null,
    preservePath = false,
): Promise<void> {
    const requestId = ++zoneRequestId;

    zoneGeometry.value = null;
    zoneMapImage.value = null;
    zoneMapImageBounds.value = null;
    zoneMarkers.value = [];
    zoneSpeciesLocations.value = [];
    zoneError.value = null;

    if (!zoneId) {
        loadingZone.value = false;

        if (!preservePath) clearPath();

        return;
    }

    loadingZone.value = true;

    try {
        const response = await fetch(admin.mapPaths.zone(zoneId).url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = (await response.json()) as ZoneResponse;

        // Ignora respuestas antiguas si cambió la zona seleccionada.
        if (requestId !== zoneRequestId) return;

        zoneGeometry.value = data.zone.geometry ?? null;
        zoneMapImage.value = data.zone.map_image ?? null;
        zoneMapImageBounds.value = data.zone.map_image_bounds ?? null;
        zoneMarkers.value = data.markers ?? [];
        zoneSpeciesLocations.value = data.speciesLocations ?? [];

        if (!preservePath) clearPath();
    } catch (error) {
        if (requestId !== zoneRequestId) return;

        console.error('Error cargando la zona:', error);
        zoneError.value =
            'No fue posible cargar la información de la zona.';

        zoneGeometry.value = null;
        zoneMapImage.value = null;
        zoneMapImageBounds.value = null;
        zoneMarkers.value = [];
        zoneSpeciesLocations.value = [];

        if (!preservePath) clearPath();
    } finally {
        if (requestId === zoneRequestId) {
            loadingZone.value = false;
        }
    }
}

let initialized = false;

onMounted(async () => {
    if (selectedZoneId.value) {
        await loadZone(selectedZoneId.value, true);
    }

    initialized = true;
});

watch(selectedZoneId, async (zoneId) => {
    form.zone_id = zoneId;

    if (!initialized) return;

    await loadZone(zoneId, false);
});

watch(
    () => form.coordinates,
    () => updateDistanceFromGraph(),
    { deep: true },
);

function submit(): void {
    zoneError.value = null;

    if (!form.zone_id) {
        zoneError.value =
            'Debes seleccionar una zona antes de actualizar el camino.';
        return;
    }

    if (form.coordinates.nodes.length < 2) {
        zoneError.value = 'Debes tener al menos 2 nodos.';
        return;
    }

    if (form.coordinates.edges.length < 1) {
        zoneError.value =
            'Debes tener al menos una conexión entre nodos.';
        return;
    }

    form.estimated_time = calculatedEstimatedTime.value;

    form.put(admin.mapPaths.update(props.mapPath.id).url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Editar camino: ${mapPath.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar camino"
            description="Modifica la red de caminos internos del zoológico."
            section="Mapa del zoológico"
            :icon="Route"
            :action-icon="ArrowLeft"
            action-label="Volver a caminos"
            :action-href="admin.mapPaths.index().url"
        />

        <!-- Información general -->
        <AdminCard
            title="Información general"
            description="Configura los datos básicos del camino."
            :icon="Info"
        >
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="form-field">
                    <label for="name" class="form-label">
                        Nombre del camino
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-input"
                        placeholder="Ej. Camino principal"
                        maxlength="150"
                        required
                    />

                    <p v-if="form.errors.name" class="form-error">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="form-field">
                    <label for="order" class="form-label">
                        Orden de visualización
                    </label>

                    <input
                        id="order"
                        v-model.number="form.order"
                        type="number"
                        min="0"
                        class="form-input"
                        placeholder="0"
                    />

                    <p v-if="form.errors.order" class="form-error">
                        {{ form.errors.order }}
                    </p>
                </div>

                <div class="form-field md:col-span-2">
                    <label for="description" class="form-label">
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="form-input min-h-24 resize-y"
                        placeholder="Describe brevemente el recorrido..."
                    />

                    <p v-if="form.errors.description" class="form-error">
                        {{ form.errors.description }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between gap-4 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4 md:col-span-2"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                        >
                            <Sparkles class="h-5 w-5" />
                        </span>

                        <div>
                            <p class="text-sm font-semibold text-[#244334]">
                                Camino activo
                            </p>
                            <p class="mt-1 text-sm text-[#718477]">
                                Permite que el camino esté disponible para su
                                visualización.
                            </p>
                        </div>
                    </div>

                    <label class="relative inline-flex cursor-pointer items-center">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="peer sr-only"
                        />
                        <span
                            class="h-6 w-11 rounded-full bg-[#DCE7DF] transition peer-checked:bg-[#187744] peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-[#187744]"
                        />
                        <span
                            class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"
                        />
                    </label>
                </div>
            </div>
        </AdminCard>

        <!-- Selección de zona -->
        <AdminCard
            title="Zona del zoológico"
            description="Selecciona la zona a la que pertenece este camino."
            :icon="MapPin"
        >
            <div class="form-field">
                <label for="zone_id" class="form-label">
                    Zona
                    <span class="text-red-500">*</span>
                </label>

                <div class="relative">
                    <select
                        id="zone_id"
                        v-model="selectedZoneId"
                        class="form-select appearance-none"
                        required
                    >
                        <option :value="null" disabled>
                            Selecciona una zona
                        </option>

                        <option
                            v-for="zone in zones"
                            :key="zone.id"
                            :value="zone.id"
                        >
                            {{ zone.name }}
                        </option>
                    </select>

                    <ChevronDown
                        :size="16"
                        class="form-select-icon"
                    />
                </div>

                <p v-if="form.errors.zone_id" class="form-error">
                    {{ form.errors.zone_id }}
                </p>
            </div>

            <div
                v-if="loadingZone"
                class="mt-4 flex items-center gap-3 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4"
            >
                <Sparkles class="h-5 w-5 animate-pulse text-[#187744]" />
                <p class="text-sm text-[#435D4C]">
                    Cargando información de la zona...
                </p>
            </div>

            <div
                v-if="zoneError"
                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                role="alert"
            >
                <div class="flex items-start gap-2">
                    <Info class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>{{ zoneError }}</p>
                </div>
            </div>

            <div
                v-if="selectedZone && !loadingZone"
                class="mt-4 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                    >
                        <Map class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <h3 class="font-semibold text-[#244334]">
                            {{ selectedZone.name }}
                        </h3>

                        <p
                            v-if="selectedZone.description"
                            class="mt-1 text-sm text-[#718477]"
                        >
                            {{ selectedZone.description }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#435D4C] ring-1 ring-[#DCE7DF]"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                {{ zoneMarkers.length }} marcadores
                            </span>

                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-[#435D4C] ring-1 ring-[#DCE7DF]"
                            >
                                <CircleDot class="h-3.5 w-3.5" />
                                {{ zoneSpeciesLocations.length }} ubicaciones
                            </span>

                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1"
                                :class="
                                    zoneMapImage
                                        ? 'bg-[#EAF3ED] text-[#187744] ring-[#DCE7DF]'
                                        : 'bg-amber-50 text-amber-700 ring-amber-200'
                                "
                            >
                                {{ zoneMapImage ? 'Plano disponible' : 'Sin plano' }}
                            </span>
                        </div>

                        <p class="mt-3 text-xs leading-5 text-[#718477]">
                            El plano es una referencia visual. Coloca los nodos
                            dentro de los límites de la zona.
                        </p>
                    </div>
                </div>
            </div>
        </AdminCard>

        <!-- Editor y panel lateral -->
        <div
            class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="min-w-0">
                <AdminCard
                    title="Editor de caminos"
                    description="Haz clic sobre el mapa para crear nodos y conectar el recorrido."
                    :icon="Route"
                    :padding="false"
                >
                    <div class="p-3 sm:p-4">
                        <MapPathMap
                            v-model:coordinates="form.coordinates"
                            :zone-geometry="zoneGeometry"
                            :map-image="zoneMapImage"
                            :map-image-bounds="zoneMapImageBounds"
                            :markers="zoneMarkers"
                            :species-locations="zoneSpeciesLocations"
                            @update:distance="updateDistance"
                        />

                        <div
                            v-if="selectedZone && !zoneMapImage"
                            class="mt-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                        >
                            <Info
                                class="mt-0.5 h-5 w-5 shrink-0 text-amber-700"
                            />
                            <div>
                                <p class="text-sm font-semibold text-amber-800">
                                    La zona no tiene un plano configurado
                                </p>
                                <p class="mt-1 text-sm leading-5 text-amber-700">
                                    Puedes editar el recorrido usando el área
                                    delimitada de la zona como referencia.
                                </p>
                            </div>
                        </div>
                    </div>
                </AdminCard>
            </div>

            <aside class="flex min-w-0 flex-col gap-5">
                <!-- Herramientas -->
                <AdminCard
                    title="Herramientas"
                    description="Administra los nodos del camino."
                    :icon="Route"
                >
                    <div class="grid grid-cols-2 gap-2">
                        <AdminButton
                            type="button"
                            variant="secondary"
                            :icon="Undo2"
                            :disabled="nodeCount === 0"
                            block
                            @click="undoLastNode"
                        >
                            Deshacer
                        </AdminButton>

                        <AdminButton
                            type="button"
                            variant="danger"
                            :icon="Trash2"
                            :disabled="nodeCount === 0"
                            block
                            @click="clearPath"
                        >
                            Limpiar
                        </AdminButton>
                    </div>
                </AdminCard>

                <!-- Resumen -->
                <AdminCard
                    title="Resumen del camino"
                    description="Información calculada automáticamente."
                    :icon="Info"
                >
                    <div class="flex flex-col gap-4">
                        <div
                            class="flex items-center justify-between gap-3 border-b border-[#E5ECE7] pb-3"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-[#718477]"
                            >
                                <CircleDot class="h-4 w-4" />
                                Nodos
                            </span>
                            <span class="font-semibold text-[#244334]">
                                {{ nodeCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-b border-[#E5ECE7] pb-3"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-[#718477]"
                            >
                                <GitBranch class="h-4 w-4" />
                                Conexiones
                            </span>
                            <span class="font-semibold text-[#244334]">
                                {{ connectionCount }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-b border-[#E5ECE7] pb-3"
                        >
                            <span
                                class="flex items-center gap-2 text-sm text-[#718477]"
                            >
                                <Ruler class="h-4 w-4" />
                                Distancia
                            </span>
                            <span class="font-semibold text-[#244334]">
                                {{ form.distance }} m
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="flex items-center gap-2 text-sm text-[#718477]"
                            >
                                <Clock3 class="h-4 w-4" />
                                Tiempo estimado
                            </span>
                            <span class="font-semibold text-[#244334]">
                                {{ calculatedEstimatedTime }} min
                            </span>
                        </div>
                    </div>
                </AdminCard>

                <!-- Lista de nodos -->
                <AdminCard
                    title="Nodos del camino"
                    description="Consulta o elimina puntos del recorrido."
                    :icon="CircleDot"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-sm text-[#718477]">
                            Total de puntos
                        </span>
                        <span
                            class="rounded-full bg-[#EAF3ED] px-2.5 py-1 text-xs font-semibold text-[#187744]"
                        >
                            {{ nodeCount }}
                        </span>
                    </div>

                    <div
                        v-if="nodeCount === 0"
                        class="rounded-lg border border-dashed border-[#DCE7DF] px-4 py-8 text-center"
                    >
                        <CircleDot
                            class="mx-auto h-7 w-7 text-[#84958A]"
                        />
                        <p class="mt-2 text-sm font-medium text-[#435D4C]">
                            Aún no hay nodos
                        </p>
                        <p class="mt-1 text-xs text-[#718477]">
                            Agrega puntos desde el mapa.
                        </p>
                    </div>

                    <div
                        v-else
                        class="max-h-[360px] overflow-y-auto"
                    >
                        <div
                            v-for="node in form.coordinates.nodes"
                            :key="node.id"
                            class="flex items-center justify-between gap-3 border-b border-[#E5ECE7] py-3 last:border-b-0"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#187744] text-xs font-bold text-white"
                                >
                                    {{ node.id }}
                                </span>

                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[#244334]">
                                        Nodo {{ node.id }}
                                    </p>
                                    <p class="truncate text-xs text-[#718477]">
                                        {{ node.lat.toFixed(6) }},
                                        {{ node.lng.toFixed(6) }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                :aria-label="`Eliminar nodo ${node.id}`"
                                @click="removeNode(node.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                Eliminar
                            </button>
                        </div>
                    </div>
                </AdminCard>
            </aside>
        </div>

        <!-- Errores generales -->
        <div
            v-if="zoneError || Object.keys(form.errors).length > 0"
            class="rounded-xl border border-red-200 bg-red-50 p-4"
            role="alert"
        >
            <div class="flex items-center gap-2 text-sm font-semibold text-red-700">
                <Info class="h-4 w-4" />
                Revisa la información antes de continuar.
            </div>

            <ul
                v-if="Object.keys(form.errors).length > 0"
                class="mt-2 list-inside list-disc space-y-1 text-sm text-red-600"
            >
                <li
                    v-for="(error, key) in form.errors"
                    :key="key"
                >
                    {{ error }}
                </li>
            </ul>
        </div>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
        >
            <CancelButton
                :href="admin.mapPaths.index().url"
                :icon="X"
                label="Cancelar"
            />

            <AdminButton
                type="button"
                variant="primary"
                :icon="form.processing ? Sparkles : Save"
                :loading="form.processing"
                loading-text="Actualizando..."
                :disabled="!canSave"
                @click="submit"
            >
                Actualizar camino
            </AdminButton>
        </div>
    </div>
</template>