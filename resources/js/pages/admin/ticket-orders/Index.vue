
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ClipboardList,
    FilterX,
    Plus,
    Store,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface User {
    id: number;
    name: string;
    email?: string;
}

interface TicketType {
    id: number;
    name: string;
    price: string | number;
}

interface TicketOrderItem {
    id: number;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    ticket_type: TicketType;
}

interface PaymentMethod {
    id: number;
    name: string;
}

interface TicketOrderPayment {
    id: number;
    amount: string | number;
    reference: string | null;
    payment_method: PaymentMethod;
}

interface TicketOrder {
    id: number;
    folio: string;
    user: User | null;
    seller: User | null;
    source: 'app' | 'taquilla';
    subtotal: string | number;
    discount: string | number;
    total: string | number;
    status: string;
    paid_at: string | null;
    created_at: string;
    items: TicketOrderItem[];
    payments: TicketOrderPayment[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface OrdersPagination {
    data: TicketOrder[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    orders: OrdersPagination;
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
        admin.ticketOrders.index().url,
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
        admin.ticketOrders.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteOrder = async (order: TicketOrder): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar orden?',
        text: `Se eliminará la orden "${order.folio}". Esta acción no se puede deshacer.`,
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

    router.delete(admin.ticketOrders.destroy(order.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Eliminada',
                text: 'La orden se eliminó correctamente.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo eliminar la orden.',
                icon: 'error',
                confirmButtonText: 'Aceptar',
            });
        },
    });
};

const formatCurrency = (value: string | number): string => {
    return Number(value).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
};

const formatDate = (value: string): string => {
    return new Date(value).toLocaleDateString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        paid: 'Pagada',
        cancelled: 'Cancelada',
        refunded: 'Reembolsada',
    };

    return labels[status] ?? status;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Órdenes de boletos',
                href: admin.ticketOrders.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Órdenes de boletos" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Órdenes de boletos"
            description="Administra las ventas de boletos realizadas desde taquilla y la aplicación."
            section="Taquilla"
            :icon="ClipboardList"
            :action-icon="Plus"
            action-label="Nueva orden"
            :action-href="admin.ticketOrders.create().url"
        />

        <DataTable
            :pagination="orders"
            :is-empty="orders.data.length === 0"
            :empty-colspan="8"
            :search="search"
            search-placeholder="Buscar folio, comprador, vendedor u origen..."
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
                    <th class="min-w-[170px] px-5 py-4 font-semibold sm:px-6">
                        Folio
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Origen
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Comprador
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Vendedor
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Total
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Estado
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Fecha
                    </th>
                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="order in orders.data"
                    :key="order.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <ClipboardList class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-semibold text-[#244334]">
                                    {{ order.folio }}
                                </p>
                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ order.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="order.source === 'app'"
                            class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700"
                        >
                            Aplicación
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-purple-200 bg-purple-50 px-2.5 py-1 text-xs font-medium text-purple-700"
                        >
                            <Store class="h-3.5 w-3.5" />
                            Taquilla
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <template v-if="order.user">
                            <p class="font-medium text-[#244334]">
                                {{ order.user.name }}
                            </p>
                            <p
                                v-if="order.user.email"
                                class="mt-0.5 text-xs text-[#718477]"
                            >
                                {{ order.user.email }}
                            </p>
                        </template>

                        <span v-else class="text-sm text-[#718477]">
                            Venta sin usuario
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="order.seller"
                            class="font-medium text-[#244334]"
                        >
                            {{ order.seller.name }}
                        </span>
                        <span v-else class="text-[#718477]">
                            —
                        </span>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span class="font-semibold text-[#244334]">
                            {{ formatCurrency(order.total) }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="order.status === 'paid'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                            {{ statusLabel(order.status) }}
                        </span>

                        <span
                            v-else-if="order.status === 'pending'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                            {{ statusLabel(order.status) }}
                        </span>

                        <span
                            v-else-if="order.status === 'refunded'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-medium text-orange-700"
                        >
                            {{ statusLabel(order.status) }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                        >
                            {{ statusLabel(order.status) }}
                        </span>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 text-sm text-[#718477] sm:px-6">
                        {{ formatDate(order.created_at) }}
                    </td>

                    <TableActions
                        :view-url="admin.ticketOrders.show(order.id).url"
                        :show-edit="false"
                        :show-delete="order.status !== 'paid'"
                        item-name="orden"
                        @delete="deleteOrder(order)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <ClipboardList class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron órdenes
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay órdenes que coincidan con la búsqueda realizada.'
                                : 'Todavía no hay órdenes de boletos registradas.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
