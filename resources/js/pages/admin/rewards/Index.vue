<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

import admin from '@/routes/admin';

interface Reward {
    id: number;
    name: string;
    description: string | null;
    points: number;
    stock: number;
    image: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RewardsPagination {
    data: Reward[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    rewards: RewardsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = (): void => {
    router.get(
        admin.rewards.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteReward = async (
    reward: Reward,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar recompensa?',
        text: `Se eliminará la recompensa "${reward.name}". Esta acción no se puede deshacer.`,
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
        admin.rewards.destroy(reward.id).url,
        {
            preserveScroll: true,

            onSuccess: () => {
                Swal.fire({
                    title: 'Eliminada',
                    text: 'La recompensa se eliminó correctamente.',
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },

            onError: () => {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo eliminar la recompensa.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar',
                });
            },
        },
    );
};
</script>

<template>
    <Head title="Recompensas" />

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
                        Recompensas
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra las recompensas que los usuarios pueden
                        canjear con sus puntos.
                    </p>
                </div>

                <Link
                    :href="admin.rewards.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nueva recompensa
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
                    class="flex w-full gap-2 md:max-w-md"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar recompensa..."
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
                    {{ rewards.total }}
                    {{
                        rewards.total === 1
                            ? 'recompensa'
                            : 'recompensas'
                    }}
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
                                Recompensa
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Puntos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Stock
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
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
                            v-for="reward in rewards.data"
                            :key="reward.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Recompensa -->
                            <td class="px-6 py-4">
                                <div class="font-medium">
                                    {{ reward.name }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    ID: {{ reward.id }}
                                </div>

                                <div
                                    v-if="reward.description"
                                    class="mt-1 max-w-md text-xs text-muted-foreground"
                                >
                                    {{ reward.description }}
                                </div>
                            </td>

                            <!-- Puntos -->
                            <td class="px-6 py-4">
                                <span
                                    class="font-semibold text-primary"
                                >
                                    {{ reward.points }} pts
                                </span>
                            </td>

                            <!-- Stock -->
                            <td class="px-6 py-4">
                                <span
                                    :class="
                                        reward.stock > 0
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-red-600 dark:text-red-400'
                                    "
                                    class="font-semibold"
                                >
                                    {{ reward.stock }}
                                </span>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        reward.stock > 0
                                            ? 'Disponible'
                                            : 'Agotada'
                                    }}
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="reward.is_active"
                                    class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400"
                                >
                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            admin.rewards.edit(
                                                reward.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteReward(reward)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="rewards.data.length === 0">
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron recompensas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="rewards.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in rewards.links"
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