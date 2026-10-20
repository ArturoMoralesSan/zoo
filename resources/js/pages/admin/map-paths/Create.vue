<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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
    ChevronDown,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import MapPathMap from '@/components/admin/MapPathMap.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
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

const selectedZone = computed(
    () =>
        props.zones.find(
            (zone) => zone.id === selectedZoneId.value,
        ) ?? null,
);

const zoneGeometry = ref<GeoJsonGeometry | null>(null);
const zoneMapImage = ref<string | null>(null);
const zoneMapImageBounds = ref<MapImageBounds | null>(null);
const zoneMarkers = ref<MapMarker[]>([]);
const zoneSpeciesLocations = ref<SpeciesLocation[]>([]);
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

const nodeCount = computed(() => form.coordinates.nodes.length);
const connectionCount = computed(() => form.coordinates.edges.length);

const calculatedEstimatedTime = computed(() => {
    if (form.distance <= 0) {
        return 0;
    }

    return Math.ceil(
        form.distance / WALKING_SPEED_METERS_PER_MINUTE,
    );
});

const canSave = computed(
    () =>
        !form.processing &&
        selectedZoneId.value !== null &&
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
                  form.distance / WALKING_SPEED_METERS_PER_MINUTE,
              )
            : 0;
}

function updateDistance(distance: number): void {
    form.distance = Math.round(distance);
    form.estimated_time =
        form.distance > 0
            ? Math.ceil(
                  form.distance / WALKING_SPEED_METERS_PER_MINUTE,
              )
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

    if (nodes.length === 0) {
        return;
    }

    removeNode(nodes[nodes.length - 1].id);
}

function clearPath(): void {
    form.coordinates = {
        nodes: [],
        edges: [],
    };

    form.distance = 0;
    form.estimated_time = 0;
}

async function loadZone(zoneId: number | null): Promise<void> {
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
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = (await response.json()) as ZoneResponse;

        zoneGeometry.value = data.zone.geometry ?? null;
        zoneMapImage.value = data.zone.map_image ?? null;
        zoneMapImageBounds.value = data.zone.map_image_bounds ?? null;
        zoneMarkers.value = data.markers ?? [];
        zoneSpeciesLocations.value = data.speciesLocations ?? [];
    } catch (error) {
        console.error('Error cargando la zona:', error);

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

watch(selectedZoneId, async (zoneId) => {
    form.zone_id = zoneId;
    await loadZone(zoneId);
});

watch(
    () => form.coordinates,
    () => {
        updateDistanceFromGraph();
    },
    { deep: true },
);

function submit(): void {
    if (!selectedZoneId.value) {
        zoneError.value =
            'Debes seleccionar una zona antes de guardar el camino.';
        return;
    }

    if (form.coordinates.nodes.length < 2) {
        zoneError.value = 'Debes crear al menos 2 nodos.';
        return;
    }

    if (form.coordinates.edges.length < 1) {
        zoneError.value =
            'Debes crear al menos una conexión entre nodos.';
        return;
    }

    zoneError.value = null;
    form.zone_id = selectedZoneId.value;

    form.post(admin.mapPaths.store().url, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Crear camino" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Crear camino"
            description="Define la red de caminos internos del zoológico."
            section="Mapa del zoológico"
            :icon="Route"
            :action-icon="ArrowLeft"
            action-label="Volver a caminos"
            :action-href="admin.mapPaths.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Define la información básica del camino."
                :icon="Info"
            >
                <div class="form-grid">
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre del camino
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Camino principal"
                            :class="[
                                'form-input',
                                { 'form-input-error': form.errors.name },
                            ]"
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
                            :class="[
                                'form-input',
                                { 'form-input-error': form.errors.order },
                            ]"
                        />

                        <p class="form-help">
                            Determina la posición del camino en los listados.
                        </p>

                        <p v-if="form.errors.order" class="form-error">
                            {{ form.errors.order }}
                        </p>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Describe este camino..."
                            :class="[
                                'form-textarea',
                                {
                                    'form-textarea-error':
                                        form.errors.description,
                                },
                            ]"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="form-field form-field-full">
                        <label class="form-checkbox-row">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="form-checkbox"
                            />

                            <span>
                                <span class="form-checkbox-title block">
                                    Camino activo
                                </span>

                                <span
                                    class="form-checkbox-description block"
                                >
                                    El camino estará disponible para mostrarse
                                    en el mapa del zoológico.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            </AdminCard>

            <!-- Selección de zona -->
            <AdminCard
                title="Zona del zoológico"
                description="Selecciona la zona donde vas a dibujar el camino."
                :icon="MapPin"
            >
                <div class="form-field">
                    <label for="zone" class="form-label">
                        Zona
                        <span class="form-required">*</span>
                    </label>

                    <div class="form-select-wrapper">
                        <select
                            id="zone"
                            v-model="selectedZoneId"
                            required
                            :class="[
                                'form-select appearance-none',
                                {
                                    'form-select-error': form.errors.zone_id,
                                },
                            ]"
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
                    class="form-info mt-5"
                >
                    <Sparkles class="form-info-icon animate-pulse" />

                    <p class="form-info-text">
                        Cargando información de la zona...
                    </p>
                </div>

                <div
                    v-if="zoneError"
                    class="mt-5 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                >
                    <Info class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ zoneError }}</span>
                </div>

                <div
                    v-if="selectedZone && !loadingZone"
                    class="mt-5 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4"
                >
                    <div
                        class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div>
                            <div class="flex items-center gap-2 text-[#718477]">
                                <MapPin class="h-4 w-4" />
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide"
                                >
                                    Zona
                                </span>
                            </div>

                            <p class="mt-1.5 font-semibold text-[#244334]">
                                {{ selectedZone.name }}
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 text-[#718477]">
                                <MapPin class="h-4 w-4" />
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide"
                                >
                                    Puntos de interés
                                </span>
                            </div>

                            <p class="mt-1.5 font-semibold text-[#244334]">
                                {{ zoneMarkers.length }}
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 text-[#718477]">
                                <CircleDot class="h-4 w-4" />
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide"
                                >
                                    Especies
                                </span>
                            </div>

                            <p class="mt-1.5 font-semibold text-[#244334]">
                                {{ zoneSpeciesLocations.length }}
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 text-[#718477]">
                                <Map class="h-4 w-4" />
                                <span
                                    class="text-xs font-semibold uppercase tracking-wide"
                                >
                                    Plano
                                </span>
                            </div>

                            <p
                                class="mt-1.5 flex items-center gap-1.5 font-semibold text-[#244334]"
                            >
                                <Check
                                    v-if="zoneMapImage"
                                    class="h-4 w-4 text-[#187744]"
                                />
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
                        class="mt-4 border-t border-[#DCE7DF] pt-4 text-sm leading-6 text-[#718477]"
                    >
                        {{ selectedZone.description }}
                    </p>

                    <div class="form-info mt-4">
                        <Info class="form-info-icon" />

                        <p class="form-info-text">
                            El plano de la zona se utiliza como referencia
                            visual. Los nodos del camino deben ubicarse dentro
                            del área de la zona.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Editor de caminos y panel lateral -->
            <div
                class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_340px]"
            >
                <AdminCard
                    title="Editor de caminos"
                    description="Haz clic sobre el mapa para crear nodos y conectar los caminos."
                    :icon="Route"
                >
                    <MapPathMap
                        v-model:coordinates="form.coordinates"
                        :zone-geometry="zoneGeometry"
                        :map-image="zoneMapImage"
                        :map-image-bounds="zoneMapImageBounds"
                        :markers="zoneMarkers"
                        :species-locations="zoneSpeciesLocations"
                        @update:distance="updateDistance"
                    />
                </AdminCard>

                <aside class="flex flex-col gap-5">
                    <!-- Herramientas -->
                    <AdminCard
                        title="Herramientas"
                        description="Administra los nodos del camino."
                        :icon="Route"
                    >
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                :disabled="nodeCount === 0"
                                class="form-button form-button-secondary"
                                @click="undoLastNode"
                            >
                                <Undo2 class="h-4 w-4" />
                                Deshacer
                            </button>

                            <button
                                type="button"
                                :disabled="nodeCount === 0"
                                class="form-button form-button-danger"
                                @click="clearPath"
                            >
                                <Trash2 class="h-4 w-4" />
                                Limpiar
                            </button>
                        </div>
                    </AdminCard>

                    <!-- Estado del editor -->
                    <AdminCard
                        title="Estado del editor"
                        description="Indicaciones para dibujar el camino."
                        :icon="CircleDot"
                    >
                        <div class="form-info">
                            <Info class="form-info-icon" />

                            <p class="form-info-text">
                                <template v-if="nodeCount === 0">
                                    Haz clic en el mapa para crear el primer
                                    nodo.
                                </template>

                                <template v-else>
                                    Selecciona un nodo directamente en el mapa
                                    para conectarlo con otro.
                                </template>
                            </p>
                        </div>
                    </AdminCard>

                    <!-- Resumen -->
                    <AdminCard
                        title="Resumen del camino"
                        description="Datos calculados automáticamente."
                        :icon="Info"
                    >
                        <div class="flex flex-col gap-4">
                            <div
                                class="flex items-center justify-between border-b border-[#E5ECE7] pb-3"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-[#718477]"
                                >
                                    <CircleDot class="h-4 w-4" />
                                    Nodos
                                </div>

                                <span class="font-semibold text-[#244334]">
                                    {{ nodeCount }}
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between border-b border-[#E5ECE7] pb-3"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-[#718477]"
                                >
                                    <GitBranch class="h-4 w-4" />
                                    Conexiones
                                </div>

                                <span class="font-semibold text-[#244334]">
                                    {{ connectionCount }}
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between border-b border-[#E5ECE7] pb-3"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm text-[#718477]"
                                >
                                    <Ruler class="h-4 w-4" />
                                    Distancia
                                </div>

                                <span class="font-semibold text-[#244334]">
                                    {{ form.distance }} m
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <div
                                    class="flex items-center gap-2 text-sm text-[#718477]"
                                >
                                    <Clock3 class="h-4 w-4" />
                                    Tiempo estimado
                                </div>

                                <span class="font-semibold text-[#244334]">
                                    {{ calculatedEstimatedTime }} min
                                </span>
                            </div>
                        </div>
                    </AdminCard>

                    <!-- Lista de nodos -->
                    <AdminCard
                        title="Nodos creados"
                        description="Consulta o elimina puntos del camino."
                    >
                        <template #header>
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="form-section-title">
                                        Nodos creados
                                    </h3>

                                    <p class="form-section-description">
                                        Consulta o elimina puntos del camino.
                                    </p>
                                </div>

                                <span
                                    class="rounded-full bg-[#EAF3ED] px-2.5 py-1 text-xs font-semibold text-[#187744]"
                                >
                                    {{ nodeCount }}
                                </span>
                            </div>
                        </template>

                        <div
                            v-if="nodeCount === 0"
                            class="rounded-lg border border-dashed border-[#DCE7DF] px-4 py-8 text-center"
                        >
                            <CircleDot
                                class="mx-auto h-7 w-7 text-[#84958A]"
                            />

                            <p
                                class="mt-2 text-sm font-medium text-[#435D4C]"
                            >
                                Aún no hay nodos
                            </p>

                            <p class="mt-1 text-xs text-[#718477]">
                                Haz clic en el mapa para agregar puntos.
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
                                <div
                                    class="flex min-w-0 items-center gap-3"
                                >
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#187744] text-xs font-bold text-white"
                                    >
                                        {{ node.id }}
                                    </span>

                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-[#244334]"
                                        >
                                            Nodo {{ node.id }}
                                        </p>

                                        <p
                                            class="truncate text-xs text-[#718477]"
                                        >
                                            {{ node.lat.toFixed(6) }},
                                            {{ node.lng.toFixed(6) }}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
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

            <!-- Errores -->
            <div
                v-if="form.errors.coordinates"
                class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ form.errors.coordinates }}</span>
            </div>

            <div
                v-if="Object.keys(form.errors).length > 0"
                class="rounded-xl border border-red-200 bg-red-50 p-4"
            >
                <div
                    class="flex items-center gap-2 text-sm font-semibold text-red-700"
                >
                    <Info class="h-4 w-4" />
                    Hay errores en el formulario:
                </div>

                <ul
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
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-[#718477]">
                    Para guardar, selecciona una zona y crea al menos dos
                    nodos conectados.
                </p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <CancelButton
                        :href="admin.mapPaths.index().url"
                        :icon="X"
                        label="Cancelar"
                    />

                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="Save"
                        :loading="form.processing"
                        loading-text="Guardando..."
                        :disabled="!canSave"
                    >
                        Guardar camino
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>