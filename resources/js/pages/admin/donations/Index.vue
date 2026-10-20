
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    CircleDollarSign,
    FilterX,
    CheckCircle2,
    CircleOff,
    FileText,
    UserRound,
    Store,
    CalendarDays,
    CreditCard,
} from 'lucide-vue-next';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface User {
    id: number;
    name: string;
    email: string;
    qr_token?: string | null;
}

interface Seller {
    id: number;
    name: string;
    email?: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface Donation {
    id: number;
    user: User | null;
    seller: Seller | null;
    payment_method: PaymentMethod | null;
    amount: number;
    reference: string | null;
    status: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface DonationsPagination {
    data: Donation[];
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
    donations?: DonationsPagination;
    donation?: Donation;
    filters?: Filters;
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.donations.index().url,
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
        admin.donations.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const hasFilters = () => Boolean(search.value.trim());

const formatDate = (date: string | null): string => {
    if (!date) return '—';

    return new Date(date).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(amount) || 0);
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        completed: 'Completada',
        pending: 'Pendiente',
        cancelled: 'Cancelada',
    };

    return labels[status] ?? status;
};

const statusClass = (status: string): string => {
    const classes: Record<string, string> = {
        completed: 'border-[#c9e4d2] bg-[#dcefe2] text-[#176b3d]',
        pending: 'border-amber-200 bg-amber-50 text-amber-700',
        cancelled: 'border-red-200 bg-red-50 text-red-600',
    };

    return classes[status] ?? 'border-[#dce7df] bg-[#f2f7f4] text-[#718477]';
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Donaciones',
                href: admin.donations.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Donaciones" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Donaciones"
            description="Consulta y administra las donaciones registradas."
            section="Finanzas"
            :icon="CircleDollarSign"
            :action-icon="Plus"
            action-label="Nueva donación"
            :action-href="admin.donations.create().url"
        />

        <!-- Detalle de la donación -->
        <div
            v-if="donation"
            class="overflow-hidden rounded-xl border border-[#dce7df] bg-white"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-b border-[#dce7df] p-4 sm:px-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-lg bg-[#e4f2e8] text-[#187744]"
                    >
                        <CircleDollarSign :size="23" />
                    </div>

                    <div>
                        <h2 class="text-base font-bold text-[#244334]">
                            Donación #{{ donation.id }}
                        </h2>

                        <p class="mt-1 text-xs text-[#718477]">
                            Detalle de la donación registrada
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold"
                        :class="statusClass(donation.status)"
                    >
                        <CheckCircle2
                            v-if="donation.status === 'completed'"
                            :size="14"
                        />

                        <CircleOff
                            v-else-if="donation.status === 'cancelled'"
                            :size="14"
                        />

                        <FileText v-else :size="14" />

                        {{ statusLabel(donation.status) }}
                    </span>

                    <Link
                        :href="admin.donations.index().url"
                        class="inline-flex h-9 items-center gap-2 rounded-lg border border-[#b9cfc0] bg-white px-3 text-xs font-semibold text-[#244334] transition hover:bg-[#edf4ef]"
                    >
                        <FilterX :size="15" />
                        Volver
                    </Link>
                </div>
            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3">
                <!-- Importe -->
                <div class="rounded-lg border border-[#dce7df] bg-[#f8faf8] p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <CircleDollarSign :size="16" />
                        Importe de la donación
                    </div>

                    <p class="mt-2 text-2xl font-bold text-[#187744]">
                        {{ formatCurrency(donation.amount) }}
                    </p>
                </div>

                <!-- Donante -->
                <div class="rounded-lg border border-[#dce7df] bg-white p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <UserRound :size="16" />
                        Donante
                    </div>

                    <p class="mt-2 text-sm font-semibold text-[#244334]">
                        {{ donation.user?.name ?? 'Sin usuario asociado' }}
                    </p>

                    <p class="mt-1 break-all text-xs text-[#718477]">
                        {{ donation.user?.email ?? '—' }}
                    </p>
                </div>

                <!-- Método de pago -->
                <div class="rounded-lg border border-[#dce7df] bg-white p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <CreditCard :size="16" />
                        Método de pago
                    </div>

                    <p class="mt-2 text-sm font-semibold text-[#244334]">
                        {{ donation.payment_method?.name ?? 'No especificado' }}
                    </p>

                    <p class="mt-1 text-xs text-[#718477]">
                        {{ donation.payment_method?.code ?? '—' }}
                    </p>
                </div>

                <!-- Responsable -->
                <div class="rounded-lg border border-[#dce7df] bg-white p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <Store :size="16" />
                        Responsable
                    </div>

                    <p class="mt-2 text-sm font-semibold text-[#244334]">
                        {{ donation.seller?.name ?? 'Sin responsable asignado' }}
                    </p>

                    <p class="mt-1 break-all text-xs text-[#718477]">
                        {{ donation.seller?.email ?? '—' }}
                    </p>
                </div>

                <!-- Referencia -->
                <div class="rounded-lg border border-[#dce7df] bg-white p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <FileText :size="16" />
                        Referencia
                    </div>

                    <p class="mt-2 break-all text-sm font-semibold text-[#244334]">
                        {{ donation.reference || 'Sin referencia' }}
                    </p>
                </div>

                <!-- Fecha -->
                <div class="rounded-lg border border-[#dce7df] bg-white p-4">
                    <div class="flex items-center gap-2 text-xs font-medium text-[#718477]">
                        <CalendarDays :size="16" />
                        Fecha de registro
                    </div>

                    <p class="mt-2 text-sm font-semibold text-[#244334]">
                        {{ formatDate(donation.created_at) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Listado de donaciones -->
        <DataTable
            v-if="donations"
            v-model:search="search"
            :pagination="donations"
            :is-empty="donations.data.length === 0"
            :empty-colspan="7"
            search-placeholder="Buscar por donante, referencia o estado..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <!-- Filtros activos -->
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>
            </template>

            <!-- Encabezados -->
            <template #table-head>
                <tr>
                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Donante
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Importe
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Método de pago
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Responsable
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Estado
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Fecha
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <!-- Filas -->
            <template #table-body>
                <tr
                    v-for="item in donations.data"
                    :key="item.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Donante -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[170px] items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#e4f2e8] text-[#187744]">
                                <UserRound :size="19" />
                            </div>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ item.user?.name ?? 'Donante no disponible' }}
                                </p>

                                <p class="mt-1 max-w-[190px] truncate text-xs text-[#718477]">
                                    {{ item.user?.email ?? '—' }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID {{ item.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Importe -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span class="text-sm font-bold text-[#187744]">
                            {{ formatCurrency(item.amount) }}
                        </span>
                    </td>

                    <!-- Método de pago -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-[130px]">
                            <p class="text-sm font-medium text-[#244334]">
                                {{ item.payment_method?.name ?? 'No especificado' }}
                            </p>

                            <p class="mt-1 text-xs text-[#718477]">
                                {{ item.payment_method?.code ?? '—' }}
                            </p>
                        </div>
                    </td>

                    <!-- Responsable -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-[130px]">
                            <p class="text-sm font-medium text-[#244334]">
                                {{ item.seller?.name ?? 'Sin responsable' }}
                            </p>

                            <p class="mt-1 text-xs text-[#718477]">
                                {{ item.seller?.email ?? '—' }}
                            </p>
                        </div>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold"
                            :class="statusClass(item.status)"
                        >
                            <CheckCircle2
                                v-if="item.status === 'completed'"
                                :size="14"
                            />

                            <CircleOff
                                v-else-if="item.status === 'cancelled'"
                                :size="14"
                            />

                            <FileText v-else :size="14" />

                            {{ statusLabel(item.status) }}
                        </span>
                    </td>

                    <!-- Fecha -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span class="text-sm text-[#435d4c]">
                            {{ formatDate(item.created_at) }}
                        </span>
                    </td>

                    <!-- Acciones reutilizables -->
                    <TableActions
                        :view-url="admin.donations.show(item.id).url"
                        :show-edit="false"
                        :show-delete="false"
                        item-name="donación"
                    />
                </tr>
            </template>

            <!-- Estado vacío -->
            <template #empty>
                <div class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]">
                        <FilterX v-if="hasFilters()" :size="27" />
                        <CircleDollarSign v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay donaciones'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos donaciones que coincidan con tu búsqueda.'
                                : 'Todavía no hay donaciones registradas para mostrar.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
