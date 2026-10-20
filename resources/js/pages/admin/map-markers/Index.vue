
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    MapPin,
    Plus,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface ZooZone {
    id: number;
    name: string;
}

interface MapMarker {
    id: number;
    name: string;
    type: string | null;
    description: string | null;
    latitude: number;
    longitude: number;
    icon: string | null;
    color: string | null;
    zone: ZooZone | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MapMarkersPagination {
    data: MapMarker[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    markers: MapMarkersPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = () => {
    router.get(
        admin.mapMarkers.index().url,
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
        admin.mapMarkers.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteMarker = (marker: MapMarker) => {
    Swal.fire({
        title: '¿Eliminar marcador?',
        text: `Se eliminará el marcador "${marker.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.mapMarkers.destroy(marker.id).url, {
                preserveScroll: true,
            });
        }
    });
};

const formatCoordinate = (value: number) =>
    Number(value).toFixed(7);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Marcadores',
                href: admin.mapMarkers.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Marcadores del mapa" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Marcadores del mapa"
            description="Administra los puntos y ubicaciones del mapa del zoológico."
            section="Administración"
            :icon="MapPin"
            :action-icon="Plus"
            action-label="Nuevo marcador"
            :action-href="admin.mapMarkers.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="markers"
            :is-empty="markers.data.length === 0"
            :empty-colspan="6"
            search-placeholder="Buscar marcador..."
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
                            'Marcador',
                            'Tipo',
                            'Zona',
                            'Coordenadas',
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
                    v-for="marker in markers.data"
                    :key="marker.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Marcador -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[220px] items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#dce7df]"
                                :style="
                                    marker.color
                                        ? { backgroundColor: marker.color }
                                        : { backgroundColor: '#f2f7f4' }
                                "
                            >
                                <img
                                    v-if="marker.icon"
                                    :src="`/storage/markers/${marker.icon}`"
                                    :alt="marker.name"
                                    class="h-6 w-6 object-contain"
                                />

                                <MapPin
                                    v-else
                                    :size="20"
                                    class="text-[#187744]"
                                />
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ marker.name }}
                                </p>

                                <p
                                    v-if="marker.description"
                                    class="mt-1 max-w-xs truncate text-xs text-[#718477]"
                                >
                                    {{ marker.description }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ marker.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Tipo -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="marker.type"
                            class="inline-flex items-center rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                        >
                            {{ marker.type }}
                        </span>

                        <span v-else class="text-sm text-[#84958a]">
                            Sin tipo
                        </span>
                    </td>

                    <!-- Zona -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="marker.zone"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                        >
                            <MapPin :size="13" />
                            {{ marker.zone.name }}
                        </span>

                        <span v-else class="text-sm text-[#84958a]">
                            Sin zona
                        </span>
                    </td>

                    <!-- Coordenadas -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <p class="font-mono text-xs text-[#244334]">
                            {{ formatCoordinate(marker.latitude) }}
                        </p>

                        <p class="mt-1 font-mono text-xs text-[#718477]">
                            {{ formatCoordinate(marker.longitude) }}
                        </p>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="marker.is_active"
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

                    <!-- Acciones reutilizables -->
                    <TableActions
                        :view-url="admin.mapMarkers.show(marker.id).url"
                        :edit-url="admin.mapMarkers.edit(marker.id).url"
                        item-name="marcador"
                        @delete="deleteMarker(marker)"
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
                        <MapPin v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay marcadores'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos marcadores que coincidan con tu búsqueda. Intenta con otro término.'
                                : 'Todavía no hay marcadores registrados para mostrar en el mapa.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
