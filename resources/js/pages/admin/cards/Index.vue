
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Plus,
    ImageOff,
    FilterX,
    CircleOff,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

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

const hasFilters = () =>
    Boolean(search.value.trim() || speciesId.value || rarity.value);

const submitSearch = () => {
    router.get(
        admin.cards.index().url,
        {
            search: search.value.trim() || undefined,
            species_id: speciesId.value || undefined,
            rarity: rarity.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = () => {
    submitSearch();
};

const clearFilters = () => {
    search.value = '';
    speciesId.value = '';
    rarity.value = '';

    router.get(
        admin.cards.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
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
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.cards.destroy(card.id).url,
                { preserveScroll: true },
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
        comun: 'border-[#dce7df] bg-[#f2f7f4] text-[#52685a]',
        rara: 'border-blue-200 bg-blue-50 text-blue-700',
        epica: 'border-purple-200 bg-purple-50 text-purple-700',
        edicion_especial: 'border-amber-200 bg-amber-50 text-amber-700',
    };

    return classes[value] ?? 'border-[#dce7df] bg-[#f2f7f4] text-[#52685a]';
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
        return 'border-[#c9e4d2] bg-[#e4f2e8] text-[#187744]';
    }

    return 'border-[#dce7df] bg-[#f2f7f4] text-[#84958a]';
};

const cardImageUrl = (path: string | null) => {
    if (!path) return null;

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Tarjetas',
                href: admin.cards.index,
            }
        ],
    },
});
</script>

<template>
    <Head title="Tarjetas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 sm:p-6">
        <PageHeader
            title="Tarjetas"
            description="Administra las tarjetas coleccionables de las especies."
            section="Colección"
            :icon="ImageOff"
            :action-icon="Plus"
            action-label="Nueva tarjeta"
            :action-href="admin.cards.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="cards"
            :is-empty="cards.data.length === 0"
            :empty-colspan="7"
            search-placeholder="Buscar tarjeta..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <!-- Filtros -->
            <template #filters>
                <select
                    v-model="speciesId"
                    aria-label="Filtrar por especie"
                    class="h-[42px] w-full min-w-0 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 sm:w-auto sm:min-w-[180px]"
                    @change="applyFilters"
                >
                    <option value="">Todas las especies</option>

                    <option
                        v-for="item in species"
                        :key="item.id"
                        :value="String(item.id)"
                    >
                        {{ item.common_name }}
                    </option>
                </select>

                <select
                    v-model="rarity"
                    aria-label="Filtrar por rareza"
                    class="h-[42px] w-full min-w-0 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 sm:w-auto sm:min-w-[170px]"
                    @change="applyFilters"
                >
                    <option value="">Todas las rarezas</option>
                    <option value="comun">Común</option>
                    <option value="rara">Rara</option>
                    <option value="epica">Épica</option>
                    <option value="edicion_especial">Edición especial</option>
                </select>
            </template>

            <!-- Filtros activos -->
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>

                <span
                    v-if="speciesId"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Especie:
                    {{
                        species.find(
                            (item) => String(item.id) === speciesId,
                        )?.common_name ?? 'Seleccionada'
                    }}
                </span>

                <span
                    v-if="rarity"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Rareza: {{ getRarityLabel(rarity) }}
                </span>
            </template>

            <!-- Encabezados -->
            <template #table-head>
                <tr>
                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Tarjeta
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Especie
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Rareza
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Edición
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Modelo 3D
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Estado
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <!-- Filas -->
            <template #table-body>
                <tr
                    v-for="card in cards.data"
                    :key="card.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Tarjeta -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[190px] items-center gap-3">
                            <div class="h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-[#e1e9e3] bg-[#f2f7f4]">
                                <img
                                    v-if="card.card_image"
                                    :src="cardImageUrl(card.card_image) ?? ''"
                                    :alt="card.name"
                                    class="h-full w-full object-cover"
                                />

                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#84958a]"
                                    title="Sin imagen"
                                >
                                    <ImageOff :size="20" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-[#244334]">
                                    {{ card.name }}
                                </div>

                                <div
                                    v-if="card.description"
                                    class="mt-1 max-w-[220px] truncate text-xs text-[#718477]"
                                >
                                    {{ card.description }}
                                </div>

                                <div class="mt-1 text-xs text-[#84958a]">
                                    ID {{ card.id }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Especie -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-[130px]">
                            <div class="text-sm font-semibold text-[#244334]">
                                {{ card.species?.common_name ?? 'Sin especie' }}
                            </div>

                            <div class="mt-1 text-xs italic text-[#718477]">
                                {{ card.species?.scientific_name ?? '—' }}
                            </div>
                        </div>
                    </td>

                    <!-- Rareza -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                            :class="getRarityClasses(card.rarity)"
                        >
                            {{ getRarityLabel(card.rarity) }}
                        </span>
                    </td>

                    <!-- Edición -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="card.edition"
                            class="inline-flex whitespace-nowrap rounded-lg border border-[#dce7df] bg-white px-2.5 py-1.5 text-xs font-medium text-[#435d4c]"
                        >
                            {{ card.edition }}
                        </span>

                        <span v-else class="text-sm text-[#a0ada4]">
                            —
                        </span>
                    </td>

                    <!-- Modelo 3D -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                            :class="getModelClasses(card)"
                        >
                            {{ getModelLabel(card) }}
                        </span>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="card.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#c9e4d2] bg-[#dcefe2] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            <span class="h-2 w-2 rounded-full bg-[#187744]" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#e1e7e2] bg-[#f2f5f3] px-3 py-1.5 text-xs font-medium text-[#718477]"
                        >
                            <CircleOff :size="14" />
                            Inactiva
                        </span>
                    </td>

                    <!-- Acciones reutilizables -->
                    <TableActions
                        :questions-url="admin.cards.quiz.index(card.id).url"
                        :view-url="admin.cards.show(card.id).url"
                        :edit-url="admin.cards.edit(card.id).url"
                        item-name="tarjeta"
                        show-questions
                        @delete="deleteCard(card)"
                    />
                </tr>
            </template>

            <!-- Estado vacío -->
            <template #empty>
                <div class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]">
                        <FilterX v-if="hasFilters()" :size="27" />
                        <ImageOff v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay tarjetas'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos tarjetas que coincidan con los filtros seleccionados.'
                                : 'Todavía no has registrado tarjetas coleccionables. Puedes crear la primera.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
