<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import {
    Edit,
    Leaf,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

interface Category {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    species_count: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CategoriesPagination {
    data: Category[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    categories: CategoriesPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.speciesCategories.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteCategory = (category: Category) => {
    if (category.species_count > 0) {
        Swal.fire({
            title: 'No se puede eliminar',
            text: `La categoría "${category.name}" tiene ${category.species_count} especie(s) asignada(s).`,
            icon: 'warning',
            confirmButtonText: 'Entendido',
        });

        return;
    }

    Swal.fire({
        title: '¿Eliminar categoría?',
        text: `Se eliminará la categoría "${category.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.speciesCategories.destroy(category.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};
</script>

<template>
    <Head title="Categorías de especies" />

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
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Leaf class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Categorías de especies
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra las categorías del catálogo de especies.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.speciesCategories.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nueva categoría
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
                            placeholder="Buscar categoría..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-9 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ categories.total }} categorías
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
                                Categoría
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Descripción
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Especies
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
                            v-for="category in categories.data"
                            :key="category.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Leaf class="h-4 w-4" />
                                    </div>

                                    <div>
                                        <div class="font-medium">
                                            {{ category.name }}
                                        </div>

                                        <div class="text-xs text-muted-foreground">
                                            ID: {{ category.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div
                                    v-if="category.description"
                                    class="max-w-md text-sm text-muted-foreground"
                                >
                                    {{ category.description }}
                                </div>

                                <div
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    Sin descripción
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex min-w-8 items-center justify-center rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ category.species_count }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <span
                                    v-if="category.is_active"
                                    class="inline-flex rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="inline-flex rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    Inactiva
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            admin.speciesCategories.edit(
                                                category.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-3.5 w-3.5" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteCategory(category)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="categories.data.length === 0">
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted/50 text-muted-foreground"
                                    >
                                        <Leaf class="h-5 w-5" />
                                    </div>

                                    <p
                                        class="mt-3 text-sm font-medium text-foreground"
                                    >
                                        No se encontraron categorías
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        No hay categorías que coincidan con la búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="categories.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in categories.links"
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
