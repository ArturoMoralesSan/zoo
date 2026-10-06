<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Award,
    Edit,
    Plus,
    Search,
    Trash2,
    Users,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import admin from '@/routes/admin';

interface Level {
    id: number;
    name: string;
    min_points: number;
    max_points: number | null;
    description: string | null;
    users_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface LevelsPagination {
    data: Level[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    levels: LevelsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.levels.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteLevel = (level: Level) => {
    Swal.fire({
        title: '¿Eliminar nivel?',
        text: `Se eliminará el nivel "${level.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.levels.destroy(level.id).url, {
                preserveScroll: true,
            });
        }
    });
};
</script>

<template>
    <Head title="Niveles" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
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
                        <Award class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Niveles
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra los niveles y puntos de los visitantes.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.levels.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo nivel
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    @submit.prevent="submitSearch"
                    class="flex w-full gap-2 md:max-w-md"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar nivel..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2 pl-9 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Award class="h-4 w-4" />
                    {{ levels.total }} niveles
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
                                Nivel
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Rango de puntos
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Usuarios
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
                            v-for="level in levels.data"
                            :key="level.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Nivel -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Award class="h-4 w-4" />
                                    </div>

                                    <div>
                                        <div class="font-medium">
                                            {{ level.name }}
                                        </div>

                                        <div
                                            v-if="level.description"
                                            class="mt-0.5 max-w-md text-xs text-muted-foreground"
                                        >
                                            {{ level.description }}
                                        </div>

                                        <div
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            ID: {{ level.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Rango de puntos -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ level.min_points }}
                                    -
                                    {{ level.max_points ?? '∞' }}
                                </span>
                            </td>

                            <!-- Usuarios -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    <Users class="h-3.5 w-3.5" />
                                    {{ level.users_count }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="admin.levels.edit(level.id).url"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteLevel(level)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="levels.data.length === 0">
                            <td
                                colspan="4"
                                class="px-6 py-12 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-3"
                                >
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <Award class="h-5 w-5" />
                                    </div>

                                    <div>
                                        <p class="text-sm font-medium">
                                            No se encontraron niveles.
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Intenta con otro término de búsqueda.
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="levels.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in levels.links"
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