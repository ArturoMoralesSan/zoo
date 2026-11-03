<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Box,
    CircleAlert,
    CircleCheck,
    Copyright,
    ExternalLink,
    Eye,
    FileImage,
    Image,
    Info,
    Plus,
    Smartphone,
    Sparkles,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';

import AdminCard from '@/components/admin/AdminCard.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PageHeaderLink from '@/components/admin/PageHeaderLink.vue';
import TableActions from '@/components/admin/TableActions.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { CardSummary } from '@/lib/cardQrCode';
import { CREDIT_ROLES } from '@/lib/credits/types';
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
    status: 'complete' | 'missing' | 'no_file';
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

const ASSET_ICONS = { artwork: FileImage, thumbnail: Image, model: Box };

const ROLE_CLASSES: Record<CreditRole, string> = {
    origin: 'border-[#C8DCCF] bg-[#EAF5EE] text-[#187744]',
    based_on: 'border-sky-200 bg-sky-50 text-sky-800',
    component: 'border-violet-200 bg-violet-50 text-violet-800',
};

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
                ? 'Es el origen del recurso: sin él, el recurso quedará como "Falta licencia".'
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
            :description="`${card.species?.common_name ?? 'Sin especie'} · Licencia y atribución de la ilustración, la miniatura y el modelo 3D. Todo lo que registres aquí aparece en la pantalla de créditos de la app cuando la tarjeta está activa.`"
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

        <!-- Un panel por recurso -->
        <AdminCard
            v-for="asset in assets"
            :id="`credit-${asset.value}`"
            :key="asset.value"
            class="scroll-mt-24"
            :title="asset.label"
            :description="
                asset.value === 'model'
                    ? 'Modelo 3D que se muestra en realidad aumentada.'
                    : asset.value === 'artwork'
                      ? 'Fondo de la plantilla de la tarjeta.'
                      : 'Imagen de los listados de la colección.'
            "
            :icon="ASSET_ICONS[asset.value]"
        >
            <div class="space-y-4">
                <!-- Estado y acciones -->
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

                    <div
                        v-if="asset.has_file"
                        class="ml-auto flex flex-wrap gap-2"
                    >
                        <Link
                            v-for="role in CREDIT_ROLES.filter(
                                (item) =>
                                    item.value !== 'origin' ||
                                    !asset.has_origin,
                            )"
                            :key="role.value"
                            :href="createUrl(asset.value, role.value)"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                            :class="
                                role.value === 'origin'
                                    ? 'border-[#187744] bg-[#187744] text-white hover:bg-[#146338]'
                                    : 'border-[#C8DCCF] bg-white text-[#187744] hover:bg-[#F2F7F4]'
                            "
                        >
                            <Plus :size="14" />
                            {{
                                role.value === 'origin'
                                    ? 'Agregar origen'
                                    : role.label
                            }}
                        </Link>
                    </div>
                </div>

                <!-- Sin archivo -->
                <p
                    v-if="!asset.has_file"
                    class="rounded-lg border border-dashed border-[#C8DCCF] p-4 text-sm text-[#718477]"
                >
                    La tarjeta no tiene {{ asset.label.toLowerCase() }}. Súbela
                    en el
                    <Link
                        :href="admin.cards.edit(card.id).url"
                        class="font-medium text-[#187744] hover:underline"
                    >
                        formulario de la tarjeta
                    </Link>
                    y después registra su licencia aquí.
                </p>

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

                <!-- Créditos -->
                <ul
                    v-if="asset.credits.length"
                    class="divide-y divide-[#E4EEE7] rounded-xl border border-[#DCE7DF] bg-white"
                >
                    <li
                        v-for="credit in asset.credits"
                        :key="credit.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start"
                    >
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="ROLE_CLASSES[credit.role]"
                                >
                                    {{ credit.role_label }}
                                </span>

                                <span
                                    v-if="credit.is_ai"
                                    class="inline-flex items-center gap-1 rounded-md border border-violet-200 bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-800"
                                >
                                    <Sparkles :size="11" />
                                    IA ·
                                    {{
                                        credit.ai_checked_at
                                            ? 'revisada'
                                            : 'sin revisar'
                                    }}
                                </span>

                                <a
                                    v-if="credit.license_url"
                                    :href="credit.license_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#187744] hover:underline"
                                >
                                    {{ credit.license_short_name }}
                                    <ExternalLink :size="11" />
                                </a>
                                <span
                                    v-else
                                    class="text-xs font-semibold text-[#435D4C]"
                                >
                                    {{ credit.license_short_name }}
                                </span>

                                <a
                                    v-if="credit.source_url"
                                    :href="credit.source_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 text-xs text-[#718477] hover:underline"
                                >
                                    {{ credit.source_label }}
                                    <ExternalLink :size="11" />
                                </a>
                                <span
                                    v-else-if="credit.source_label"
                                    class="text-xs text-[#718477]"
                                >
                                    {{ credit.source_label }}
                                </span>
                            </div>

                            <p class="text-sm text-[#244334]">
                                {{ credit.attribution }}
                            </p>
                        </div>

                        <TableActions
                            :edit-url="editUrl(credit)"
                            :show-view="false"
                            item-name="crédito"
                            @delete="deleteCredit(credit)"
                        />
                    </li>
                </ul>
            </div>
        </AdminCard>

        <!-- Vista previa en la app -->
        <AdminCard
            title="Vista previa en la app"
            :description="
                card.is_active
                    ? 'Así aparece esta tarjeta en la pantalla de créditos (GET /api/v1/credits).'
                    : 'Así aparecerá cuando la tarjeta esté activa (las inactivas no se publican).'
            "
            :icon="Smartphone"
        >
            <div
                v-if="appPreview.length"
                class="rounded-xl border border-[#DCE7DF] bg-white p-4"
            >
                <p class="text-sm font-bold text-[#244334]">{{ card.name }}</p>

                <ul class="mt-2 space-y-2">
                    <li
                        v-for="row in appPreview"
                        :key="row.id"
                        class="text-sm text-[#435D4C]"
                    >
                        <span class="text-xs font-semibold text-[#718477]">
                            {{ row.asset_label }}
                        </span>
                        <br />
                        {{ row.attribution }}
                    </li>
                </ul>
            </div>

            <p v-else class="text-sm text-[#718477]">
                Aún no hay créditos: la tarjeta no aparecerá en la pantalla de
                créditos.
            </p>
        </AdminCard>
    </div>
</template>
