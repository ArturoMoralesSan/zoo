
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Coins,
    FilterX,
    Plus,
    ToggleLeft,
    ToggleRight,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface PointRule {
    id: number;
    type: string;
    name: string;
    points: number;
    description: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RulesPagination {
    data: PointRule[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    rules: RulesPagination;
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
        admin.pointRules.index().url,
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
        admin.pointRules.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteRule = async (rule: PointRule): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar regla?',
        text: `Se eliminará la regla "${rule.name}". Esta acción no se puede deshacer.`,
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

    router.delete(admin.pointRules.destroy(rule.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Eliminada',
                text: 'La regla de puntos se eliminó correctamente.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo eliminar la regla de puntos.',
                icon: 'error',
                confirmButtonText: 'Aceptar',
            });
        },
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
                title: 'Reglas de puntos',
                href: admin.pointRules.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Reglas de puntos" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Reglas de puntos"
            description="Administra las reglas que generan o descuentan puntos."
            section="Administración"
            :icon="Coins"
            :action-icon="Plus"
            action-label="Nueva regla"
            :action-href="admin.pointRules.create().url"
        />

        <DataTable
            :pagination="rules"
            :is-empty="rules.data.length === 0"
            :empty-colspan="5"
            :search="search"
            search-placeholder="Buscar regla..."
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
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Regla
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Tipo
                    </th>

                    <th class="whitespace-nowrap px-5 py-4 font-semibold sm:px-6">
                        Puntos
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
                    v-for="rule in rules.data"
                    :key="rule.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <Coins class="h-4 w-4" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ rule.name }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ rule.id }}
                                </p>

                                <p
                                    v-if="rule.description"
                                    class="mt-1 max-w-md text-xs leading-relaxed text-[#718477]"
                                >
                                    {{ rule.description }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <code
                            class="inline-flex rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ rule.type }}
                        </code>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="rule.points > 0"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700"
                        >
                            +{{ rule.points }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
                        >
                            {{ rule.points }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="rule.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <ToggleRight class="h-3.5 w-3.5" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#718477]"
                        >
                            <ToggleLeft class="h-3.5 w-3.5" />
                            Inactiva
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.pointRules.edit(rule.id).url"
                        item-name="regla de puntos"
                        @delete="deleteRule(rule)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Coins class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron reglas de puntos
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay reglas que coincidan con la búsqueda.'
                                : 'Crea una regla para definir cómo se generan o descuentan los puntos.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
