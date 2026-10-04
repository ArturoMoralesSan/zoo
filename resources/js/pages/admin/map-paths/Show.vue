<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowLeft,
    Check,
    CircleDot,
    Clock3,
    Edit,
    Eye,
    FileText,
    GitBranch,
    Info,
    Map,
    MapPin,
    Route,
    Ruler,
    X,
} from 'lucide-vue-next';

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
    type: string | null;
    geometry: GeoJsonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
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
    coordinates: PathData;
    distance: number | null;
    estimated_time: number | null;
    is_active: boolean;
    order: number;
    created_at: string;
    updated_at: string;
    zone?: Zone | null;
}

const props = defineProps<{
    mapPath: MapPath;
}>();

const nodeCount = computed(
    () => props.mapPath.coordinates?.nodes?.length ?? 0,
);

const connectionCount = computed(
    () => props.mapPath.coordinates?.edges?.length ?? 0,
);

const formatDistance = (distance: number | null) => {
    if (distance === null || distance === undefined) {
        return 'Sin calcular';
    }

    if (distance >= 1000) {
        return `${(distance / 1000).toFixed(2)} km`;
    }

    return `${distance} m`;
};

const formatTime = (minutes: number | null) => {
    if (
        minutes === null ||
        minutes === undefined ||
        minutes <= 0
    ) {
        return 'Sin calcular';
    }

    if (minutes === 1) {
        return '1 minuto';
    }

    return `${minutes} minutos`;
};

const formatDate = (date: string) => {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

const zone = computed(() => props.mapPath.zone ?? null);

const hasMapImage = computed(() => !!zone.value?.map_image);

const hasZoneGeometry = computed(() => !!zone.value?.geometry);
</script>

<template>
    <Head :title="`Camino: ${mapPath.name}`" />

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
                <div class="flex min-w-0 items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Route class="h-5 w-5" />
                    </div>

                    <div class="min-w-0">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                            >
                                ID: {{ mapPath.id }}
                            </span>

                            <span
                                v-if="mapPath.is_active"
                                class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                            >
                                <Check class="h-3.5 w-3.5" />
                                Activo
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                            >
                                <X class="h-3.5 w-3.5" />
                                Inactivo
                            </span>
                        </div>

                        <h1 class="truncate text-2xl font-semibold">
                            {{ mapPath.name }}
                        </h1>

                        <p
                            v-if="mapPath.description"
                            class="mt-1 max-w-3xl text-sm text-muted-foreground"
                        >
                            {{ mapPath.description }}
                        </p>

                        <p
                            v-else
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Sin descripción.
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                    <Link
                        :href="admin.mapPaths.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Regresar
                    </Link>

                    <Link
                        :href="admin.mapPaths.edit(mapPath.id).url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        <Edit class="h-4 w-4" />
                        Editar
                    </Link>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Nodos -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <CircleDot class="h-5 w-5" />
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">
                            Nodos
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ nodeCount }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Puntos de la red
                </p>
            </div>

            <!-- Conexiones -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <GitBranch class="h-5 w-5" />
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">
                            Conexiones
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ connectionCount }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Segmentos del camino
                </p>
            </div>

            <!-- Distancia -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Ruler class="h-5 w-5" />
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">
                            Distancia total
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ formatDistance(mapPath.distance) }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Suma de las conexiones
                </p>
            </div>

            <!-- Tiempo -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-5 dark:border-sidebar-border"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Clock3 class="h-5 w-5" />
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">
                            Tiempo estimado
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ formatTime(mapPath.estimated_time) }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    Caminando
                </p>
            </div>
        </div>

        <!-- Zona -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <MapPin class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Zona del zoológico
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Información de la zona a la que pertenece este
                            camino.
                        </p>
                    </div>
                </div>

                <div
                    v-if="zone"
                    class="rounded-full border border-sidebar-border px-3 py-1 text-xs font-medium"
                >
                    {{ zone.name }}
                </div>
            </div>

            <div
                v-if="zone"
                class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Zona
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ zone.name }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Tipo
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ zone.type ?? '—' }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Área delimitada
                    </p>
                    <p
                        class="mt-1 text-sm font-medium"
                        :class="
                            hasZoneGeometry
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-amber-600 dark:text-amber-400'
                        "
                    >
                        {{
                            hasZoneGeometry
                                ? 'Configurada'
                                : 'Sin geometría'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Plano
                    </p>
                    <p
                        class="mt-1 text-sm font-medium"
                        :class="
                            hasMapImage
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-amber-600 dark:text-amber-400'
                        "
                    >
                        {{ hasMapImage ? 'Configurado' : 'Sin plano' }}
                    </p>
                </div>
            </div>

            <div
                v-if="zone?.description"
                class="mt-4 flex items-start gap-3 rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
            >
                <FileText
                    class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                />

                <p class="text-sm text-muted-foreground">
                    {{ zone.description }}
                </p>
            </div>

            <div
                v-else-if="!zone"
                class="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />

                <span>
                    Este camino no tiene una zona asociada.
                </span>
            </div>
        </div>

        <!-- Mapa -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Map class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Red del camino
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Vista del camino interno con sus nodos, conexiones,
                            zona y plano.
                        </p>
                    </div>
                </div>

                <span
                    class="inline-flex w-fit items-center gap-2 rounded-full border border-sidebar-border px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    <Eye class="h-3.5 w-3.5" />
                    Solo lectura
                </span>
            </div>

            <MapPathMap
                :coordinates="mapPath.coordinates"
                :zone-geometry="zone?.geometry ?? null"
                :map-image="zone?.map_image ?? null"
                :map-image-bounds="zone?.map_image_bounds ?? null"
                readonly
            />

            <div
                v-if="zone && !hasMapImage"
                class="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />

                <span>
                    Esta zona no tiene un plano configurado. El camino se
                    muestra directamente sobre el área geográfica de la zona.
                </span>
            </div>

            <div
                v-else-if="zone && hasMapImage"
                class="mt-4 flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-800 dark:bg-blue-950/30 dark:text-blue-300"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />

                <span>
                    El plano se utiliza como referencia visual. La red del
                    camino corresponde a las coordenadas geográficas
                    almacenadas.
                </span>
            </div>
        </div>

        <!-- Información -->
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
                        Información
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Datos administrativos y estado del camino.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Orden
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ mapPath.order }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Estado
                    </p>

                    <p
                        class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium"
                        :class="
                            mapPath.is_active
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        "
                    >
                        <Check
                            v-if="mapPath.is_active"
                            class="h-4 w-4"
                        />

                        <X
                            v-else
                            class="h-4 w-4"
                        />

                        {{
                            mapPath.is_active
                                ? 'Activo'
                                : 'Inactivo'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Creado
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ formatDate(mapPath.created_at) }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-4"
                >
                    <p class="text-xs text-muted-foreground">
                        Última actualización
                    </p>
                    <p class="mt-1 text-sm font-medium">
                        {{ formatDate(mapPath.updated_at) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Lista de nodos -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <CircleDot class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Nodos del camino
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Coordenadas almacenadas para cada punto de la red.
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-full border border-sidebar-border px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    {{ nodeCount }} nodos ·
                    {{ connectionCount }} conexiones
                </div>
            </div>

            <div
                v-if="nodeCount > 0"
                class="mt-6 overflow-hidden rounded-lg border border-sidebar-border/70"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40"
                        >
                            <tr>
                                <th class="px-4 py-3 font-semibold">
                                    Nodo
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    Latitud
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    Longitud
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-sidebar-border/70"
                        >
                            <tr
                                v-for="node in mapPath.coordinates.nodes"
                                :key="node.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                        >
                                            {{ node.id }}
                                        </span>

                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Nodo
                                        </span>
                                    </div>
                                </td>

                                <td
                                    class="px-4 py-3 font-mono text-xs"
                                >
                                    {{ node.lat }}
                                </td>

                                <td
                                    class="px-4 py-3 font-mono text-xs"
                                >
                                    {{ node.lng }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-else
                class="mt-6 flex flex-col items-center justify-center rounded-lg border border-sidebar-border p-8 text-center"
            >
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                >
                    <CircleDot class="h-5 w-5" />
                </div>

                <p class="mt-3 text-sm font-medium">
                    Este camino no tiene nodos.
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    No existen puntos almacenados para esta red.
                </p>
            </div>
        </div>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
        >
            <Link
                :href="admin.mapPaths.index().url"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
            >
                <X class="h-4 w-4" />
                Cerrar
            </Link>

            <Link
                :href="admin.mapPaths.edit(mapPath.id).url"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                <Edit class="h-4 w-4" />
                Editar camino
            </Link>
        </div>
    </div>
</template>