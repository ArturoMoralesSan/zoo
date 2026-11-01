
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Box,
    CircleCheck,
    CircleOff,
    Copyright,
    FilterX,
    HelpCircle,
    ImageOff,
    Plus,
    QrCode,
    Settings2,
    Tag,
    TriangleAlert,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { onMounted, ref, watch } from 'vue';

import DuelMessagesSettings from '@/components/admin/cards/DuelMessagesSettings.vue';
import RaritySettingsTable from '@/components/admin/cards/RaritySettingsTable.vue';
import DataTable from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import TableActions from '@/components/admin/TableActions.vue';
import {
    getRarityClasses,
    getRarityLabel,
    rarityOptions,
} from '@/lib/cardRarity';
import type { CardRaritySetting } from '@/lib/cardRarity';
import { storageUrl } from '@/lib/modelPreview';
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
    thumbnail_image: string | null;
    generated_image: string | null;
    model_file: string | null;
    is_active: boolean;
    sort_order: number;
    species: Species;
    qr_codes_count?: number;
    active_quiz_questions_count?: number;
    effective_questions_per_duel?: number;
    // Recursos subidos sin licencia.
    missing_credits?: string[];
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
    raritySettings: CardRaritySetting[];
    duelMessages: Record<
        'win_message' | 'retry_message' | 'locked_message',
        string | null
    >;
    duelMessagesBuiltIn: Record<
        'win_message' | 'retry_message' | 'locked_message',
        string
    >;
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

const columns = ['Tarjeta', 'Rareza', 'Duelos', 'Acciones'];

// Imagen final de la tarjeta; si no existe, la miniatura.
const cardImageUrl = (card: Card) =>
    storageUrl(card.generated_image ?? card.thumbnail_image);

/*
 * Si la tarjeta se puede obtener en un duelo y, si no, por qué.
 * Una tarjeta inactiva se muestra en gris (no es un problema por resolver).
 */
const duelReadiness = (card: Card) => {
    if (!card.is_active) {
        return { status: 'inactive', label: 'Inactiva' } as const;
    }

    if (!card.active_quiz_questions_count) {
        return { status: 'warning', label: 'Sin preguntas activas' } as const;
    }

    if (!card.qr_codes_count) {
        return { status: 'warning', label: 'Sin códigos QR' } as const;
    }

    return { status: 'ready', label: 'Lista para duelos' } as const;
};

const readinessClasses = {
    ready: 'text-[#187744]',
    warning: 'text-amber-700',
    inactive: 'text-[#718477]',
};

const chipClass = (warning: boolean) =>
    warning
        ? 'border-amber-200 bg-amber-50 text-amber-700'
        : 'border-[#dce7df] bg-[#f2f7f4] text-[#435d4c]';

/*
 * Pestañas: lista de tarjetas y configuración de duelos.
 * Se recuerda la última pestaña usada.
 */
type Tab = 'cards' | 'settings';

const TAB_STORAGE_KEY = 'admin.cards.tab';

const tab = ref<Tab>('cards');

// Se lee al montar para que coincida con el HTML renderizado en el servidor.
onMounted(() => {
    try {
        if (localStorage.getItem(TAB_STORAGE_KEY) === 'settings') {
            tab.value = 'settings';
        }
    } catch {
        // Almacenamiento no disponible: se muestra la lista.
    }
});

watch(tab, (value) => {
    try {
        localStorage.setItem(TAB_STORAGE_KEY, value);
    } catch {
        // Almacenamiento no disponible: no se recuerda la pestaña.
    }
});

const tabs: {
    value: Tab;
    label: string;
    // Etiqueta corta para pantallas pequeñas.
    shortLabel: string;
    icon: typeof Tag;
}[] = [
    { value: 'cards', label: 'Tarjetas', shortLabel: 'Tarjetas', icon: Tag },
    {
        value: 'settings',
        label: 'Configuración de duelos',
        shortLabel: 'Configuración',
        icon: Settings2,
    },
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Tarjetas',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Tarjetas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 sm:p-6">
        <PageHeader
            title="Tarjetas"
            description="Tarjetas coleccionables, su contenido para duelos y la configuración general."
            section="Colección"
            :icon="Tag"
            :action-icon="Plus"
            action-label="Nueva tarjeta"
            :action-href="admin.cards.create().url"
        />

        <!-- Pestañas -->
        <div
            role="tablist"
            aria-label="Secciones de tarjetas"
            class="flex w-full gap-1 rounded-xl border border-[#d7e2da] bg-white p-1 sm:w-fit"
        >
            <button
                v-for="item in tabs"
                :key="item.value"
                type="button"
                role="tab"
                :aria-selected="tab === item.value"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors sm:flex-none"
                :class="
                    tab === item.value
                        ? 'bg-[#187744] text-white'
                        : 'text-[#435d4c] hover:bg-[#f2f7f4]'
                "
                @click="tab = item.value"
            >
                <component :is="item.icon" :size="16" />
                <span class="sm:hidden">{{ item.shortLabel }}</span>
                <span class="hidden sm:inline">{{ item.label }}</span>
                <span
                    v-if="item.value === 'cards'"
                    class="rounded-full px-2 py-0.5 text-xs"
                    :class="
                        tab === item.value
                            ? 'bg-white/20 text-white'
                            : 'bg-[#edf4ef] text-[#435d4c]'
                    "
                >
                    {{ cards.total }}
                </span>
            </button>
        </div>

        <!-- Configuración de duelos -->
        <div v-if="tab === 'settings'" class="space-y-4">
            <RaritySettingsTable :settings="raritySettings" always-open />

            <DuelMessagesSettings
                :messages="duelMessages"
                :built-in="duelMessagesBuiltIn"
                always-open
            />
        </div>

        <!-- Tarjetas -->
        <DataTable
            v-else
            v-model:search="search"
            :pagination="cards"
            :is-empty="cards.data.length === 0"
            :empty-colspan="columns.length"
            search-placeholder="Buscar tarjeta o especie..."
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
                    <option
                        v-for="option in rarityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
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
                    <th
                        v-for="column in columns"
                        :key="column"
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold tracking-wide text-[#435d4c] uppercase sm:px-6"
                        :class="{ 'text-right': column === 'Acciones' }"
                    >
                        {{ column }}
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
                        <Link
                            :href="admin.cards.show(card.id).url"
                            class="group flex min-w-60 items-center gap-3"
                        >
                            <div
                                class="h-16 w-9 shrink-0 overflow-hidden rounded-md border border-[#e1e9e3] bg-[#f2f7f4]"
                                :class="{ 'opacity-50 grayscale': !card.is_active }"
                            >
                                <img
                                    v-if="cardImageUrl(card)"
                                    :src="cardImageUrl(card) ?? ''"
                                    :alt="card.name"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                />

                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#84958a]"
                                    title="Sin imagen"
                                >
                                    <ImageOff :size="16" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div
                                    class="text-sm font-semibold text-[#244334] group-hover:text-[#187744]"
                                >
                                    {{ card.name }}
                                </div>

                                <div class="mt-0.5 text-xs text-[#435d4c]">
                                    {{ card.species?.common_name ?? 'Sin especie' }}
                                    <span
                                        v-if="card.species?.scientific_name"
                                        class="text-[#84958a] italic"
                                    >
                                        · {{ card.species.scientific_name }}
                                    </span>
                                </div>

                                <span
                                    v-if="card.missing_credits?.length"
                                    class="mt-1 inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                                    title="Hay recursos subidos sin licencia"
                                >
                                    <Copyright :size="12" />
                                    Falta licencia
                                </span>
                            </div>
                        </Link>
                    </td>

                    <!-- Rareza -->
                    <td class="px-5 py-4 whitespace-nowrap sm:px-6">
                        <span
                            class="inline-flex rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                            :class="getRarityClasses(card.rarity)"
                        >
                            {{ getRarityLabel(card.rarity) }}
                        </span>
                    </td>

                    <!-- Duelos -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-57.5 space-y-2">
                            <p
                                class="inline-flex items-center gap-1.5 text-xs font-semibold"
                                :class="readinessClasses[duelReadiness(card).status]"
                            >
                                <CircleCheck
                                    v-if="duelReadiness(card).status === 'ready'"
                                    :size="14"
                                />
                                <CircleOff
                                    v-else-if="duelReadiness(card).status === 'inactive'"
                                    :size="14"
                                />
                                <TriangleAlert v-else :size="14" />
                                {{ duelReadiness(card).label }}
                            </p>

                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium"
                                    :class="
                                        chipClass(
                                            (card.active_quiz_questions_count ?? 0) <
                                                (card.effective_questions_per_duel ?? 3),
                                        )
                                    "
                                    title="Preguntas activas / preguntas por duelo"
                                >
                                    <HelpCircle :size="12" />
                                    {{ card.active_quiz_questions_count ?? 0 }}/{{
                                        card.effective_questions_per_duel ?? 3
                                    }}
                                    preguntas
                                </span>

                                <span
                                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium"
                                    :class="chipClass((card.qr_codes_count ?? 0) === 0)"
                                >
                                    <QrCode :size="12" />
                                    {{ card.qr_codes_count ?? 0 }} QR
                                </span>

                                <span
                                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium"
                                    :class="chipClass(false)"
                                >
                                    <Box :size="12" />
                                    {{ card.model_file ? 'Modelo 3D' : 'Sin modelo' }}
                                </span>
                            </div>
                        </div>
                    </td>

                    <!-- Acciones -->
                    <TableActions
                        :questions-url="admin.cards.quiz.index(card.id).url"
                        :qr-codes-url="admin.cards.qrCodes.index(card.id).url"
                        :credits-url="admin.cards.credits.index(card.id).url"
                        :credits-warning="Boolean(card.missing_credits?.length)"
                        :view-url="admin.cards.show(card.id).url"
                        :edit-url="admin.cards.edit(card.id).url"
                        item-name="tarjeta"
                        show-questions
                        show-qr-codes
                        show-credits
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
