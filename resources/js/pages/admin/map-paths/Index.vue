
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleDot,
    Clock3,
    FilterX,
    GitBranch,
    Plus,
    Route,
    Ruler,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

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

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = () => {
    router.get(
        admin.mapPaths.index().url,
        {
            search: search.value.trim() || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';

    router.get(
        admin.mapPaths.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
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
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.mapPaths.destroy(mapPath.id).url, {
                preserveScroll: true,
            });
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

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Caminos',
                href: admin.mapPaths.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Caminos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Caminos"
            description="Administra la red de caminos peatonales internos del zoológico."
            section="Administración"
            :icon="Route"
            :action-icon="Plus"
            action-label="Nuevo camino"
            :action-href="admin.mapPaths.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="mapPaths"
            :is-empty="mapPaths.data.length === 0"
            :empty-colspan="7"
            search-placeholder="Buscar camino..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th
                        v-for="heading in [
                            'Camino',
                            'Nodos',
                            'Conexiones',
                            'Distancia',
                            'Tiempo',
                            'Estado',
                            'Acciones',
                        ]"
                        :key="heading"
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                        :class="heading === 'Acciones' ? 'text-right' : ''"
                    >
                        {{ heading }}
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="mapPath in mapPaths.data"
                    :key="mapPath.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Camino -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[200px] items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#dce7df] bg-[#e4f2e8] text-[#187744]"
                            >
                                <Route :size="18" />
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ mapPath.name }}
                                </p>

                                <p
                                    v-if="mapPath.description"
                                    class="mt-1 max-w-xs truncate text-xs text-[#718477]"
                                >
                                    {{ mapPath.description }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ mapPath.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Nodos -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2">
                            <CircleDot
                                :size="16"
                                class="text-[#718477]"
                            />

                            <span
                                class="inline-flex min-w-8 items-center justify-center rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-semibold text-[#435d4c]"
                            >
                                {{ getNodeCount(mapPath) }}
                            </span>
                        </div>
                    </td>

                    <!-- Conexiones -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2">
                            <GitBranch
                                :size="16"
                                class="text-[#718477]"
                            />

                            <span
                                class="inline-flex min-w-8 items-center justify-center rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-semibold text-[#435d4c]"
                            >
                                {{ getConnectionCount(mapPath) }}
                            </span>
                        </div>
                    </td>

                    <!-- Distancia -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2 text-sm text-[#435d4c]">
                            <Ruler
                                :size="16"
                                class="shrink-0 text-[#718477]"
                            />

                            {{ formatDistance(mapPath.distance) }}
                        </div>
                    </td>

                    <!-- Tiempo -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2 text-sm text-[#435d4c]">
                            <Clock3
                                :size="16"
                                class="shrink-0 text-[#718477]"
                            />

                            {{ formatTime(mapPath.estimated_time) }}
                        </div>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="mapPath.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#c9e4d2] bg-[#dcefe2] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#187744]" />
                            Activo
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#f0cccc] bg-[#fceaea] px-3 py-1.5 text-xs font-semibold text-[#b42323]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#c24141]" />
                            Inactivo
                        </span>
                    </td>

                    <!-- Acciones -->
                    <TableActions
                        :view-url="admin.mapPaths.show(mapPath.id).url"
                        :edit-url="admin.mapPaths.edit(mapPath.id).url"
                        item-name="camino"
                        @delete="deleteMapPath(mapPath)"
                    />
                </tr>
            </template>

            <template #empty>
                <div
                    class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center"
                >
                    <div
                        class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]"
                    >
                        <FilterX v-if="hasFilters()" :size="27" />
                        <Route v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay caminos'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos caminos que coincidan con tu búsqueda. Intenta con otro término.'
                                : 'Todavía no hay caminos registrados para mostrar en el mapa.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
