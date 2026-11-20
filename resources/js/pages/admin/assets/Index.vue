<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeftRight,
    Box,
    CircleCheck,
    CircleAlert,
    ExternalLink,
    Gauge,
    HardDrive,
    Server,
} from 'lucide-vue-next';
import { ref } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import AssetTransferPanel from '@/components/admin/assets/AssetTransferPanel.vue';
import OptimizeImagesPanel from '@/components/admin/cards/OptimizeImagesPanel.vue';
import SizeSavings from '@/components/admin/cards/SizeSavings.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import OptimizeSpeciesImagesPanel from '@/components/admin/species/OptimizeSpeciesImagesPanel.vue';
import { formatBytes } from '@/lib/glb/modelSize';
import admin from '@/routes/admin';

// Recursos: peso y optimización de los archivos que descarga la app
// (App\Services\AssetInventory) y paquetes .zip con tarjetas y especies.

interface AssetGroup {
    key: 'card_images' | 'species_images' | 'card_models';
    label: string;
    files: number;
    bytes: number;
    original_bytes: number;
    // null: el tipo no tiene conversión pendiente (modelos 3D).
    pending: number | null;
}

interface ModelRow {
    card_id: number;
    card_name: string;
    bytes: number | null;
    original_bytes: number | null;
    height: number | null;
    width: number | null;
    depth: number | null;
}

interface HeavyFile {
    kind: string;
    owner: string;
    edit_url: string;
    path: string;
    bytes: number;
}

const props = defineProps<{
    // null sin el permiso assets.view.
    summary: {
        server_encoder: string | null;
        zip_available: boolean;
        groups: AssetGroup[];
        models: ModelRow[];
        heaviest: HeavyFile[];
    } | null;
    canView: boolean;
    canOptimize: boolean;
    // assets.export o assets.import.
    canTransfer: boolean;
    initialTab: Tab;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Recursos',
                href: admin.assets.index(),
            },
        ],
    },
});

type Tab = 'optimization' | 'downloads';

const tabs: { value: Tab; label: string; icon: typeof Gauge }[] = [
    ...(props.canView
        ? [{ value: 'optimization' as Tab, label: 'Optimización', icon: Gauge }]
        : []),
    ...(props.canTransfer
        ? [
              {
                  value: 'downloads' as Tab,
                  label: 'Descargas',
                  icon: ArrowLeftRight,
              },
          ]
        : []),
];

const tab = ref<Tab>(
    tabs.some((item) => item.value === props.initialTab)
        ? props.initialTab
        : (tabs[0]?.value ?? 'optimization'),
);

const metres = (value: number | null) =>
    value === null ? '—' : `${value.toFixed(2)} m`;
</script>

<template>
    <Head title="Recursos" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Recursos"
            description="Peso y optimización de las imágenes y modelos 3D, y paquetes con todas las tarjetas y especies."
            section="Administración"
            :icon="HardDrive"
        />

        <div
            role="tablist"
            aria-label="Secciones de recursos"
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
                {{ item.label }}
            </button>
        </div>

        <div v-if="tab === 'optimization' && summary" class="space-y-6">
            <!-- Estado del servidor -->
            <div
                class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-[#d7e2da] bg-white px-4 py-3 text-sm text-[#435d4c]"
            >
                <span class="inline-flex items-center gap-1.5">
                    <Server :size="16" />
                    {{
                        summary.server_encoder
                            ? `El servidor convierte imágenes (${summary.server_encoder})`
                            : 'El servidor no convierte imágenes: lo hace el navegador'
                    }}
                </span>

                <span class="inline-flex items-center gap-1.5">
                    <component
                        :is="summary.zip_available ? CircleCheck : CircleAlert"
                        :size="16"
                        :class="
                            summary.zip_available
                                ? 'text-[#187744]'
                                : 'text-[#b45309]'
                        "
                    />
                    {{
                        summary.zip_available
                            ? 'Paquetes .zip disponibles'
                            : 'Sin extensión PHP zip: no se pueden crear paquetes'
                    }}
                </span>
            </div>

            <!-- Resumen por tipo -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div
                    v-for="group in summary.groups"
                    :key="group.key"
                    class="rounded-xl border border-[#d7e2da] bg-white p-4"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                    >
                        <component
                            :is="group.key === 'card_models' ? Box : Archive"
                            :size="16"
                            class="text-[#187744]"
                        />
                        {{ group.label }}
                    </p>

                    <p class="mt-2 text-2xl font-bold text-[#244334]">
                        {{ formatBytes(group.bytes) }}
                    </p>

                    <p class="text-xs text-[#718477]">
                        {{ group.files }}
                        {{ group.files === 1 ? 'archivo' : 'archivos' }}
                    </p>

                    <SizeSavings
                        class="mt-2"
                        label="Antes de optimizar"
                        :original="group.original_bytes"
                        :optimized="group.bytes"
                    />

                    <p
                        v-if="group.pending !== null"
                        class="mt-2 text-xs font-semibold"
                        :class="
                            group.pending ? 'text-[#b45309]' : 'text-[#187744]'
                        "
                    >
                        {{
                            group.pending
                                ? `${group.pending} pendientes de convertir a WebP`
                                : 'Todo en WebP'
                        }}
                    </p>
                </div>
            </div>

            <!-- Conversión de imágenes guardadas -->
            <template v-if="canOptimize">
                <OptimizeImagesPanel />
                <OptimizeSpeciesImagesPanel />
            </template>

            <!-- Modelos 3D -->
            <AdminCard
                title="Modelos 3D de tarjetas"
                description="Se escalan y comprimen en el navegador al subirlos; el original se conserva aparte."
                :icon="Box"
            >
                <p v-if="!summary.models.length" class="text-sm text-[#718477]">
                    Ninguna tarjeta tiene modelo 3D.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-[#E5ECE7] text-left text-xs text-[#718477]"
                            >
                                <th class="py-2 pr-4 font-medium">Tarjeta</th>
                                <th class="py-2 pr-4 font-medium">Original</th>
                                <th class="py-2 pr-4 font-medium">En la app</th>
                                <th class="py-2 font-medium">
                                    Alto × ancho × fondo
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="model in summary.models"
                                :key="model.card_id"
                                class="border-b border-[#E5ECE7] last:border-0"
                            >
                                <td
                                    class="py-2 pr-4 font-semibold text-[#244334]"
                                >
                                    <Link
                                        :href="
                                            admin.cards.edit(model.card_id).url
                                        "
                                        class="hover:underline"
                                    >
                                        {{ model.card_name }}
                                    </Link>
                                </td>
                                <td class="py-2 pr-4 text-[#718477]">
                                    {{ formatBytes(model.original_bytes) }}
                                </td>
                                <td class="py-2 pr-4 text-[#244334]">
                                    {{ formatBytes(model.bytes) }}
                                </td>
                                <td class="py-2 text-[#718477]">
                                    {{ metres(model.height) }} ×
                                    {{ metres(model.width) }} ×
                                    {{ metres(model.depth) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminCard>

            <!-- Archivos más pesados -->
            <AdminCard
                title="Archivos más pesados"
                description="Los que más tarda en descargar la app."
                :icon="HardDrive"
            >
                <p
                    v-if="!summary.heaviest.length"
                    class="text-sm text-[#718477]"
                >
                    No hay archivos.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-[#E5ECE7] text-left text-xs text-[#718477]"
                            >
                                <th class="py-2 pr-4 font-medium">Archivo</th>
                                <th class="py-2 pr-4 font-medium">De</th>
                                <th class="py-2 text-right font-medium">
                                    Peso
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="file in summary.heaviest"
                                :key="file.path"
                                class="border-b border-[#E5ECE7] last:border-0"
                            >
                                <td class="py-2 pr-4 text-[#244334]">
                                    {{ file.kind }}
                                    <span
                                        class="block text-xs break-all text-[#718477]"
                                    >
                                        {{ file.path }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4">
                                    <Link
                                        :href="file.edit_url"
                                        class="inline-flex items-center gap-1 font-semibold text-[#187744] hover:underline"
                                    >
                                        {{ file.owner }}
                                        <ExternalLink :size="13" />
                                    </Link>
                                </td>
                                <td
                                    class="py-2 text-right font-semibold whitespace-nowrap text-[#244334]"
                                >
                                    {{ formatBytes(file.bytes) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AdminCard>
        </div>

        <AssetTransferPanel v-else-if="tab === 'downloads'" />
    </div>
</template>
