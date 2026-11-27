<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronDown,
    CircleAlert,
    CircleCheck,
    Copyright,
    CornerDownRight,
    Eye,
    EyeOff,
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
import type {
    SpeciesCreditSummary,
    SpeciesImageCreditRow,
    SpeciesImageSummary,
} from '@/lib/credits/speciesCredits';
import { CREDIT_ROLES, creditRole } from '@/lib/credits/types';
import type { CreditRole } from '@/lib/credits/types';
import admin from '@/routes/admin';

interface AppPreviewRow {
    id: number;
    image_label: string;
    role_label: string;
    attribution: string;
}

const props = defineProps<{
    species: SpeciesCreditSummary & { is_active: boolean };
    images: SpeciesImageSummary[];
    appPreview: AppPreviewRow[];
}>();

const EXTRA_ROLES = CREDIT_ROLES.filter((item) => item.value !== 'origin');

const missing = computed(() =>
    props.images.filter((image) => image.status === 'missing'),
);

const withFile = computed(() =>
    props.images.filter((image) => image.status !== 'no_file'),
);

const done = computed(() => withFile.value.length - missing.value.length);

const groups = computed(() =>
    [
        {
            key: 'main',
            title: 'Imagen principal',
            description: 'La imagen grande de la ficha de la especie.',
        },
        {
            key: 'thumbnail',
            title: 'Miniatura',
            description: 'Imagen de los listados y del mapa.',
        },
        {
            key: 'gallery',
            title: 'Galería',
            description: 'Fotos adicionales de la ficha de la especie.',
        },
    ]
        .map((group) => ({
            ...group,
            images: props.images.filter((image) => image.type === group.key),
        }))
        .filter((group) => group.images.length),
);

const origin = (image: SpeciesImageSummary) =>
    image.credits.find((credit) => credit.role === 'origin') ?? null;

const extras = (image: SpeciesImageSummary) =>
    image.credits.filter((credit) => credit.role !== 'origin');

const createUrl = (image: SpeciesImageSummary, role: CreditRole) =>
    admin.species.images.credits.create(
        { species: props.species.id, image: image.id },
        { query: { role } },
    ).url;

const editUrl = (image: SpeciesImageSummary, credit: SpeciesImageCreditRow) =>
    admin.species.images.credits.edit({
        species: props.species.id,
        image: image.id,
        credit: credit.id,
    }).url;

const deleteCredit = async (
    image: SpeciesImageSummary,
    credit: SpeciesImageCreditRow,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar crédito?',
        text:
            credit.role === 'origin'
                ? 'Es quien hizo la imagen: sin este crédito, la imagen quedará como "Falta licencia".'
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
        admin.species.images.credits.destroy({
            species: props.species.id,
            image: image.id,
            credit: credit.id,
        }).url,
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
                title: 'Especies',
                href: admin.species.index(),
            },
            {
                title: 'Créditos',
                href: admin.species.index(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Créditos: ${species.common_name}`" />

    <div class="flex h-full flex-1 flex-col gap-5 bg-[#F2F7F4] p-4 sm:p-5">
        <PageHeader
            :title="species.common_name"
            :description="`${species.scientific_name ?? ''} · Registra quién hizo cada imagen de la especie y con qué licencia; así aparece en los créditos de la app.`"
            section="Créditos de especie"
            :icon="Copyright"
        >
            <template #actions>
                <PageHeaderLink
                    :href="admin.species.show(species.id).url"
                    label="Ver especie"
                    :icon="Eye"
                />
            </template>
        </PageHeader>

        <!-- Progreso -->
        <div
            class="flex flex-col gap-4 rounded-2xl border p-4 sm:flex-row sm:items-center sm:p-5"
            :class="
                missing.length
                    ? 'border-amber-200 bg-amber-50/60'
                    : 'border-[#C8DCCF] bg-white'
            "
        >
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                :class="
                    missing.length
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-[#E2F3E8] text-[#187744]'
                "
            >
                <ListChecks v-if="missing.length" :size="22" />
                <ShieldCheck v-else :size="22" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-base font-bold text-[#244334]">
                    <template v-if="withFile.length === 0">
                        La especie aún no tiene imágenes
                    </template>
                    <template v-else>
                        {{ done }} de {{ withFile.length }}
                        {{ withFile.length === 1 ? 'imagen' : 'imágenes' }}
                        con licencia
                    </template>
                </p>
                <p class="text-sm text-[#435D4C]">
                    <template v-if="withFile.length === 0">
                        Sube la imagen principal, la miniatura o la galería en
                        el
                        <Link
                            :href="admin.species.edit(species.id).url"
                            class="font-medium text-[#187744] hover:underline"
                        >
                            formulario de la especie
                        </Link>
                        y después registra aquí su licencia.
                    </template>
                    <template v-else-if="missing.length && species.is_active">
                        <strong>La especie está activa:</strong> la app muestra
                        las imágenes sin licencia sin sus créditos.
                    </template>
                    <template v-else-if="missing.length">
                        Regístralas antes de activar la especie: si no, la app
                        mostrará esas imágenes sin sus créditos.
                    </template>
                    <template v-else>
                        Todo listo: los créditos aparecen en la app cuando la
                        especie y la imagen están activas.
                    </template>
                </p>

                <div
                    v-if="withFile.length"
                    class="mt-3 h-1.5 overflow-hidden rounded-full bg-[#E4EEE7]"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="
                            missing.length ? 'bg-amber-500' : 'bg-[#187744]'
                        "
                        :style="{
                            width: `${(done / withFile.length) * 100}%`,
                        }"
                    />
                </div>
            </div>

            <div
                v-if="missing.length"
                class="flex flex-wrap gap-2 sm:justify-end"
            >
                <a
                    v-for="image in missing"
                    :key="image.id"
                    :href="`#image-${image.id}`"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                >
                    {{ image.label }}
                </a>
            </div>
        </div>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <!-- Un panel por tipo de imagen -->
            <div class="space-y-5">
                <p
                    v-if="!groups.length"
                    class="rounded-2xl border border-dashed border-[#C8DCCF] bg-white p-6 text-center text-sm text-[#718477]"
                >
                    Esta especie no tiene imágenes.
                </p>

                <AdminCard
                    v-for="group in groups"
                    :key="group.key"
                    :title="group.title"
                    :description="group.description"
                    :icon="Copyright"
                >
                    <div class="divide-y divide-[#E4EEE7]">
                        <div
                            v-for="image in group.images"
                            :id="`image-${image.id}`"
                            :key="image.id"
                            class="scroll-mt-24 space-y-4 py-4 first:pt-0 last:pb-0"
                        >
                            <!-- Estado -->
                            <div class="flex flex-wrap items-center gap-3">
                                <img
                                    v-if="image.preview_url"
                                    :src="image.preview_url"
                                    :alt="image.alt_text ?? image.label"
                                    class="h-14 w-14 rounded-lg border border-[#DCE7DF] object-cover"
                                />

                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-[#244334]"
                                    >
                                        {{ image.label }}
                                    </p>
                                    <p
                                        v-if="!image.is_active"
                                        class="inline-flex items-center gap-1 text-xs text-[#718477]"
                                    >
                                        <EyeOff :size="12" />
                                        Inactiva: no aparece en la app
                                    </p>
                                </div>

                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                    :class="statusChip[image.status].class"
                                >
                                    <component
                                        :is="statusChip[image.status].icon"
                                        :size="14"
                                    />
                                    {{ statusChip[image.status].label }}
                                </span>

                                <!-- Créditos opcionales, solo con origen -->
                                <DropdownMenu v-if="image.has_origin">
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

                                    <DropdownMenuContent
                                        align="end"
                                        class="w-80"
                                    >
                                        <DropdownMenuItem
                                            v-for="role in EXTRA_ROLES"
                                            :key="role.value"
                                            as-child
                                        >
                                            <Link
                                                :href="
                                                    createUrl(image, role.value)
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

                            <!-- Sin origen: una sola acción -->
                            <div
                                v-if="image.has_file && !image.has_origin"
                                class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-amber-300 bg-amber-50/40 p-4 sm:flex-row sm:items-center"
                            >
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-semibold text-[#244334]"
                                    >
                                        {{ creditRole('origin').question }}
                                    </p>
                                    <p class="text-xs text-[#718477]">
                                        Indica el autor o el sitio de donde
                                        salió y su licencia.
                                    </p>
                                </div>

                                <Link
                                    :href="createUrl(image, 'origin')"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#187744] bg-[#187744] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146338]"
                                >
                                    <Plus :size="16" />
                                    Registrar licencia
                                </Link>
                            </div>

                            <!-- Avisos -->
                            <Alert
                                v-for="alert in image.alerts"
                                :key="alert.message"
                                :class="
                                    alert.type === 'warning'
                                        ? 'border-amber-200 bg-amber-50 text-amber-900'
                                        : 'border-sky-200 bg-sky-50 text-sky-900'
                                "
                            >
                                <AlertTriangle
                                    v-if="alert.type === 'warning'"
                                />
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

                            <!-- Origen y extras -->
                            <div
                                v-if="image.credits.length"
                                class="rounded-xl border border-[#DCE7DF] bg-white"
                            >
                                <div v-if="origin(image)" class="p-4">
                                    <p
                                        class="mb-1.5 text-[11px] font-semibold tracking-wide text-[#718477] uppercase"
                                    >
                                        Quién la hizo
                                    </p>
                                    <CreditChainRow
                                        :credit="origin(image)!"
                                        :edit-url="
                                            editUrl(image, origin(image)!)
                                        "
                                        @delete="
                                            deleteCredit(image, origin(image)!)
                                        "
                                    />
                                </div>

                                <div
                                    v-if="extras(image).length"
                                    class="space-y-3 border-t border-[#E4EEE7] p-4 pl-6"
                                >
                                    <div
                                        v-for="credit in extras(image)"
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
                                                :edit-url="
                                                    editUrl(image, credit)
                                                "
                                                @delete="
                                                    deleteCredit(image, credit)
                                                "
                                            />
                                        </div>
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
                        species.is_active
                            ? 'Pantalla de créditos de la app.'
                            : 'Cuando la especie esté activa (las inactivas no se publican).'
                    "
                    :icon="Smartphone"
                >
                    <div
                        v-if="appPreview.length"
                        class="rounded-xl border border-[#DCE7DF] bg-white p-4"
                    >
                        <p class="text-sm font-bold text-[#244334]">
                            {{ species.common_name }}
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
                                    {{ row.image_label }}
                                </span>
                                <br />
                                {{ row.attribution }}
                            </li>
                        </ul>
                    </div>

                    <p v-else class="text-sm text-[#718477]">
                        Aún no hay créditos: la especie no aparecerá en la
                        pantalla de créditos.
                    </p>
                </AdminCard>
            </aside>
        </div>
    </div>
</template>
