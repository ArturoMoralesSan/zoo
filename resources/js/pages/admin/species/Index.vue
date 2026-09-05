<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

import admin from '@/routes/admin';

interface Category {
    id: number;
    name: string;
}

interface Species {
    id: number;
    species_category_id: number;
    common_name: string;
    scientific_name: string;
    slug: string;
    description: string | null;
    habitat: string | null;
    origin: string | null;
    diet: string | null;
    conservation_status: string | null;
    is_active: boolean;
    category: Category;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface SpeciesPagination {
    data: Species[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    species: SpeciesPagination;
    categories: Category[];
    filters: {
        search?: string;
        category_id?: number | string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const categoryId = ref(
    props.filters?.category_id
        ? String(props.filters.category_id)
        : '',
);

const submitSearch = () => {
    router.get(
        admin.species.index().url,
        {
            search: search.value || undefined,
            category_id: categoryId.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteSpecies = (item: Species) => {
    Swal.fire({
        title: '¿Eliminar especie?',
        text: `Se eliminará "${item.common_name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.species.destroy(item.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};
</script>

<template>
    <Head title="Especies" />

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
                        Especies
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra el catálogo de especies del zoológico.
                    </p>
                </div>

                <Link
                    :href="admin.species.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nueva especie
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full flex-col gap-2 md:max-w-2xl md:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar especie..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <select
                        v-model="categoryId"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">
                            Todas las categorías
                        </option>

                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ species.total }} especies
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
                                Especie
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Categoría
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Hábitat
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Conservación
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
                            v-for="item in species.data"
                            :key="item.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Especie -->
                            <td class="px-6 py-4">
                                <div class="font-medium">
                                    {{ item.common_name }}
                                </div>

                                <div
                                    class="text-xs italic text-muted-foreground"
                                >
                                    {{ item.scientific_name }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    ID: {{ item.id }}
                                </div>
                            </td>

                            <!-- Categoría -->
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ item.category.name }}
                                </span>
                            </td>

                            <!-- Hábitat -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="item.habitat"
                                    class="text-sm"
                                >
                                    {{ item.habitat }}
                                </span>

                                <span
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    Sin especificar
                                </span>
                            </td>

                            <!-- Conservación -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="item.conservation_status"
                                    class="text-sm"
                                >
                                    {{ item.conservation_status }}
                                </span>

                                <span
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    Sin especificar
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="item.is_active"
                                    class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <!-- Detalles -->
                                    <Link
                                        :href="
                                            admin.species.show(
                                                item.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Detalles
                                    </Link>

                                    <!-- Editar -->
                                    <Link
                                        :href="
                                            admin.species.edit(
                                                item.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Editar
                                    </Link>

                                    <!-- Eliminar -->
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteSpecies(item)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="species.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron especies.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="species.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in species.links"
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