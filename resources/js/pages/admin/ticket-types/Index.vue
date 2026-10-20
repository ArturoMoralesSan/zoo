
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    Plus,
    Ticket,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface TicketType {
    id: number;
    name: string;
    price: number | string;
    description: string | null;
    is_active: boolean;
    tickets_count?: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TicketTypePagination {
    data: TicketType[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    ticketTypes: TicketTypePagination;
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
        admin.ticketTypes.index().url,
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
        admin.ticketTypes.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteTicketType = async (
    item: TicketType,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar tipo de boleto?',
        text: `Se eliminará "${item.name}". Esta acción no se puede deshacer.`,
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

    router.delete(
        admin.ticketTypes.destroy(item.id).url,
        {
            preserveScroll: true,
        },
    );
};

const formatPrice = (price: number | string): string => {
    return Number(price).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
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
                title: 'Tipos de boletos',
                href: admin.ticketTypes.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Tipos de boleto" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Tipos de boleto"
            description="Administra las tarifas y tipos de acceso al zoológico."
            section="Taquilla"
            :icon="Ticket"
            :action-icon="Plus"
            action-label="Nuevo tipo de boleto"
            :action-href="admin.ticketTypes.create().url"
        />

        <DataTable
            :pagination="ticketTypes"
            :is-empty="ticketTypes.data.length === 0"
            :empty-colspan="5"
            :search="search"
            search-placeholder="Buscar tipo de boleto..."
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
                        Tipo
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Precio
                    </th>
                    <th class="min-w-[220px] px-5 py-4 font-semibold sm:px-6">
                        Descripción
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
                    v-for="item in ticketTypes.data"
                    :key="item.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <Ticket class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ item.name }}
                                </p>
                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ item.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span class="font-semibold text-[#244334]">
                            {{ formatPrice(item.price) }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <p
                            v-if="item.description"
                            class="max-w-sm text-sm leading-5 text-[#42604C]"
                        >
                            {{ item.description }}
                        </p>
                        <span
                            v-else
                            class="text-sm italic text-[#87978B]"
                        >
                            Sin descripción
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="item.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                            Activo
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500" />
                            Inactivo
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.ticketTypes.edit(item.id).url"
                        item-name="tipo de boleto"
                        @delete="deleteTicketType(item)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Ticket class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron tipos de boleto
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay tipos de boleto que coincidan con la búsqueda.'
                                : 'Todavía no hay tipos de boleto registrados.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
