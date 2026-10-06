<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Gift,
    Hash,
    Package,
    Pencil,
    Plus,
    Search,
    Star,
    Trash2,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

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

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Gift class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Recompensas
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra las recompensas que los usuarios pueden
                            canjear con sus puntos.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.rewards.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nueva recompensa
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar recompensa..."
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pl-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary"
                                    >
                                        <Gift class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ reward.name }}
                                        </div>

                                        <div
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <Hash class="h-3 w-3" />
                                            ID: {{ reward.id }}
                                        </div>

                                        <div
                                            v-if="reward.description"
                                            class="mt-1 max-w-md text-xs text-muted-foreground"
                                        >
                                            {{ reward.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Puntos -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"
                                >
                                    <Star class="h-3.5 w-3.5" />
                                    {{ reward.points }} pts
                                </span>
                            </td>

                            <!-- Stock -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted/40 text-muted-foreground"
                                    >
                                        <Package class="h-4 w-4" />
                                    </span>

                                    <div>
                                        <div
                                            :class="
                                                reward.stock > 0
                                                    ? 'font-semibold text-green-600 dark:text-green-400'
                                                    : 'font-semibold text-red-600 dark:text-red-400'
                                            "
                                        >
                                            {{ reward.stock }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                reward.stock > 0
                                                    ? 'Disponible'
                                                    : 'Agotada'
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="reward.is_active"
                                    class="inline-flex items-center rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium text-muted-foreground"
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
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteReward(reward)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="rewards.data.length === 0">
                            <td
                                colspan="5"
                                class="px-6 py-12"
                            >
                                <div
                                    class="flex flex-col items-center justify-center text-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <Gift class="h-6 w-6" />
                                    </div>

                                    <h3
                                        class="mt-4 text-sm font-semibold text-foreground"
                                    >
                                        No se encontraron recompensas
                                    </h3>

                                    <p
                                        class="mt-1 max-w-md text-sm text-muted-foreground"
                                    >
                                        No hay recompensas que coincidan con la
                                        búsqueda realizada.
                                    </p>
                                </div>
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