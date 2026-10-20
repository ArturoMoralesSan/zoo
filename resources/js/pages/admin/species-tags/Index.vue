
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    Plus,
    Tags,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface Tag {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    species_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TagsPagination {
    data: Tag[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    tags: TagsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = (): boolean => {
    return Boolean(search.value.trim());
};

const submitSearch = (): void => {
    router.get(
        admin.speciesTags.index().url,
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

const clearFilters = (): void => {
    search.value = '';

    router.get(
        admin.speciesTags.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteTag = async (tag: Tag): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar etiqueta?',
        text: `Se eliminará la etiqueta "${tag.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#6B7280',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(admin.speciesTags.destroy(tag.id).url, {
        preserveScroll: true,
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
                title: 'Etiquetas de especies',
                href: admin.speciesTags.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Etiquetas" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Etiquetas"
            description="Administra las etiquetas utilizadas por las especies."
            section="Administración"
            :icon="Tags"
            :action-icon="Plus"
            action-label="Nueva etiqueta"
            :action-href="admin.speciesTags.create().url"
        />

        <DataTable
            :pagination="tags"
            :is-empty="tags.data.length === 0"
            :empty-colspan="5"
            :search="search"
            search-placeholder="Buscar etiqueta..."
            :has-filters="hasFilters()"
            @update:search="search = $event"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-3 py-1 text-xs font-medium text-[#42604C]"
                >
                    <FilterX class="h-3.5 w-3.5" />
                    Búsqueda: {{ search }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th class="min-w-[200px] px-5 py-4 font-semibold sm:px-6">
                        Etiqueta
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Slug
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Especies
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
                    v-for="tag in tags.data"
                    :key="tag.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <Tags class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ tag.name }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ tag.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex max-w-full break-all rounded-lg border border-[#DCE7DF] bg-[#F5F9F6] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ tag.slug }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex min-w-9 items-center justify-center rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-2.5 py-1 text-xs font-semibold text-[#42604C]"
                        >
                            {{ tag.species_count.toLocaleString('es-MX') }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="tag.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
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
                        :edit-url="admin.speciesTags.edit(tag.id).url"
                        item-name="etiqueta"
                        @delete="deleteTag(tag)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Tags class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron etiquetas
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay etiquetas que coincidan con la búsqueda realizada.'
                                : 'Todavía no hay etiquetas registradas.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
