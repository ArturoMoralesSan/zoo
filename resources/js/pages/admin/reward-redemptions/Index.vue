<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Canjes de recompensas
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Consulta el historial de
                        recompensas canjeadas por los
                        usuarios.
                    </p>
                </div>

                <Link
                    :href="
                        admin.rewardRedemptions
                            .create().url
                    "
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo canje
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
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar canje..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div
                    class="text-sm text-muted-foreground"
                >
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
                <table
                    class="w-full text-left text-sm"
                >
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Folio
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Usuario
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Recompensa
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Puntos requeridos
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Fecha
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="
                                redemption in redemptions.data
                            "
                            :key="redemption.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Folio -->
                            <td class="px-6 py-4">
                                <div
                                    class="font-medium"
                                >
                                    {{ redemption.folio }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    ID:
                                    {{
                                        redemption.id
                                    }}
                                </div>
                            </td>

                            <!-- Usuario -->
                            <td class="px-6 py-4">
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        redemption.user
                                            .name
                                    }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        redemption.user
                                            .email
                                    }}
                                </div>
                            </td>

                            <!-- Recompensa -->
                            <td class="px-6 py-4">
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        redemption.reward
                                            .name
                                    }}
                                </div>
                            </td>

                            <!-- Puntos -->
                            <td class="px-6 py-4">
                                <span
                                    class="font-semibold"
                                >
                                    {{
                                        formatPoints(
                                            redemption.points,
                                        )
                                    }}
                                </span>

                                <span
                                    class="ml-1 text-xs text-muted-foreground"
                                >
                                    pts
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="
                                        redemption.status ===
                                        'completed'
                                    "
                                    class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    {{
                                        statusLabel(
                                            redemption.status,
                                        )
                                    }}
                                </span>

                                <span
                                    v-else-if="
                                        redemption.status ===
                                        'cancelled'
                                    "
                                    class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    {{
                                        statusLabel(
                                            redemption.status,
                                        )
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400"
                                >
                                    {{
                                        statusLabel(
                                            redemption.status,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Fecha -->
                            <td class="px-6 py-4">
                                <div>
                                    {{
                                        formatDate(
                                            redemption.redeemed_at,
                                        )
                                    }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    Creado:

                                    {{
                                        formatDate(
                                            redemption.created_at,
                                        )
                                    }}
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="
                                redemptions.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron
                                canjes de recompensas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="
                    redemptions.last_page > 1
                "
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link, index
                    ) in redemptions.links"
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