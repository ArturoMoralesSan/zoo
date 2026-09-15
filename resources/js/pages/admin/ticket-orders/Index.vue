<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';

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

const search = ref(
    props.filters?.search ?? '',
);

/*
|--------------------------------------------------------------------------
| Búsqueda
|--------------------------------------------------------------------------
*/

const submitSearch = (): void => {
    router.get(
        admin.ticketOrders.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

/*
|--------------------------------------------------------------------------
| Eliminar orden
|--------------------------------------------------------------------------
*/

const deleteOrder = async (
    order: TicketOrder,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar orden?',
        text: `Se eliminará la orden "${order.folio}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.ticketOrders.destroy(order.id).url,
        {
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
        },
    );
};

/*
|--------------------------------------------------------------------------
| Formato de moneda
|--------------------------------------------------------------------------
*/

const formatCurrency = (
    value: string | number,
): string => {
    return Number(value).toLocaleString(
        'es-MX',
        {
            style: 'currency',
            currency: 'MXN',
        },
    );
};

/*
|--------------------------------------------------------------------------
| Formato de fecha
|--------------------------------------------------------------------------
*/

const formatDate = (
    value: string,
): string => {
    return new Date(value).toLocaleDateString(
        'es-MX',
        {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        },
    );
};

/*
|--------------------------------------------------------------------------
| Estado
|--------------------------------------------------------------------------
*/

const statusLabel = (
    status: string,
): string => {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        paid: 'Pagada',
        cancelled: 'Cancelada',
        refunded: 'Reembolsada',
    };

    return labels[status] ?? status;
};

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Órdenes de boletos
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra las ventas de boletos realizadas desde
                        taquilla y la aplicación.
                    </p>
                </div>

                <Link
                    :href="admin.ticketOrders.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nueva orden
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    @submit.prevent="submitSearch"
                    class="flex w-full gap-2 md:max-w-xl"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar folio, comprador, vendedor, origen..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ orders.total }} órdenes
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
                                Folio
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Origen
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Comprador
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Vendedor
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Total
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Fecha
                            </th>

                            <th class="px-6 py-4 text-right font-semibold">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Folio -->
                            <td class="px-6 py-4">
                                <div class="font-medium">
                                    {{ order.folio }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    ID: {{ order.id }}
                                </div>
                            </td>

                            <!-- Origen -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="order.source === 'app'"
                                    class="rounded-full border border-blue-500/30 bg-blue-500/10 px-2.5 py-1 text-xs font-medium text-blue-600 dark:text-blue-400"
                                >
                                    App
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-purple-500/30 bg-purple-500/10 px-2.5 py-1 text-xs font-medium text-purple-600 dark:text-purple-400"
                                >
                                    Taquilla
                                </span>
                            </td>

                            <!-- Comprador -->
                            <td class="px-6 py-4">
                                <div v-if="order.user">
                                    <div class="font-medium">
                                        {{ order.user.name }}
                                    </div>

                                    <div
                                        v-if="order.user.email"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ order.user.email }}
                                    </div>
                                </div>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Venta sin usuario
                                </span>
                            </td>

                            <!-- Vendedor -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="order.seller"
                                    class="font-medium"
                                >
                                    {{ order.seller.name }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Total -->
                            <td class="px-6 py-4 font-medium">
                                {{ formatCurrency(order.total) }}
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="order.status === 'paid'"
                                    class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>

                                <span
                                    v-else-if="order.status === 'pending'"
                                    class="rounded-full border border-yellow-500/30 bg-yellow-500/10 px-2.5 py-1 text-xs font-medium text-yellow-600 dark:text-yellow-400"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>

                                <span
                                    v-else-if="order.status === 'refunded'"
                                    class="rounded-full border border-orange-500/30 bg-orange-500/10 px-2.5 py-1 text-xs font-medium text-orange-600 dark:text-orange-400"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    {{ statusLabel(order.status) }}
                                </span>
                            </td>

                            <!-- Fecha -->
                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formatDate(order.created_at) }}
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.ticketOrders.show(
                                                order.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Ver
                                    </Link>

                                    <button
                                        v-if="order.status !== 'paid'"
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteOrder(order)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="orders.data.length === 0">
                            <td
                                colspan="8"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron órdenes de boletos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="orders.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in orders.links"
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