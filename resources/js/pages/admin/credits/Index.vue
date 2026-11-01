<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    Copyright,
    ExternalLink,
    ImageOff,
    Pencil,
} from 'lucide-vue-next';
import { ref } from 'vue';

import DataTable from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import type { AssetSourceOption, CreditAsset } from '@/lib/credits/types';
import admin from '@/routes/admin';

interface CreditRow {
    key: string;
    card: { id: number; name: string; is_active: boolean };
    asset: CreditAsset;
    asset_label: string;
    preview_url: string | null;
    missing: boolean;
    source: string | null;
    source_label: string | null;
    source_url: string | null;
    license: string | null;
    license_short_name: string | null;
    license_url: string | null;
    is_proprietary: boolean;
    attribution: string | null;
    edit_url: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CreditsPagination {
    data: CreditRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    credits: CreditsPagination;
    filters: {
        search?: string | null;
        source?: string | null;
        license?: string | null;
        asset?: string | null;
        missing?: string | null;
    };
    licenses: { id: number; slug: string; short_name: string }[];
    assetSources: AssetSourceOption[];
    assets: { value: CreditAsset; label: string }[];
    missingCount: number;
}>();

const search = ref(props.filters.search ?? '');
const source = ref(props.filters.source ?? '');
const license = ref(props.filters.license ?? '');
const asset = ref(props.filters.asset ?? '');
const missing = ref(Boolean(props.filters.missing));

const hasFilters = (): boolean =>
    Boolean(
        search.value.trim() ||
        source.value ||
        license.value ||
        asset.value ||
        missing.value,
    );

const applyFilters = (): void => {
    router.get(
        admin.credits.index().url,
        {
            search: search.value.trim() || undefined,
            source: source.value || undefined,
            license: license.value || undefined,
            asset: asset.value || undefined,
            missing: missing.value ? 1 : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = (): void => {
    search.value = '';
    source.value = '';
    license.value = '';
    asset.value = '';
    missing.value = false;
    applyFilters();
};

const sourceLabel = (value: string): string =>
    props.assetSources.find((option) => option.value === value)?.label ?? value;

const licenseLabel = (slug: string): string =>
    props.licenses.find((option) => option.slug === slug)?.short_name ?? slug;

const assetLabel = (value: string): string =>
    props.assets.find((option) => option.value === value)?.label ?? value;

const selectClass =
    'h-[42px] w-full min-w-0 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 sm:w-auto sm:min-w-[150px]';

const headClass =
    'border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6';

const columns = [
    'Tarjeta',
    'Recurso',
    'Origen',
    'Licencia',
    'Atribución',
    '',
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Créditos y licencias',
                href: admin.credits.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Créditos y licencias" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Créditos y licencias"
            description="Licencia y atribución de cada imagen y modelo 3D de las tarjetas. Los créditos de las tarjetas activas se publican en la pantalla de créditos de la app."
            section="Colección"
            :icon="Copyright"
        >
            <template #details>
                <button
                    v-if="missingCount > 0 && !missing"
                    type="button"
                    class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                    @click="
                        missing = true;
                        applyFilters();
                    "
                >
                    <CircleAlert :size="14" />
                    {{ missingCount }}
                    {{
                        missingCount === 1
                            ? 'recurso sin licencia'
                            : 'recursos sin licencia'
                    }}
                </button>
            </template>
        </PageHeader>

        <DataTable
            v-model:search="search"
            :pagination="credits"
            :is-empty="credits.data.length === 0"
            :empty-colspan="columns.length"
            search-placeholder="Buscar por tarjeta..."
            :has-filters="hasFilters()"
            @search="applyFilters"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <div class="flex flex-wrap gap-3">
                    <select
                        v-model="source"
                        aria-label="Filtrar por origen"
                        :class="selectClass"
                        @change="applyFilters"
                    >
                        <option value="">Todos los orígenes</option>
                        <option
                            v-for="option in assetSources"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <select
                        v-model="license"
                        aria-label="Filtrar por licencia"
                        :class="selectClass"
                        @change="applyFilters"
                    >
                        <option value="">Todas las licencias</option>
                        <option
                            v-for="option in licenses"
                            :key="option.slug"
                            :value="option.slug"
                        >
                            {{ option.short_name }}
                        </option>
                    </select>

                    <select
                        v-model="asset"
                        aria-label="Filtrar por recurso"
                        :class="selectClass"
                        @change="applyFilters"
                    >
                        <option value="">Todos los recursos</option>
                        <option
                            v-for="option in assets"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <label
                        class="inline-flex h-[42px] cursor-pointer items-center gap-2 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334]"
                    >
                        <input
                            v-model="missing"
                            type="checkbox"
                            class="accent-[#187744]"
                            @change="applyFilters"
                        />
                        Falta licencia
                    </label>
                </div>
            </template>

            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>
                <span
                    v-if="source"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Origen: {{ sourceLabel(source) }}
                </span>
                <span
                    v-if="license"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Licencia: {{ licenseLabel(license) }}
                </span>
                <span
                    v-if="asset"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Recurso: {{ assetLabel(asset) }}
                </span>
                <span
                    v-if="missing"
                    class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800"
                >
                    Falta licencia
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column"
                        :class="headClass"
                    >
                        {{ column }}
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="row in credits.data"
                    :key="row.key"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Tarjeta -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-48 items-center gap-3">
                            <div
                                class="h-12 w-12 shrink-0 overflow-hidden rounded-md border border-[#e1e9e3] bg-[#f2f7f4]"
                                :class="{
                                    'opacity-50 grayscale': !row.card.is_active,
                                }"
                            >
                                <img
                                    v-if="row.preview_url"
                                    :src="row.preview_url"
                                    :alt="row.card.name"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#84958a]"
                                >
                                    <ImageOff :size="16" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <Link
                                    :href="admin.cards.show(row.card.id).url"
                                    class="text-sm font-semibold text-[#244334] hover:text-[#187744]"
                                >
                                    {{ row.card.name }}
                                </Link>
                                <p
                                    v-if="!row.card.is_active"
                                    class="text-xs text-[#84958a]"
                                >
                                    Inactiva
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Recurso -->
                    <td
                        class="px-5 py-4 text-sm whitespace-nowrap text-[#435d4c] sm:px-6"
                    >
                        {{ row.asset_label }}
                    </td>

                    <!-- Origen -->
                    <td class="px-5 py-4 text-sm whitespace-nowrap sm:px-6">
                        <a
                            v-if="row.source_url"
                            :href="row.source_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-[#187744] hover:underline"
                        >
                            {{ row.source_label }}
                            <ExternalLink :size="12" />
                        </a>
                        <span v-else class="text-[#435d4c]">
                            {{ row.source_label ?? '—' }}
                        </span>
                    </td>

                    <!-- Licencia -->
                    <td class="px-5 py-4 whitespace-nowrap sm:px-6">
                        <span
                            v-if="row.missing"
                            class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800"
                        >
                            <CircleAlert :size="13" />
                            Falta licencia
                        </span>
                        <a
                            v-else-if="row.license_url"
                            :href="row.license_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-lg border border-[#C8DCCF] bg-[#EAF5EE] px-2.5 py-1 text-xs font-semibold text-[#187744] hover:underline"
                        >
                            {{ row.license_short_name }}
                            <ExternalLink :size="12" />
                        </a>
                        <span
                            v-else
                            class="inline-flex rounded-lg border border-[#dce7df] bg-white px-2.5 py-1 text-xs font-semibold text-[#435d4c]"
                        >
                            {{ row.license_short_name }}
                        </span>
                    </td>

                    <!-- Atribución -->
                    <td class="px-5 py-4 text-sm text-[#435d4c] sm:px-6">
                        <p class="max-w-md min-w-64">
                            {{ row.attribution ?? '—' }}
                        </p>
                    </td>

                    <!-- Editar -->
                    <td class="px-5 py-4 text-right whitespace-nowrap sm:px-6">
                        <a
                            :href="row.edit_url"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#C8DCCF] bg-white px-3 py-2 text-xs font-medium text-[#187744] transition hover:bg-[#F2F7F4]"
                        >
                            <Pencil :size="13" />
                            {{ row.missing ? 'Agregar licencia' : 'Editar' }}
                        </a>
                    </td>
                </tr>
            </template>
        </DataTable>
    </div>
</template>
