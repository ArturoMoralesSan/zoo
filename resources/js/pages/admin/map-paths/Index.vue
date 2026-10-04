<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import {
    ArrowLeft,
    CircleDot,
    Clock3,
    Edit,
    Eye,
    GitBranch,
    Map,
    Plus,
    Route,
    Ruler,
    Search,
    Trash2,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

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
    name: string;
    description: string | null;
    coordinates: PathData;
    distance: number | null;
    estimated_time: number | null;
    is_active: boolean;
    order: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MapPathsPagination {
    data: MapPath[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    mapPaths: MapPathsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.mapPaths.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteMapPath = (mapPath: MapPath) => {
    Swal.fire({
        title: '¿Eliminar camino?',
        text: `Se eliminará el camino "${mapPath.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.mapPaths.destroy(mapPath.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const getNodeCount = (mapPath: MapPath): number => {
    return mapPath.coordinates?.nodes?.length ?? 0;
};

const getConnectionCount = (mapPath: MapPath): number => {
    return mapPath.coordinates?.edges?.length ?? 0;
};

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
    if (minutes === null || minutes === undefined) {
        return 'Sin calcular';
    }

    if (minutes === 1) {
        return '1 minuto';
    }

    return `${minutes} minutos`;
};
</script>

<template>
    <Head title="Caminos" />

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
                            Caminos
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra la red de caminos peatonales
                            internos del zoológico.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.mapPaths.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo camino
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar camino..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Route class="h-4 w-4" />
                    {{ mapPaths.total }}
                    {{ mapPaths.total === 1 ? 'camino' : 'caminos' }}
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Camino
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Nodos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Conexiones
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Distancia
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Tiempo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 text-right font-semibold"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="mapPath in mapPaths.data"
                            :key="mapPath.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Camino -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary"
                                    >
                                        <Route class="h-4 w-4" />
                                    </span>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ mapPath.name }}
                                        </div>

                                        <div
                                            v-if="mapPath.description"
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{ mapPath.description }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ mapPath.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Nodos -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <CircleDot
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span
                                        class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ getNodeCount(mapPath) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Conexiones -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <GitBranch
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span
                                        class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ getConnectionCount(mapPath) }}
                                    </span>
                                </div>
                            </td>

                            <!-- Distancia -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Ruler
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span>
                                        {{
                                            formatDistance(
                                                mapPath.distance,
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- Tiempo -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Clock3
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span>
                                        {{
                                            formatTime(
                                                mapPath.estimated_time,
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="mapPath.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-green-500"
                                    />
                                    Activo
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-red-500"
                                    />
                                    Inactivo
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.mapPaths.show(
                                                mapPath.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Eye class="h-4 w-4" />
                                        Ver
                                    </Link>

                                    <Link
                                        :href="
                                            admin.mapPaths.edit(
                                                mapPath.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="
                                            deleteMapPath(
                                                mapPath,
                                            )
                                        "
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="
                                mapPaths.data.length === 0
                            "
                        >
                            <td
                                colspan="7"
                                class="px-6 py-16 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <Route
                                            class="h-6 w-6"
                                        />
                                    </div>

                                    <h3
                                        class="mt-4 text-sm font-semibold text-foreground"
                                    >
                                        No se encontraron caminos
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        No hay caminos que coincidan
                                        con la búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="mapPaths.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in mapPaths.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-2 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border hover:bg-accent'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="rounded-lg border border-sidebar-border px-3 py-2 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>