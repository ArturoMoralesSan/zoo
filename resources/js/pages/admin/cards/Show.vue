<script setup lang="ts">
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    CheckCircle2,
    CircleOff,
    ExternalLink,
    FileImage,
    HelpCircle,
    ImageOff,
    Info,
    Layers3,
    Palette,
    Pencil,
    QrCode,
    Swords,
    Tag,
    Zap,
} from 'lucide-vue-next';
import { computed, onMounted } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import CardDetailField from '@/components/admin/cards/CardDetailField.vue';
import CardPreview from '@/components/admin/cards/CardPreview.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { autoAccent } from '@/lib/cardColors';
import type { AccentColor } from '@/lib/cardColors';
import {
    CARD_FONTS,
    TITLE_SIZES,
    WEIGHT_LABELS,
    ensureCardFont,
} from '@/lib/cardFonts';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';
import { getRarityLabel } from '@/lib/cardRarity';
import type { DuelSettingsSource } from '@/lib/cardRarity';
import { CARD_STATS } from '@/lib/cardStats';
import { storageUrl } from '@/lib/modelPreview';
import admin from '@/routes/admin';

interface SpeciesCategory {
    id: number;
    name: string;
}

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
    category?: SpeciesCategory;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    thumbnail_image: string | null;
    artwork_image: string | null;
    generated_image: string | null;
    fade_color: string;
    accent_color: AccentColor | null;
    title_font_family: CardFontId;
    title_font_weight: number;
    title_font_size: TitleSize;
    model_file: string | null;
    is_active: boolean;
    sort_order: number;
    astucia?: number;
    rapidez?: number;
    fuerza?: number;
    duel_settings_source?: DuelSettingsSource;
    questions_per_duel?: number | null;
    retries?: number | null;
    effective_questions_per_duel?: number;
    effective_retries?: number;
    effective_cooldown_minutes?: number;
    qr_codes_count?: number;
    species: Species;
    quiz_questions_count?: number;
    active_quiz_questions_count?: number;
}

const props = defineProps<{ card: Card }>();

const modelPreviewSource = computed(() => storageUrl(props.card.model_file));

const duelSettingsLabel = computed(() =>
    props.card.duel_settings_source === 'card' ? 'Personalizado' : 'Por rareza',
);

const rarityClasses: Record<string, string> = {
    comun: 'bg-[#F2F7F4] text-[#718477]',
    rara: 'bg-blue-50 text-blue-700',
    epica: 'bg-purple-50 text-purple-700',
    edicion_especial: 'bg-amber-50 text-amber-700',
};

const rarityLabel = getRarityLabel;

const rarityClass = (value: string) =>
    rarityClasses[value] ?? rarityClasses.comun;

/*
 * Diseño de la tarjeta.
 */
const accentLabels: Record<AccentColor, string> = {
    white: 'Blanco',
    black: 'Negro',
};

const accentLabel = computed(() =>
    props.card.accent_color
        ? accentLabels[props.card.accent_color]
        : `Automático (${accentLabels[autoAccent(props.card.fade_color)]})`,
);

const titleFont = computed(() => CARD_FONTS[props.card.title_font_family]);

const titleFontStyle = computed(() => ({
    fontFamily: `"${titleFont.value.family}"`,
    fontWeight: props.card.title_font_weight,
}));

// El nombre de la fuente se muestra con la propia fuente.
onMounted(() => {
    void ensureCardFont(
        props.card.title_font_family,
        props.card.title_font_weight,
    );
});

const statValue = (key: (typeof CARD_STATS)[number]['key']) =>
    props.card[key] ?? 50;

const hasFewQuestions = computed(
    () =>
        (props.card.active_quiz_questions_count ?? 0) <
        (props.card.effective_questions_per_duel ?? 3),
);

/*
 * Botón secundario del encabezado (mismo tamaño que la acción principal).
 */
const secondaryActionClass =
    'inline-flex items-center justify-center gap-2 rounded-lg border border-[#DCE7DF] border-b-[3px] border-b-[#c8d8ce] bg-white px-3.5 py-2 text-xs font-semibold text-[#244334] transition hover:bg-[#f2f7f4] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 sm:text-[13px]';

const sectionLinkClass =
    'inline-flex items-center gap-1.5 rounded-lg border border-[#DCE7DF] px-3 py-1.5 text-xs font-semibold text-[#187744] hover:bg-[#F2F7F4]';

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
                title: 'Detalle de tarjeta',
                href: admin.cards.show,
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Tarjeta: ${card.name}`" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-5 bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado -->
        <PageHeader
            :title="card.name"
            :description="`${card.species.common_name} · ${card.species.scientific_name}`"
            section="Colección"
            :icon="Tag"
            :action-icon="Pencil"
            action-label="Editar tarjeta"
            :action-href="admin.cards.edit(card.id).url"
        >
            <template #details>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="rarityClass(card.rarity)"
                    >
                        {{ rarityLabel(card.rarity) }}
                    </span>

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="
                            card.is_active
                                ? 'bg-[#EAF5EE] text-[#187744]'
                                : 'bg-[#F2F4F2] text-[#718477]'
                        "
                    >
                        <CheckCircle2 v-if="card.is_active" :size="13" />
                        <CircleOff v-else :size="13" />
                        {{ card.is_active ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
            </template>

            <template #actions>
                <Link
                    :href="admin.cards.index().url"
                    :class="secondaryActionClass"
                >
                    <ArrowLeft :size="16" />
                    Volver a tarjetas
                </Link>
            </template>
        </PageHeader>

        <div
            class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_30rem] xl:items-start"
        >
            <!--
                Vista previa: fija a la derecha en pantallas anchas;
                en pantallas pequeñas aparece antes de los datos.
            -->
            <div class="min-w-0 xl:sticky xl:top-5 xl:order-2">
                <CardPreview
                    :title="card.name"
                    :scientific-name="card.species.scientific_name"
                    :astucia="card.astucia ?? null"
                    :rapidez="card.rapidez ?? null"
                    :fuerza="card.fuerza ?? null"
                    :artwork-url="storageUrl(card.artwork_image)"
                    :fade-color="card.fade_color"
                    :accent-color="card.accent_color"
                    :title-font-family="card.title_font_family"
                    :title-font-weight="card.title_font_weight"
                    :title-font-size="card.title_font_size"
                />
            </div>

            <div class="min-w-0 space-y-5 xl:order-1">
                <!-- Contenido de la tarjeta -->
                <AdminCard
                    title="Contenido de la tarjeta"
                    description="Especie y nombre que aparecen en la tarjeta."
                    :icon="Info"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <CardDetailField label="Especie">
                            {{ card.species.common_name }}
                            <p
                                class="mt-0.5 text-xs font-normal text-[#718477] italic"
                            >
                                {{ card.species.scientific_name }}
                            </p>
                        </CardDetailField>

                        <CardDetailField label="Nombre de la tarjeta">
                            {{ card.name }}
                        </CardDetailField>
                    </div>
                </AdminCard>

                <!-- Estadísticas de la tarjeta -->
                <AdminCard
                    title="Estadísticas de la tarjeta"
                    description="Valores de 1 a 100 de cada atributo de la tarjeta."
                    :icon="Zap"
                >
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div
                            v-for="stat in CARD_STATS"
                            :key="stat.key"
                            class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#EAF5EE] text-[#187744]"
                                >
                                    <HugeiconsIcon
                                        :icon="stat.icon"
                                        :size="20"
                                        :stroke-width="2"
                                    />
                                </span>

                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ stat.label }}
                                </p>
                            </div>

                            <p class="mt-3 text-2xl font-bold text-[#187744]">
                                {{ statValue(stat.key) }}
                                <span class="text-xs font-normal text-[#718477]"
                                    >/100</span
                                >
                            </p>

                            <div
                                class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#DCE7DF]"
                            >
                                <div
                                    class="h-full rounded-full bg-[#187744]"
                                    :style="{
                                        width: `${statValue(stat.key)}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </AdminCard>

                <!-- Imagen de la tarjeta -->
                <AdminCard
                    title="Imagen de la tarjeta"
                    description="Fondo de la plantilla de la tarjeta."
                    :icon="FileImage"
                >
                    <div class="space-y-3">
                        <div
                            class="flex min-h-48 items-center justify-center rounded-lg bg-[#F6F9F6] p-3"
                        >
                            <img
                                v-if="card.artwork_image"
                                :src="storageUrl(card.artwork_image) ?? ''"
                                :alt="card.name"
                                class="max-h-72 w-auto rounded-lg object-contain"
                            />

                            <div v-else class="py-6 text-center">
                                <ImageOff
                                    :size="28"
                                    class="mx-auto text-[#84958A]"
                                />
                                <p
                                    class="mt-2 text-sm font-medium text-[#435D4C]"
                                >
                                    Sin imagen
                                </p>
                                <Link
                                    :href="admin.cards.edit(card.id).url"
                                    class="mt-2 inline-block text-sm font-medium text-[#187744] hover:underline"
                                >
                                    Agregar imagen
                                </Link>
                            </div>
                        </div>

                        <a
                            v-if="card.generated_image"
                            :href="storageUrl(card.generated_image) ?? ''"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-[#187744] hover:underline"
                        >
                            <ExternalLink :size="14" />
                            Ver imagen final guardada
                        </a>
                    </div>
                </AdminCard>

                <!-- Diseño de la tarjeta -->
                <AdminCard
                    title="Diseño de la tarjeta"
                    description="Colores de la plantilla y fuente del nombre de la especie."
                    :icon="Palette"
                >
                    <div class="grid gap-3 sm:grid-cols-2">
                        <CardDetailField label="Color del degradado">
                            <span class="flex items-center gap-2">
                                <span
                                    class="h-5 w-5 rounded-full border border-black/10"
                                    :style="{
                                        backgroundColor: card.fade_color,
                                    }"
                                />
                                <span class="font-mono uppercase">
                                    {{ card.fade_color }}
                                </span>
                            </span>
                        </CardDetailField>

                        <CardDetailField label="Color de acento">
                            {{ accentLabel }}
                        </CardDetailField>

                        <CardDetailField label="Fuente del nombre">
                            <span class="text-base" :style="titleFontStyle">
                                {{ titleFont.family }}
                            </span>
                        </CardDetailField>

                        <CardDetailField label="Peso y tamaño">
                            {{ card.title_font_weight }} ·
                            {{ WEIGHT_LABELS[card.title_font_weight] }}
                            <span class="font-normal text-[#718477]">
                                — {{ TITLE_SIZES[card.title_font_size].label }}
                            </span>
                        </CardDetailField>
                    </div>
                </AdminCard>

                <!-- Colección y duelos -->
                <h2
                    class="px-1 pt-3 text-xs font-semibold tracking-wider text-[#718477] uppercase"
                >
                    Colección y duelos
                </h2>

                <!-- Colección -->
                <AdminCard
                    title="Colección"
                    description="Rareza, orden de aparición y miniatura de la tarjeta."
                    :icon="Layers3"
                >
                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        <div class="grid content-start gap-3">
                            <CardDetailField label="Rareza">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="rarityClass(card.rarity)"
                                >
                                    {{ rarityLabel(card.rarity) }}
                                </span>
                            </CardDetailField>

                            <CardDetailField label="Orden de aparición">
                                {{ card.sort_order }}
                            </CardDetailField>
                        </div>

                        <CardDetailField label="Miniatura">
                            <img
                                v-if="card.thumbnail_image"
                                :src="storageUrl(card.thumbnail_image) ?? ''"
                                :alt="card.name"
                                class="mt-1 h-40 w-full rounded-lg bg-white object-contain"
                            />

                            <div
                                v-else
                                class="mt-1 flex h-40 flex-col items-center justify-center gap-1 rounded-lg bg-white text-center"
                            >
                                <ImageOff :size="22" class="text-[#84958A]" />
                                <span
                                    class="text-xs font-medium text-[#718477]"
                                >
                                    Sin miniatura
                                </span>
                            </div>
                        </CardDetailField>
                    </div>
                </AdminCard>

                <!-- Duelos -->
                <AdminCard
                    title="Configuración de duelos y reintentos"
                    :description="`Origen: ${duelSettingsLabel.toLowerCase()}.`"
                    :icon="Swords"
                >
                    <div class="grid gap-3 sm:grid-cols-3">
                        <CardDetailField label="Preguntas por duelo">
                            {{ card.effective_questions_per_duel ?? 3 }}
                        </CardDetailField>

                        <CardDetailField label="Límite de reintentos">
                            {{ card.effective_retries ?? 3 }}
                        </CardDetailField>

                        <CardDetailField label="Espera tras agotar reintentos">
                            {{ card.effective_cooldown_minutes ?? 1440 }} min
                        </CardDetailField>
                    </div>
                </AdminCard>

                <!-- Preguntas del quiz -->
                <AdminCard
                    :title="`Preguntas del quiz (${card.quiz_questions_count ?? 0})`"
                    :description="`${card.active_quiz_questions_count ?? 0} ${card.active_quiz_questions_count === 1 ? 'pregunta activa' : 'preguntas activas'} · cada duelo usa ${card.effective_questions_per_duel ?? 3}`"
                    :icon="HelpCircle"
                >
                    <div class="space-y-3">
                        <p
                            v-if="hasFewQuestions"
                            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
                        >
                            <Info :size="14" class="mt-0.5 shrink-0" />
                            {{
                                (card.active_quiz_questions_count ?? 0) === 0
                                    ? 'Sin preguntas activas no se puede iniciar un duelo por esta tarjeta.'
                                    : 'Hay menos preguntas activas que las configuradas por duelo; cada duelo usará todas las activas.'
                            }}
                        </p>

                        <Link
                            :href="admin.cards.quiz.index(card.id).url"
                            :class="sectionLinkClass"
                        >
                            <Pencil :size="13" />
                            Gestionar preguntas
                        </Link>
                    </div>
                </AdminCard>

                <!-- Códigos QR -->
                <AdminCard
                    :title="`Códigos QR (${card.qr_codes_count ?? 0})`"
                    description="Los visitantes escanean estos códigos para iniciar un duelo por la tarjeta."
                    :icon="QrCode"
                >
                    <Link
                        :href="admin.cards.qrCodes.index(card.id).url"
                        :class="sectionLinkClass"
                    >
                        <QrCode :size="13" />
                        Gestionar códigos QR
                    </Link>
                </AdminCard>

                <!-- Modelo 3D -->
                <AdminCard
                    title="Modelo 3D"
                    description="Modelo interactivo asociado a la tarjeta."
                    :icon="Box"
                >
                    <div class="space-y-3">
                        <ModelViewer
                            v-if="modelPreviewSource"
                            :source="modelPreviewSource"
                            extension="glb"
                            height-class="h-[460px]"
                        />

                        <a
                            v-if="card.model_file"
                            :href="storageUrl(card.model_file) ?? ''"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-2 rounded-lg border border-[#DCE7DF] p-3 text-sm text-[#187744] hover:bg-[#F6F9F6]"
                        >
                            <Box :size="17" class="shrink-0" />
                            <span class="min-w-0 flex-1 break-all">
                                {{ card.model_file }}
                            </span>
                            <span
                                class="rounded-md bg-[#F2F7F4] px-2 py-1 text-xs font-semibold text-[#435D4C] uppercase"
                            >
                                GLB
                            </span>
                            <ExternalLink :size="15" class="shrink-0" />
                        </a>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-[#DCE7DF] bg-[#F8FAF8] px-4 py-6 text-center"
                        >
                            <p class="text-sm text-[#718477]">
                                Esta tarjeta no tiene un modelo 3D asociado.
                            </p>
                            <Link
                                :href="admin.cards.edit(card.id).url"
                                class="mt-2 inline-block text-sm font-semibold text-[#187744] hover:underline"
                            >
                                Agregar modelo
                            </Link>
                        </div>
                    </div>
                </AdminCard>
            </div>
        </div>
    </div>
</template>
