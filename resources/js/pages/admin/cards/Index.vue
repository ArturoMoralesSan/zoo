<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    edition: string | null;
    description: string | null;
    card_image: string | null;
    model_name: string | null;
    model_file: string | null;
    model_url: string | null;
    model_format: string | null;
    is_active: boolean;
    sort_order: number;
    species: Species;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CardsPagination {
    data: Card[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    cards: CardsPagination;
    filters: {
        search?: string;
        species_id?: string;
        rarity?: string;
    };
    species: Species[];
}>();

const search = ref(props.filters?.search ?? '');
const speciesId = ref(props.filters?.species_id ?? '');
const rarity = ref(props.filters?.rarity ?? '');

const submitSearch = () => {
    router.get(
        admin.cards.index().url,
        {
            search: search.value || undefined,
            species_id: speciesId.value || undefined,
            rarity: rarity.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const applyFilters = () => {
    router.get(
        admin.cards.index().url,
        {
            search: search.value || undefined,
            species_id: speciesId.value || undefined,
            rarity: rarity.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteCard = (card: Card) => {
    Swal.fire({
        title: '¿Eliminar tarjeta?',
        text: `Se eliminará la tarjeta "${card.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.cards.destroy(card.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const getRarityLabel = (value: string) => {
    const labels: Record<string, string> = {
        comun: 'Común',
        rara: 'Rara',
        epica: 'Épica',
        edicion_especial: 'Edición especial',
    };

    return labels[value] ?? value;
};

const getRarityClasses = (value: string) => {
    const classes: Record<string, string> = {
        comun: 'border-sidebar-border text-muted-foreground',
        rara: 'border-blue-500/30 text-blue-500',
        epica: 'border-purple-500/30 text-purple-500',
        edicion_especial:
            'border-yellow-500/30 text-yellow-600',
    };

    return (
        classes[value] ??
        'border-sidebar-border text-muted-foreground'
    );
};

const getModelLabel = (card: Card) => {
    if (card.model_file) {
        return card.model_format
            ? card.model_format.toUpperCase()
            : 'Archivo';
    }

    if (card.model_url) {
        return 'URL';
    }

    return 'Sin modelo';
};

const getModelClasses = (card: Card) => {
    if (card.model_file || card.model_url) {
        return 'border-green-500/30 text-green-500';
    }

    return 'border-sidebar-border text-muted-foreground';
};

const cardImageUrl = (path: string | null) => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};
</script>

<template>
    <Head title="Tarjetas" />

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
                        Tarjetas
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Administra las tarjetas coleccionables de
                        las especies.
                    </p>
                </div>

                <Link
                    :href="admin.cards.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nueva tarjeta
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador y filtros -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    @submit.prevent="submitSearch"
                    class="flex w-full flex-col gap-2 md:max-w-3xl md:flex-row"
                >
                    <!-- Buscar -->
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar tarjeta..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <!-- Especie -->
                    <select
                        v-model="speciesId"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todas las especies
                        </option>

                        <option
                            v-for="item in species"
                            :key="item.id"
                            :value="String(item.id)"
                        >
                            {{ item.common_name }}
                        </option>
                    </select>

                    <!-- Rareza -->
                    <select
                        v-model="rarity"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todas las rarezas
                        </option>

                        <option value="comun">
                            Común
                        </option>

                        <option value="rara">
                            Rara
                        </option>

                        <option value="epica">
                            Épica
                        </option>

                        <option value="edicion_especial">
                            Edición especial
                        </option>
                    </select>

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
                    {{ cards.total }} tarjetas
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
                                Tarjeta
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Especie
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Rareza
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Edición
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Modelo 3D
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 text-right font-semibold"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="card in cards.data"
                            :key="card.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Tarjeta -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                                    >
                                        <img
                                            v-if="card.card_image"
                                            :src="
                                                cardImageUrl(
                                                    card.card_image,
                                                ) ?? ''
                                            "
                                            :alt="card.name"
                                            class="h-full w-full object-cover"
                                        />

                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
                                        >
                                            Sin imagen
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="font-medium"
                                        >
                                            {{ card.name }}
                                        </div>

                                        <div
                                            v-if="card.description"
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{ card.description }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID:
                                            {{ card.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Especie -->
                            <td class="px-6 py-4">
                                <div>
                                    <div
                                        class="font-medium"
                                    >
                                        {{
                                            card.species?.common_name
                                        }}
                                    </div>

                                    <div
                                        class="text-xs italic text-muted-foreground"
                                    >
                                        {{
                                            card.species
                                                ?.scientific_name
                                        }}
                                    </div>
                                </div>
                            </td>

                            <!-- Rareza -->
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        getRarityClasses(
                                            card.rarity,
                                        )
                                    "
                                >
                                    {{
                                        getRarityLabel(
                                            card.rarity,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Edición -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="card.edition"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ card.edition }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Modelo 3D -->
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        getModelClasses(card)
                                    "
                                >
                                    {{
                                        getModelLabel(card)
                                    }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="card.is_active"
                                    class="rounded-full border border-green-500/30 px-2.5 py-1 text-xs font-medium text-green-500"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.cards.show(
                                                card.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Ver
                                    </Link>

                                    <Link
                                        :href="
                                            admin.cards.edit(
                                                card.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="
                                            deleteCard(
                                                card,
                                            )
                                        "
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="
                                cards.data.length === 0
                            "
                        >
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron tarjetas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="cards.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link, index
                    ) in cards.links"
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