
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    Leaf,
    Plus,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface Category {
    id: number;
    name: string;
}

interface Species {
    id: number;
    species_category_id: number;
    common_name: string;
    scientific_name: string;
    slug: string;
    description: string | null;
    habitat: string | null;
    origin: string | null;
    diet: string | null;
    conservation_status: string | null;
    is_active: boolean;
    category: Category;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface SpeciesPagination {
    data: Species[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    species: SpeciesPagination;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: number | string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const categoryId = ref(
    props.filters?.category_id
        ? String(props.filters.category_id)
        : '',
);

const hasFilters = (): boolean => {
    return Boolean(search.value.trim() || categoryId.value);
};

const submitSearch = (): void => {
    router.get(
        admin.species.index().url,
        {
            search: search.value.trim() || undefined,
            category_id: categoryId.value || undefined,
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
    categoryId.value = '';

    router.get(
        admin.species.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteSpecies = async (item: Species): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar especie?',
        text: `Se eliminará "${item.common_name}". Esta acción no se puede deshacer.`,
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

    router.delete(admin.species.destroy(item.id).url, {
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
                title: 'Especies',
                href: admin.species.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Especies" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Especies"
            description="Administra el catálogo de especies del zoológico."
            section="Administración"
            :icon="Leaf"
            :action-icon="Plus"
            action-label="Nueva especie"
            :action-href="admin.species.create().url"
        />

        <DataTable
            :pagination="species"
            :is-empty="species.data.length === 0"
            :empty-colspan="6"
            :search="search"
            search-placeholder="Buscar especie..."
            :has-filters="hasFilters()"
            @update:search="search = $event"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <select
                    v-model="categoryId"
                    aria-label="Filtrar por categoría"
                    class="w-full rounded-lg border border-[#DCE7DF] bg-white px-3 py-2.5 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/15 sm:w-auto"
                    @change="submitSearch"
                >
                    <option value="">
                        Todas las categorías
                    </option>

                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </template>

            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-3 py-1 text-xs font-medium text-[#42604C]"
                >
                    <FilterX class="h-3.5 w-3.5" />
                    Búsqueda: {{ search }}
                </span>

                <span
                    v-if="categoryId"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-3 py-1 text-xs font-medium text-[#42604C]"
                >
                    Categoría:
                    {{
                        categories.find(
                            (category) =>
                                String(category.id) === categoryId,
                        )?.name ?? 'Seleccionada'
                    }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th class="min-w-[240px] px-5 py-4 font-semibold sm:px-6">
                        Especie
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Categoría
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Hábitat
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Conservación
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
                    v-for="item in species.data"
                    :key="item.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <Leaf class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ item.common_name }}
                                </p>

                                <p class="text-xs italic text-[#718477]">
                                    {{ item.scientific_name }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ item.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ item.category?.name ?? 'Sin categoría' }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="item.habitat"
                            class="text-sm text-[#42604C]"
                        >
                            {{ item.habitat }}
                        </span>

                        <span
                            v-else
                            class="text-sm italic text-[#91A096]"
                        >
                            Sin especificar
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="item.conservation_status"
                            class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ item.conservation_status }}
                        </span>

                        <span
                            v-else
                            class="text-sm italic text-[#91A096]"
                        >
                            Sin especificar
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="item.is_active"
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
                        :view-url="admin.species.show(item.id).url"
                        :edit-url="admin.species.edit(item.id).url"
                        item-name="especie"
                        @delete="deleteSpecies(item)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Leaf class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron especies
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay especies que coincidan con los filtros seleccionados.'
                                : 'Todavía no hay especies registradas en el catálogo.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
