
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
    RotateCcw,
    Settings2,
    Tag,
    Timer,
    TriangleAlert,
    Trophy,
    Undo2,
    Wrench,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { onMounted, ref, watch } from 'vue';

import DuelMessagesSettings from '@/components/admin/cards/DuelMessagesSettings.vue';
import OptimizeImagesPanel from '@/components/admin/cards/OptimizeImagesPanel.vue';
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

// Tarjeta obtenida por un usuario.
interface Capture {
    id: number;
    captured_at: string;
    user: { id: number; name: string; email: string } | null;
    card: { id: number; name: string; rarity: string } | null;
}

// Intentos de un usuario con una tarjeta que aún no tiene.
interface QuizState {
    id: number;
    failed_attempts: number;
    max_attempts: number | null;
    locked_until: string | null;
    user: { id: number; name: string; email: string } | null;
    card: { id: number; name: string; rarity: string } | null;
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
    captures: Capture[];
    quizStates: QuizState[];
    // Pestañas de desarrollo activas (APP_ZOODECK_DEV=1).
    devTools: boolean;
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

/*
 * Revocar una tarjeta obtenida: se borra también el historial de duelos
 * del usuario con esa tarjeta, como si nunca la hubiera ganado.
 */
const revokeCapture = (capture: Capture) => {
    Swal.fire({
        title: '¿Revocar tarjeta?',
        text: `${capture.user?.name ?? 'El usuario'} perderá "${capture.card?.name ?? 'la tarjeta'}" y su historial de duelos con ella. Podrá volver a ganarla desde cero.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, revocar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.cards.captures.destroy(capture.id).url,
                { preserveScroll: true, preserveState: true },
            );
        }
    });
};

/*
 * Reiniciar los intentos: quita el bloqueo y borra los duelos no ganados
 * del usuario con esa tarjeta, como si nunca la hubiera intentado.
 */
const resetQuizState = (state: QuizState) => {
    Swal.fire({
        title: '¿Reiniciar intentos?',
        text: `${state.user?.name ?? 'El usuario'} podrá retar a "${state.card?.name ?? 'la tarjeta'}" desde cero, sin bloqueo.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reiniciar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.cards.quizStates.destroy(state.id).url,
                { preserveScroll: true, preserveState: true },
            );
        }
    });
};

const formatDate = (value: string) =>
    new Date(value).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

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
 * Pestañas: lista de tarjetas, configuración de duelos y tarjetas obtenidas.
 * Se recuerda la última pestaña usada.
 */
type Tab = 'cards' | 'settings' | 'captures' | 'attempts';

const TAB_STORAGE_KEY = 'admin.cards.tab';

const tab = ref<Tab>('cards');

// Se lee al montar para que coincida con el HTML renderizado en el servidor.
onMounted(() => {
    try {
        const saved = localStorage.getItem(TAB_STORAGE_KEY);

        if (
            saved === 'settings' ||
            (props.devTools &&
                (saved === 'captures' || saved === 'attempts'))
        ) {
            tab.value = saved;
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

// Pestañas de desarrollo: solo con APP_ZOODECK_DEV=1.
const devTabs: typeof tabs = [
    {
        value: 'captures',
        label: 'Tarjetas obtenidas',
        shortLabel: 'Obtenidas',
        icon: Trophy,
    },
    {
        value: 'attempts',
        label: 'Intentos y bloqueos',
        shortLabel: 'Intentos',
        icon: Timer,
    },
];

const tabCount = (value: Tab) =>
    ({
        cards: props.cards.total,
        captures: props.captures.length,
        attempts: props.quizStates.length,
        settings: null,
    })[value];

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
                    v-if="tabCount(item.value) !== null"
                    class="rounded-full px-2 py-0.5 text-xs"
                    :class="
                        tab === item.value
                            ? 'bg-white/20 text-white'
                            : 'bg-[#edf4ef] text-[#435d4c]'
                    "
                >
                    {{ tabCount(item.value) }}
                </span>
            </button>

            <!-- Pestañas de desarrollo, separadas y marcadas como tales -->
            <template v-if="devTools">
                <div
                    aria-hidden="true"
                    class="mx-1 w-px self-stretch bg-[#d7e2da]"
                />

                <div
                    class="flex flex-[2] items-center gap-1 rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-0.5 sm:flex-none"
                >
                    <span
                        class="hidden items-center gap-1 px-2 text-[10px] font-bold tracking-wider text-amber-700 uppercase sm:inline-flex"
                        title="Herramientas de desarrollo (APP_ZOODECK_DEV)"
                    >
                        <Wrench :size="12" />
                        Dev
                    </span>

                    <button
                        v-for="item in devTabs"
                        :key="item.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.value"
                        :title="`${item.label} (solo desarrollo)`"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-semibold transition-colors sm:flex-none"
                        :class="
                            tab === item.value
                                ? 'bg-amber-600 text-white'
                                : 'text-amber-800 hover:bg-amber-100'
                        "
                        @click="tab = item.value"
                    >
                        <component :is="item.icon" :size="16" />
                        <span class="sm:hidden">{{ item.shortLabel }}</span>
                        <span class="hidden sm:inline">{{ item.label }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs"
                            :class="
                                tab === item.value
                                    ? 'bg-white/20 text-white'
                                    : 'bg-amber-100 text-amber-800'
                            "
                        >
                            {{ tabCount(item.value) }}
                        </span>
                    </button>
                </div>
            </template>
        </div>

        <!-- Configuración de duelos -->
        <div v-if="tab === 'settings'" class="space-y-4">
            <RaritySettingsTable :settings="raritySettings" always-open />

            <DuelMessagesSettings
                :messages="duelMessages"
                :built-in="duelMessagesBuiltIn"
                always-open
            />

            <OptimizeImagesPanel />
        </div>

        <!-- Tarjetas obtenidas -->
        <div
            v-else-if="devTools && tab === 'captures'"
            class="overflow-x-auto rounded-xl border border-[#d7e2da] bg-white"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th
                            v-for="column in ['Usuario', 'Tarjeta', 'Obtenida', 'Acciones']"
                            :key="column"
                            class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold tracking-wide text-[#435d4c] uppercase sm:px-6"
                            :class="{ 'text-right': column === 'Acciones' }"
                        >
                            {{ column }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#edf2ee]">
                    <tr
                        v-for="capture in captures"
                        :key="capture.id"
                        class="transition-colors hover:bg-[#f7faf8]"
                    >
                        <td class="px-5 py-3 sm:px-6">
                            <div class="font-semibold text-[#244334]">
                                {{ capture.user?.name ?? 'Usuario eliminado' }}
                            </div>
                            <div class="text-xs text-[#718477]">
                                {{ capture.user?.email }}
                            </div>
                        </td>

                        <td class="px-5 py-3 whitespace-nowrap sm:px-6">
                            <span class="mr-2 font-semibold text-[#244334]">
                                {{ capture.card?.name ?? 'Tarjeta eliminada' }}
                            </span>
                            <span
                                v-if="capture.card"
                                class="inline-flex rounded-lg border px-2 py-0.5 text-[11px] font-semibold"
                                :class="getRarityClasses(capture.card.rarity)"
                            >
                                {{ getRarityLabel(capture.card.rarity) }}
                            </span>
                        </td>

                        <td class="px-5 py-3 whitespace-nowrap text-[#435d4c] sm:px-6">
                            {{ formatDate(capture.captured_at) }}
                        </td>

                        <td class="px-5 py-3 text-right sm:px-6">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100"
                                @click="revokeCapture(capture)"
                            >
                                <Undo2 :size="14" />
                                Revocar
                            </button>
                        </td>
                    </tr>

                    <tr v-if="captures.length === 0">
                        <td
                            colspan="4"
                            class="px-6 py-10 text-center text-sm text-[#718477]"
                        >
                            Ningún usuario ha obtenido tarjetas todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Intentos y bloqueos -->
        <div
            v-else-if="devTools && tab === 'attempts'"
            class="overflow-x-auto rounded-xl border border-[#d7e2da] bg-white"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th
                            v-for="column in ['Usuario', 'Tarjeta', 'Intentos usados', 'Estado', 'Acciones']"
                            :key="column"
                            class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold tracking-wide text-[#435d4c] uppercase sm:px-6"
                            :class="{ 'text-right': column === 'Acciones' }"
                        >
                            {{ column }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#edf2ee]">
                    <tr
                        v-for="state in quizStates"
                        :key="state.id"
                        class="transition-colors hover:bg-[#f7faf8]"
                    >
                        <td class="px-5 py-3 sm:px-6">
                            <div class="font-semibold text-[#244334]">
                                {{ state.user?.name ?? 'Usuario eliminado' }}
                            </div>
                            <div class="text-xs text-[#718477]">
                                {{ state.user?.email }}
                            </div>
                        </td>

                        <td class="px-5 py-3 whitespace-nowrap sm:px-6">
                            <span class="mr-2 font-semibold text-[#244334]">
                                {{ state.card?.name ?? 'Tarjeta eliminada' }}
                            </span>
                            <span
                                v-if="state.card"
                                class="inline-flex rounded-lg border px-2 py-0.5 text-[11px] font-semibold"
                                :class="getRarityClasses(state.card.rarity)"
                            >
                                {{ getRarityLabel(state.card.rarity) }}
                            </span>
                        </td>

                        <td class="px-5 py-3 whitespace-nowrap text-[#435d4c] sm:px-6">
                            {{ state.failed_attempts }}/{{ state.max_attempts ?? '—' }}
                        </td>

                        <td class="px-5 py-3 whitespace-nowrap sm:px-6">
                            <span
                                v-if="state.locked_until"
                                class="text-xs font-semibold text-amber-700"
                            >
                                Bloqueada hasta {{ formatDate(state.locked_until) }}
                            </span>
                            <span v-else class="text-xs text-[#718477]">
                                Sin bloqueo
                            </span>
                        </td>

                        <td class="px-5 py-3 text-right sm:px-6">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-[#bfcfc6] bg-white px-3 py-1.5 text-xs font-semibold text-[#244334] transition-colors hover:bg-[#f2f7f4]"
                                @click="resetQuizState(state)"
                            >
                                <RotateCcw :size="14" />
                                Reiniciar
                            </button>
                        </td>
                    </tr>

                    <tr v-if="quizStates.length === 0">
                        <td
                            colspan="5"
                            class="px-6 py-10 text-center text-sm text-[#718477]"
                        >
                            Ningún usuario tiene intentos registrados.
                        </td>
                    </tr>
                </tbody>
            </table>
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
