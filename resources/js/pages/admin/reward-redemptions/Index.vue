
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Coins,
    FilterX,
    Gift,
    Hash,
    Plus,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';

interface RedemptionUser {
    id: number;
    name: string;
    email: string;
}

interface Reward {
    id: number;
    name: string;
    points: number;
}

interface RewardRedemption {
    id: number;
    user: RedemptionUser;
    reward: Reward;
    points: number;
    folio: string;
    status: string;
    redeemed_at: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RedemptionsPagination {
    data: RewardRedemption[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    redemptions: RedemptionsPagination;
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
        admin.rewardRedemptions.index().url,
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
        admin.rewardRedemptions.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const formatDate = (date: string | null): string => {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const formatPoints = (points: number): string => {
    return points.toLocaleString('es-MX');
};

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        completed: 'Completado',
        pending: 'Pendiente',
        cancelled: 'Cancelado',
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
                title: 'Recompensas',
                href: admin.rewardRedemptions.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Canjes de recompensas" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Canjes de recompensas"
            description="Consulta el historial de recompensas canjeadas por los usuarios."
            section="Administración"
            :icon="Gift"
            :action-icon="Plus"
            action-label="Nuevo canje"
            :action-href="admin.rewardRedemptions.create().url"
        />

        <DataTable
            :pagination="redemptions"
            :is-empty="redemptions.data.length === 0"
            :empty-colspan="6"
            :search="search"
            search-placeholder="Buscar canje..."
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
                        Folio
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Usuario
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Recompensa
                    </th>

                    <th class="whitespace-nowrap px-5 py-4 font-semibold sm:px-6">
                        Puntos requeridos
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Estado
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Fecha
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="redemption in redemptions.data"
                    :key="redemption.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <Hash class="h-4 w-4" />
                            </span>

                            <div>
                                <p class="font-medium text-[#244334]">
                                    {{ redemption.folio }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ redemption.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <User class="h-4 w-4" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ redemption.user.name }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    {{ redemption.user.email }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <Gift class="h-4 w-4" />
                            </span>

                            <span class="font-medium text-[#244334]">
                                {{ redemption.reward.name }}
                            </span>
                        </div>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-semibold text-[#42604C]"
                        >
                            <Coins class="h-3.5 w-3.5 text-[#718477]" />

                            {{ formatPoints(redemption.points) }}

                            <span class="font-normal text-[#718477]">
                                pts
                            </span>
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="redemption.status === 'completed'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                            {{ statusLabel(redemption.status) }}
                        </span>

                        <span
                            v-else-if="redemption.status === 'cancelled'"
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500" />
                            {{ statusLabel(redemption.status) }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#52685A]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#91A096]" />
                            {{ statusLabel(redemption.status) }}
                        </span>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-2">
                            <CalendarClock class="mt-0.5 h-4 w-4 shrink-0 text-[#718477]" />

                            <div>
                                <p class="text-sm text-[#244334]">
                                    {{ formatDate(redemption.redeemed_at) }}
                                </p>

                                <p class="mt-1 text-xs text-[#718477]">
                                    Creado: {{ formatDate(redemption.created_at) }}
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Gift class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron canjes
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay canjes de recompensas que coincidan con la búsqueda.'
                                : 'Los canjes de recompensas de los usuarios aparecerán aquí.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
