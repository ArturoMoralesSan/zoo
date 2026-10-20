
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FilterX, MapPin, Plus } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';
import admin from '@/routes/admin';

interface ZooZone {
    id: number;
    name: string;
    description: string | null;
    type: string | null;
    geometry: {
        type: string;
        coordinates: number[][][];
    } | null;
    is_active: boolean;
    species_locations_count: number;
    map_markers_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ZonesPagination {
    data: ZooZone[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    zones: ZonesPagination;
    filters: {
        search?: string;
        status?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');

const hasFilters = computed(
    () => Boolean(search.value.trim() || status.value),
);

const submitSearch = () => {
    router.get(
        admin.zooZones.index().url,
        {
            search: search.value.trim() || undefined,
            status: status.value || undefined,
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
    status.value = '';

    router.get(
        admin.zooZones.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteZone = (zone: ZooZone) => {
    Swal.fire({
        title: '¿Eliminar zona?',
        text: `Se eliminará "${zone.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.zooZones.destroy(zone.id).url, {
                preserveScroll: true,
            });
        }
    });
};
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Zonas del zoologico',
                href: admin.zooZones.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Zonas del zoológico" />

    <div class="flex h-full flex-1 flex-col gap-5 overflow-x-hidden p-4 sm:p-6">
        <PageHeader
            title="Zonas del zoológico"
            description="Administra las zonas geográficas del Zoológico Sahuatoba."
            section="Administración"
            :icon="MapPin"
            :action-icon="Plus"
            action-label="Nueva zona"
            :action-href="admin.zooZones.create().url"
        />

        <DataTable
            :pagination="zones"
            :is-empty="zones.data.length === 0"
            :empty-colspan="5"
            v-model:search="search"
            search-placeholder="Buscar zona..."
            :has-filters="hasFilters"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <select
                    v-model="status"
                    aria-label="Filtrar por estado"
                    class="w-full rounded-lg border border-[#DCE7DF] bg-white px-3 py-2.5 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/15 sm:w-48"
                    @change="submitSearch"
                >
                    <option value="">Todos los estados</option>
                    <option value="active">Activas</option>
                    <option value="inactive">Inactivas</option>
                </select>
            </template>

            <template #active-filters>
                <div
                    v-if="hasFilters"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span
                        v-if="search.trim()"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#EDF4EF] px-3 py-1.5 text-xs font-medium text-[#244334]"
                    >
                        <FilterX class="h-3.5 w-3.5" />
                        Búsqueda: {{ search }}
                    </span>

                    <span
                        v-if="status"
                        class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-[#EDF4EF] px-3 py-1.5 text-xs font-medium text-[#244334]"
                    >
                        Estado:
                        {{ status === 'active' ? 'Activas' : 'Inactivas' }}
                    </span>
                </div>
            </template>

            <template #table-head>
                <tr>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Zona
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Tipo
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Ubicación
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Estado
                    </th>
                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="zone in zones.data"
                    :key="zone.id"
                    class="transition-colors hover:bg-[#F6F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <MapPin class="h-5 w-5" />
                            </div>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ zone.name }}
                                </p>

                                <p
                                    v-if="zone.description"
                                    class="mt-1 max-w-sm truncate text-xs text-[#718277]"
                                    :title="zone.description"
                                >
                                    {{ zone.description }}
                                </p>

                                <p class="mt-1 text-xs text-[#87958B]">
                                    ID: {{ zone.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="zone.type"
                            class="inline-flex rounded-full border border-[#DCE7DF] bg-[#F6F9F6] px-2.5 py-1 text-xs font-medium text-[#385644]"
                        >
                            {{ zone.type }}
                        </span>

                        <span
                            v-else
                            class="text-xs text-[#87958B]"
                        >
                            Sin tipo
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-2">
                            <MapPin
                                class="mt-0.5 h-4 w-4 shrink-0 text-[#187744]"
                            />

                            <div>
                                <p class="font-medium text-[#244334]">
                                    {{
                                        zone.geometry
                                            ? 'Polígono definido'
                                            : 'Sin polígono'
                                    }}
                                </p>

                                <p class="mt-1 text-xs text-[#718277]">
                                    {{ zone.species_locations_count }}
                                    ubicación(es)
                                </p>

                                <p class="text-xs text-[#718277]">
                                    {{ zone.map_markers_count }}
                                    marcador(es)
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="zone.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500" />
                            Inactiva
                        </span>
                    </td>

                    <TableActions
                        :view-url="admin.zooZones.show(zone.id).url"
                        :edit-url="admin.zooZones.edit(zone.id).url"
                        item-name="zona"
                        @delete="deleteZone(zone)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex flex-col items-center justify-center gap-3 py-8">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                    >
                        <MapPin class="h-6 w-6" />
                    </div>

                    <div class="text-center">
                        <p class="font-medium text-[#244334]">
                            No se encontraron zonas
                        </p>

                        <p class="mt-1 text-sm text-[#718277]">
                            {{
                                hasFilters
                                    ? 'Prueba cambiando los filtros o limpia la búsqueda.'
                                    : 'Todavía no hay zonas registradas.'
                            }}
                        </p>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>
