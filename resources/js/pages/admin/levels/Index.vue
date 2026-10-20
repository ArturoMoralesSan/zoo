
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Award,
    Plus,
    Users,
    FilterX,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface Level {
    id: number;
    name: string;
    min_points: number;
    max_points: number | null;
    description: string | null;
    users_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface LevelsPagination {
    data: Level[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface Filters {
    search?: string;
}

const props = defineProps<{
    levels: LevelsPagination;
    filters: Filters;
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = () => {
    router.get(
        admin.levels.index().url,
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
        admin.levels.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteLevel = (level: Level) => {
    Swal.fire({
        title: '¿Eliminar nivel?',
        text: `Se eliminará el nivel "${level.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.levels.destroy(level.id).url, {
                preserveScroll: true,
            });
        }
    });
};

const formatPoints = (points: number) =>
    new Intl.NumberFormat('es-MX').format(points);

defineOptions({
layout: {
    breadcrumbs: [
        {
            title: 'Panel',
            href: admin.dashboard(),
        },
        {
            title: 'Niveles',
            href: admin.levels.index,
        },
    ],
},
});
</script>

<template>
    <Head title="Niveles" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Niveles"
            description="Administra los niveles y puntos de los visitantes."
            section="Administración"
            :icon="Award"
            :action-icon="Plus"
            action-label="Nuevo nivel"
            :action-href="admin.levels.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="levels"
            :is-empty="levels.data.length === 0"
            :empty-colspan="4"
            search-placeholder="Buscar nivel..."
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
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                    >
                        Nivel
                    </th>

                    <th
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                    >
                        Rango de puntos
                    </th>

                    <th
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                    >
                        Usuarios
                    </th>

                    <th
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                    >
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="level in levels.data"
                    :key="level.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[200px] items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e4f2e8] text-[#187744]"
                            >
                                <Award :size="19" />
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold text-[#244334]"
                                >
                                    {{ level.name }}
                                </p>

                                <p
                                    v-if="level.description"
                                    class="mt-1 max-w-sm text-xs leading-5 text-[#718477]"
                                >
                                    {{ level.description }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ level.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center rounded-full border border-[#c9e4d2] bg-[#edf5ef] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            {{ formatPoints(level.min_points) }}
                            –
                            {{
                                level.max_points !== null
                                    ? formatPoints(level.max_points)
                                    : '∞'
                            }}
                            pts
                        </span>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#dce7df] bg-[#f7faf8] px-3 py-1.5 text-xs font-semibold text-[#435d4c]"
                        >
                            <Users :size="14" />

                            {{ formatPoints(level.users_count) }}

                            {{
                                level.users_count === 1
                                    ? 'usuario'
                                    : 'usuarios'
                            }}
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.levels.edit(level.id).url"
                        item-name="nivel"
                        @delete="deleteLevel(level)"
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
                        <Award v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay niveles'
                        }}
                    </h3>

                    <p
                        class="mt-2 max-w-sm text-sm leading-6 text-[#718477]"
                    >
                        {{
                            hasFilters()
                                ? 'No encontramos niveles que coincidan con tu búsqueda. Intenta con otro término.'
                                : 'Todavía no hay niveles registrados para mostrar.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
