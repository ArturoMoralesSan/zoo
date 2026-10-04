<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    Coins,
    Gift,
    Hash,
    Plus,
    Search,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';

import admin from '@/routes/admin';

interface User {
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
    user: User;
    reward: Reward;
    points: number;
    folio: string;
    status: string;
    redeemed_at: string | null;
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

const submitSearch = (): void => {
    router.get(
        admin.rewardRedemptions.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const formatDate = (
    date: string | null,
): string => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString(
        'es-MX',
        {
            dateStyle: 'short',
            timeStyle: 'short',
        },
    );
};

const formatPoints = (
    points: number,
): string => {
    return points.toLocaleString('es-MX');
};

const statusLabel = (
    status: string,
): string => {
    const labels: Record<string, string> = {
        completed: 'Completado',
        pending: 'Pendiente',
        cancelled: 'Cancelado',
    };

    return labels[status] ?? status;
};
</script>

<template>
    <Head title="Canjes de recompensas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Encabezado -->
        <div class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Gift class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Canjes de recompensas
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Consulta el historial de recompensas canjeadas por los usuarios.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.rewardRedemptions.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo canje
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border">
            <!-- Buscador -->
            <div class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between">
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar canje..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ redemptions.total }}
                    {{
                        redemptions.total === 1
                            ? 'canje'
                            : 'canjes'
                    }}
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border">
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Folio
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Usuario
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Recompensa
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Puntos requeridos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Fecha
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <tr
                            v-for="redemption in redemptions.data"
                            :key="redemption.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Folio -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary">
                                        <Hash class="h-4 w-4" />
                                    </span>

                                    <div>
                                        <div class="font-medium">
                                            {{ redemption.folio }}
                                        </div>

                                        <div class="text-xs text-muted-foreground">
                                            ID: {{ redemption.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Usuario -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary">
                                        <User class="h-4 w-4" />
                                    </span>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ redemption.user.name }}
                                        </div>

                                        <div class="text-xs text-muted-foreground">
                                            {{ redemption.user.email }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Recompensa -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary">
                                        <Gift class="h-4 w-4" />
                                    </span>

                                    <div class="font-medium">
                                        {{ redemption.reward.name }}
                                    </div>
                                </div>
                            </td>

                            <!-- Puntos -->
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-semibold">
                                    <Coins class="h-3.5 w-3.5 text-muted-foreground" />

                                    {{ formatPoints(redemption.points) }}

                                    <span class="font-normal text-muted-foreground">
                                        pts
                                    </span>
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="redemption.status === 'completed'"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>

                                    {{ statusLabel(redemption.status) }}
                                </span>

                                <span
                                    v-else-if="redemption.status === 'cancelled'"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>

                                    {{ statusLabel(redemption.status) }}
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-muted-foreground"></span>

                                    {{ statusLabel(redemption.status) }}
                                </span>
                            </td>

                            <!-- Fecha -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-2">
                                    <CalendarClock class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />

                                    <div>
                                        <div>
                                            {{ formatDate(redemption.redeemed_at) }}
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            Creado:
                                            {{ formatDate(redemption.created_at) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="redemptions.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-12"
                            >
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                        <Gift class="h-6 w-6" />
                                    </div>

                                    <h3 class="mt-4 text-sm font-semibold text-foreground">
                                        No se encontraron canjes
                                    </h3>

                                    <p class="mt-1 max-w-md text-sm text-muted-foreground">
                                        No hay canjes de recompensas que coincidan con la búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="redemptions.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in redemptions.links"
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