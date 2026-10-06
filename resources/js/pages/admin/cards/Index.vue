<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    Eye,
    Pencil,
    Trash2,
    ImageOff,
    FilterX,
    CheckCircle2,
    CircleOff,
} from 'lucide-vue-next';
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

const clearFilters = () => {
    search.value = '';
    speciesId.value = '';
    rarity.value = '';

    applyFilters();
};

const hasFilters = () => {
    return Boolean(
        search.value ||
        speciesId.value ||
        rarity.value,
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
        comun:
            'border-sidebar-border bg-muted/40 text-muted-foreground',
        rara:
            'border-blue-500/30 bg-blue-500/5 text-blue-500',
        epica:
            'border-purple-500/30 bg-purple-500/5 text-purple-500',
        edicion_especial:
            'border-yellow-500/30 bg-yellow-500/5 text-yellow-600',
    };

    return (
        classes[value] ??
        'border-sidebar-border bg-muted/40 text-muted-foreground'
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
        return 'border-green-500/30 bg-green-500/5 text-green-500';
    }

    return 'border-sidebar-border bg-muted/40 text-muted-foreground';
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
            class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <div class="mb-2 flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <ImageOff :size="20" />
                        </div>

                        <span
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Colección
                        </span>
                    </div>

                    <h1 class="text-2xl font-semibold tracking-tight">
                        Tarjetas
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra las tarjetas coleccionables de las especies.
                    </p>
                </div>

                <Link
                    :href="admin.cards.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                    <Plus :size="18" />
                    Nueva tarjeta
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador y filtros -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <form
                        @submit.prevent="submitSearch"
                        class="flex w-full flex-col gap-2 lg:max-w-4xl lg:flex-row"
                    >
                        <!-- Buscar -->
                        <div class="relative min-w-0 flex-1">
                            <Search
                                :size="18"
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                            />

                            <input
                                v-model="search"
                                type="search"
                                placeholder="Buscar tarjeta..."
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <!-- Especie -->
                        <select
                            v-model="speciesId"
                            class="rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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
                            class="rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                        <!-- Buscar -->
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <Search :size="17" />
                            Buscar
                        </button>
                    </form>

                    <!-- Total -->
                    <div
                        class="flex shrink-0 items-center gap-2 text-sm text-muted-foreground"
                    >
                        <span
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-muted px-2 font-semibold text-foreground"
                        >
                            {{ cards.total }}
                        </span>

                        <span>
                            {{
                                cards.total === 1
                                    ? 'tarjeta'
                                    : 'tarjetas'
                            }}
                        </span>
                    </div>
                </div>

                <!-- Filtros activos -->
                <div
                    v-if="hasFilters()"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="text-xs text-muted-foreground">
                        Filtros activos:
                    </span>

                    <span
                        v-if="search"
                        class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        Búsqueda: "{{ search }}"
                    </span>

                    <span
                        v-if="speciesId"
                        class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        Especie:
                        {{
                            species.find(
                                (item) =>
                                    String(item.id) === speciesId,
                            )?.common_name
                        }}
                    </span>

                    <span
                        v-if="rarity"
                        class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        Rareza: {{ getRarityLabel(rarity) }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-primary transition hover:bg-primary/10"
                        @click="clearFilters"
                    >
                        <FilterX :size="14" />
                        Limpiar
                    </button>
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
                                <div class="flex items-center gap-3">
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
                                            class="flex h-full w-full items-center justify-center text-muted-foreground"
                                            title="Sin imagen"
                                        >
                                            <ImageOff :size="20" />
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-medium">
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
                                            ID: {{ card.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Especie -->
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium">
                                        {{ card.species?.common_name }}
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
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
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
                                    class="inline-flex rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
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
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        getModelClasses(card)
                                    "
                                >
                                    {{ getModelLabel(card) }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="card.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-500"
                                >
                                    <CheckCircle2 :size="14" />
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    <CircleOff :size="14" />
                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-1.5"
                                >
                                    <Link
                                        :href="
                                            admin.cards.show(
                                                card.id,
                                            ).url
                                        "
                                        title="Ver tarjeta"
                                        aria-label="Ver tarjeta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        <Eye :size="16" />
                                        <span>Ver</span>
                                    </Link>

                                    <Link
                                        :href="
                                            admin.cards.edit(
                                                card.id,
                                            ).url
                                        "
                                        title="Editar tarjeta"
                                        aria-label="Editar tarjeta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        <Pencil :size="16" />
                                        <span>Editar</span>
                                    </Link>

                                    <button
                                        type="button"
                                        title="Eliminar tarjeta"
                                        aria-label="Eliminar tarjeta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10 focus:outline-none focus:ring-2 focus:ring-red-500/20"
                                        @click="deleteCard(card)"
                                    >
                                        <Trash2 :size="16" />
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Estado vacío -->
                        <tr v-if="cards.data.length === 0">
                            <td
                                colspan="7"
                                class="px-6 py-16"
                            >
                                <div
                                    class="mx-auto flex max-w-md flex-col items-center justify-center text-center"
                                >
                                    <!-- Icono -->
                                    <div
                                        class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                                    >
                                        <FilterX
                                            v-if="hasFilters()"
                                            :size="30"
                                        />

                                        <ImageOff
                                            v-else
                                            :size="30"
                                        />
                                    </div>

                                    <!-- Título -->
                                    <h3 class="text-base font-semibold">
                                        {{
                                            hasFilters()
                                                ? 'No se encontraron resultados'
                                                : 'Aún no hay tarjetas'
                                        }}
                                    </h3>

                                    <!-- Descripción -->
                                    <p
                                        class="mt-1 max-w-sm text-sm leading-6 text-muted-foreground"
                                    >
                                        {{
                                            hasFilters()
                                                ? 'No encontramos tarjetas que coincidan con los filtros seleccionados. Intenta cambiar los criterios de búsqueda.'
                                                : 'Todavía no has registrado ninguna tarjeta coleccionable. Comienza creando la primera.'
                                        }}
                                    </p>

                                    <!-- Acciones -->
                                    <div
                                        class="mt-5 flex flex-wrap items-center justify-center gap-2"
                                    >
                                        <button
                                            v-if="hasFilters()"
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                                            @click="clearFilters"
                                        >
                                            <FilterX :size="17" />
                                            Limpiar filtros
                                        </button>

                                        <Link
                                            v-else
                                            :href="
                                                admin.cards.create()
                                                    .url
                                            "
                                            class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30"
                                        >
                                            <Plus :size="18" />
                                            Crear primera tarjeta
                                        </Link>
                                    </div>
                                </div>
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
                    v-for="(link, index) in cards.links"
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
