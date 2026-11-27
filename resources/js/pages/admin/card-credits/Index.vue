<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Box,
    ChevronDown,
    CircleAlert,
    CircleCheck,
    Copyright,
    CornerDownRight,
    Eye,
    FileImage,
    Image,
    Info,
    ListChecks,
    Plus,
    ShieldCheck,
    Smartphone,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import CreditChainRow from '@/components/admin/cards/CreditChainRow.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PageHeaderLink from '@/components/admin/PageHeaderLink.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CardSummary } from '@/lib/cardQrCode';
import { assetChain, creditsProgress } from '@/lib/credits/assetChain';
import type { AssetStatus } from '@/lib/credits/assetChain';
import { CREDIT_ROLES, creditRole } from '@/lib/credits/types';
import type { CreditAsset, CreditRole } from '@/lib/credits/types';
import admin from '@/routes/admin';

interface CreditRow {
    id: number;
    role: CreditRole;
    role_label: string;
    source_label: string | null;
    source_url: string | null;
    license_short_name: string;
    license_url: string | null;
    attribution: string;
    based_on_asset: CreditAsset | null;
    is_ai: boolean;
    ai_checked_at: string | null;
}

interface AssetSummary {
    value: CreditAsset;
    label: string;
    has_file: boolean;
    preview_url: string | null;
    status: AssetStatus;
    has_origin: boolean;
    credits: CreditRow[];
    alerts: { type: 'info' | 'warning'; message: string }[];
}

interface AppPreviewRow {
    id: number;
    asset_label: string;
    role_label: string;
    attribution: string;
}

const props = defineProps<{
    card: CardSummary & { is_active: boolean };
    assets: AssetSummary[];
    appPreview: AppPreviewRow[];
}>();

const ASSET_ICONS = {
    artwork: FileImage,
    thumbnail: Image,
    model: Box,
    image: Image,
};

const ASSET_DESCRIPTIONS: Record<CreditAsset, string> = {
    artwork: 'Fondo de la plantilla de la tarjeta.',
    thumbnail: 'Imagen de los listados de la colección.',
    model: 'Modelo 3D que se muestra en realidad aumentada.',
    // Solo especies; la tarjeta no tiene este recurso.
    image: 'Imagen de una especie.',
};

const EXTRA_ROLES = CREDIT_ROLES.filter((item) => item.value !== 'origin');

const progress = computed(() => creditsProgress(props.assets));

const chains = computed(() =>
    Object.fromEntries(
        props.assets.map((asset) => [
            asset.value,
            assetChain(asset, props.assets),
        ]),
    ),
);

const labelOf = (value: CreditAsset) =>
    props.assets.find((item) => item.value === value)?.label ?? value;

const missingText = computed(() => {
    const names = progress.value.missing.map((value) =>
        labelOf(value).toLowerCase(),
    );

    return names.length > 1
        ? `${names.slice(0, -1).join(', ')} y ${names.at(-1)}`
        : (names[0] ?? '');
});

const createUrl = (asset: CreditAsset, role: CreditRole) =>
    admin.cards.credits.create(props.card.id, {
        query: { asset, role },
    }).url;

const editUrl = (credit: CreditRow) =>
    admin.cards.credits.edit({ card: props.card.id, credit: credit.id }).url;

const deleteCredit = async (credit: CreditRow): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar crédito?',
        text:
            credit.role === 'origin'
                ? 'Es quien hizo el archivo: sin este crédito, el archivo quedará como "Falta licencia".'
                : 'El crédito dejará de aparecer en la app.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.cards.credits.destroy({ card: props.card.id, credit: credit.id })
            .url,
        { preserveScroll: true },
    );
};

const statusChip = {
    complete: {
        label: 'Licencia completa',
        icon: CircleCheck,
        class: 'border-[#C8DCCF] bg-[#EAF5EE] text-[#187744]',
    },
    missing: {
        label: 'Falta licencia',
        icon: CircleAlert,
        class: 'border-amber-200 bg-amber-50 text-amber-800',
    },
    no_file: {
        label: 'Sin archivo',
        icon: Info,
        class: 'border-[#DCE7DF] bg-[#F7FAF8] text-[#718477]',
    },
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
                href: admin.cards.index(),
            },
            {
                title: 'Créditos',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Créditos: ${card.name}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-[#F2F7F4] p-4 sm:p-5">
        <PageHeader
            :title="card.name"
            :description="`${card.species?.common_name ?? 'Sin especie'} · Registra quién hizo cada archivo de la tarjeta y con qué licencia; así aparece en los créditos de la app.`"
            section="Créditos de tarjeta"
            :icon="Copyright"
        >
            <template #actions>
                <PageHeaderLink
                    :href="admin.cards.show(card.id).url"
                    label="Ver tarjeta"
                    :icon="Eye"
                />
            </template>
        </PageHeader>

        <!-- Progreso -->
        <div
            class="flex flex-col gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center sm:p-5"
            :class="
                progress.missing.length
                    ? 'border-amber-200 bg-amber-50/60'
                    : 'border-[#C8DCCF] bg-white'
            "
        >
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                :class="
                    progress.missing.length
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-[#E2F3E8] text-[#187744]'
                "
            >
                <ListChecks v-if="progress.missing.length" :size="22" />
                <ShieldCheck v-else :size="22" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-base font-bold text-[#244334]">
                    <template v-if="progress.total === 0">
                        La tarjeta aún no tiene archivos
                    </template>
                    <template v-else>
                        {{ progress.done }} de {{ progress.total }}
                        {{ progress.total === 1 ? 'archivo' : 'archivos' }}
                        con licencia
                    </template>
                </p>
                <p class="text-sm text-[#435D4C]">
                    <template v-if="progress.total === 0">
                        Sube la ilustración, la miniatura o el modelo en el
                        <Link
                            :href="admin.cards.edit(card.id).url"
                            class="font-medium text-[#187744] hover:underline"
                        >
                            formulario de la tarjeta
                        </Link>
                        y después registra aquí su licencia.
                    </template>
                    <template v-else-if="progress.missing.length">
                        Falta registrar {{ missingText }}. La tarjeta no se
                        puede activar hasta que todos sus archivos tengan
                        licencia.
                    </template>
                    <template v-else>
                        Todo listo: los créditos aparecen en la app cuando la
                        tarjeta está activa.
                    </template>
                </p>

                <div
                    v-if="progress.total"
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#E4EEE7]"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="
                            progress.missing.length
                                ? 'bg-amber-500'
                                : 'bg-[#187744]'
                        "
                        :style="{
                            width: `${(progress.done / progress.total) * 100}%`,
                        }"
                    />
                </div>
            </div>

            <div
                v-if="progress.missing.length"
                class="flex flex-wrap gap-2 sm:justify-end"
            >
                <a
                    v-for="value in progress.missing"
                    :key="value"
                    :href="`#credit-${value}`"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                >
                    <component :is="ASSET_ICONS[value]" :size="14" />
                    {{ labelOf(value) }}
                </a>
            </div>
        </div>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <!-- Un panel por archivo -->
            <div class="space-y-5">
                <AdminCard
                    v-for="asset in assets"
                    :id="`credit-${asset.value}`"
                    :key="asset.value"
                    class="scroll-mt-24"
                    :title="asset.label"
                    :description="ASSET_DESCRIPTIONS[asset.value]"
                    :icon="ASSET_ICONS[asset.value]"
                >
                    <div class="space-y-4">
                        <!-- Estado -->
                        <div class="flex flex-wrap items-center gap-3">
                            <img
                                v-if="asset.preview_url"
                                :src="asset.preview_url"
                                :alt="asset.label"
                                class="h-14 w-14 rounded-lg border border-[#DCE7DF] object-cover"
                            />

                            <span
                                class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                :class="statusChip[asset.status].class"
                            >
                                <component
                                    :is="statusChip[asset.status].icon"
                                    :size="14"
                                />
                                {{ statusChip[asset.status].label }}
                            </span>

                            <!-- Créditos opcionales, solo con origen -->
                            <DropdownMenu v-if="asset.has_origin">
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-[#C8DCCF] bg-white px-3 py-2 text-xs font-semibold text-[#187744] transition hover:bg-[#F2F7F4]"
                                    >
                                        <Plus :size="14" />
                                        Añadir crédito adicional
                                        <ChevronDown :size="14" />
                                    </button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent align="end" class="w-80">
                                    <DropdownMenuItem
                                        v-for="role in EXTRA_ROLES"
                                        :key="role.value"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                createUrl(
                                                    asset.value,
                                                    role.value,
                                                )
                                            "
                                            class="flex cursor-pointer flex-col items-start gap-0.5 px-3 py-2"
                                        >
                                            <span
                                                class="text-sm font-semibold text-[#244334]"
                                            >
                                                {{ role.question }}
                                            </span>
                                            <span
                                                class="text-xs text-[#718477]"
                                            >
                                                {{ role.description }}
                                            </span>
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Sin archivo -->
                        <p
                            v-if="!asset.has_file"
                            class="rounded-lg border border-dashed border-[#C8DCCF] p-4 text-sm text-[#718477]"
                        >
                            La tarjeta no tiene
                            {{ asset.label.toLowerCase() }}. Súbela en el
                            <Link
                                :href="admin.cards.edit(card.id).url"
                                class="font-medium text-[#187744] hover:underline"
                            >
                                formulario de la tarjeta
                            </Link>
                            y después registra su licencia aquí.
                        </p>

                        <!-- Sin origen: una sola acción -->
                        <div
                            v-else-if="!asset.has_origin"
                            class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-amber-300 bg-amber-50/40 p-4 sm:flex-row sm:items-center"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ creditRole('origin').question }}
                                </p>
                                <p class="text-xs text-[#718477]">
                                    Indica el autor o el sitio de donde salió y
                                    su licencia. Sin esto, la tarjeta no se
                                    puede activar.
                                </p>
                            </div>

                            <Link
                                :href="createUrl(asset.value, 'origin')"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#187744] bg-[#187744] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146338]"
                            >
                                <Plus :size="16" />
                                Registrar licencia
                            </Link>
                        </div>

                        <!-- Avisos de la cadena de licencias -->
                        <Alert
                            v-for="alert in asset.alerts"
                            :key="alert.message"
                            :class="
                                alert.type === 'warning'
                                    ? 'border-amber-200 bg-amber-50 text-amber-900'
                                    : 'border-sky-200 bg-sky-50 text-sky-900'
                            "
                        >
                            <AlertTriangle v-if="alert.type === 'warning'" />
                            <Info v-else />
                            <AlertDescription
                                :class="
                                    alert.type === 'warning'
                                        ? 'text-amber-800'
                                        : 'text-sky-800'
                                "
                            >
                                {{ alert.message }}
                            </AlertDescription>
                        </Alert>

                        <!-- Cadena: origen, de qué se hizo y extras -->
                        <div
                            v-if="asset.credits.length"
                            class="rounded-xl border border-[#DCE7DF] bg-white"
                        >
                            <div v-if="chains[asset.value].origin" class="p-4">
                                <p
                                    class="mb-1.5 text-[11px] font-semibold tracking-wide text-[#718477] uppercase"
                                >
                                    Quién lo hizo
                                </p>
                                <CreditChainRow
                                    :credit="chains[asset.value].origin!"
                                    :edit-url="
                                        editUrl(chains[asset.value].origin!)
                                    "
                                    @delete="
                                        deleteCredit(
                                            chains[asset.value].origin!,
                                        )
                                    "
                                />
                            </div>

                            <div
                                v-if="
                                    chains[asset.value].basis ||
                                    chains[asset.value].extras.length
                                "
                                class="space-y-3 border-t border-[#E4EEE7] p-4 pl-6"
                            >
                                <a
                                    v-if="chains[asset.value].basis"
                                    :href="`#credit-${chains[asset.value].basis!.value}`"
                                    class="flex items-center gap-2 text-sm text-[#435D4C] hover:text-[#187744]"
                                >
                                    <CornerDownRight
                                        :size="16"
                                        class="shrink-0 text-[#A9CBB5]"
                                    />
                                    Hecho a partir de
                                    <span class="font-semibold">
                                        {{
                                            chains[
                                                asset.value
                                            ].basis!.label.toLowerCase()
                                        }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            statusChip[
                                                chains[asset.value].basis!
                                                    .status
                                            ].class
                                        "
                                    >
                                        {{
                                            statusChip[
                                                chains[asset.value].basis!
                                                    .status
                                            ].label
                                        }}
                                    </span>
                                    <ArrowUpRight :size="14" />
                                </a>

                                <div
                                    v-for="credit in chains[asset.value].extras"
                                    :key="credit.id"
                                    class="flex gap-2"
                                >
                                    <CornerDownRight
                                        :size="16"
                                        class="mt-0.5 shrink-0 text-[#A9CBB5]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <CreditChainRow
                                            :credit="credit"
                                            :edit-url="editUrl(credit)"
                                            @delete="deleteCredit(credit)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </AdminCard>
            </div>

            <!-- Vista previa en la app -->
            <aside class="lg:sticky lg:top-6">
                <AdminCard
                    title="Así se verá en la app"
                    :description="
                        card.is_active
                            ? 'Pantalla de créditos de la app.'
                            : 'Cuando la tarjeta esté activa (las inactivas no se publican).'
                    "
                    :icon="Smartphone"
                >
                    <div
                        v-if="appPreview.length"
                        class="rounded-xl border border-[#DCE7DF] bg-white p-4"
                    >
                        <p class="text-sm font-bold text-[#244334]">
                            {{ card.name }}
                        </p>

                        <ul class="mt-2 space-y-2">
                            <li
                                v-for="row in appPreview"
                                :key="row.id"
                                class="text-sm text-[#435D4C]"
                            >
                                <span
                                    class="text-xs font-semibold text-[#718477]"
                                >
                                    {{ row.asset_label }}
                                </span>
                                <br />
                                {{ row.attribution }}
                            </li>
                        </ul>
                    </div>

                    <p v-else class="text-sm text-[#718477]">
                        Aún no hay créditos: la tarjeta no aparecerá en la
                        pantalla de créditos.
                    </p>
                </AdminCard>
            </aside>
        </div>
    </div>
</template>
