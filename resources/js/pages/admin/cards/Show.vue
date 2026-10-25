<script setup lang="ts">
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
    Pencil,
    QrCode,
    RotateCcw,
    Swords,
    Tag,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

import CardPreview from '@/components/admin/cards/CardPreview.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';

import { getRarityLabel } from '@/lib/cardRarity';
import type { AccentColor } from '@/lib/cardColors';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';
import type { DuelSettingsSource } from '@/lib/cardRarity';
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

    <div class="space-y-4 bg-[#F8FAF8] p-3 sm:p-5">
        <!-- Encabezado compacto -->
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-[#DCE7DF] bg-white p-4">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#EAF3EC] text-[#187744]">
                    <Tag :size="20" />
                </div>

                <div class="min-w-0">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-[#718477]">
                            Tarjetas / #{{ card.id }}
                        </span>

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="rarityClass(card.rarity)"
                        >
                            {{ rarityLabel(card.rarity) }}
                        </span>
                    </div>

                    <h1 class="truncate text-xl font-bold text-[#244334]">
                        {{ card.name }}
                    </h1>

                    <p class="mt-1 flex items-center gap-1.5 text-xs text-[#718477]">
                        <CheckCircle2 v-if="card.is_active" :size="14" class="text-[#187744]" />
                        <CircleOff v-else :size="14" />
                        {{ card.is_active ? 'Activa' : 'Inactiva' }}
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <Link
                    :href="admin.cards.index().url"
                    class="inline-flex items-center gap-2 rounded-lg border border-[#DCE7DF] px-3 py-2 text-sm font-medium text-[#435D4C] hover:bg-[#F2F7F4]"
                >
                    <ArrowLeft :size="16" />
                    Volver
                </Link>

                <Link
                    :href="admin.cards.edit(card.id).url"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#187744] px-3 py-2 text-sm font-medium text-white hover:bg-[#126238]"
                >
                    <Pencil :size="16" />
                    Editar
                </Link>
            </div>
        </header>

        <!-- Información principal -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Miniatura -->
            <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
                <div class="mb-3 flex items-center gap-2">
                    <FileImage :size="18" class="text-[#187744]" />
                    <h2 class="text-sm font-semibold text-[#244334]">
                        Miniatura
                    </h2>
                </div>

                <div class="flex min-h-64 items-center justify-center rounded-lg bg-[#F6F9F6] p-3">
                    <img
                        v-if="card.thumbnail_image"
                        :src="storageUrl(card.thumbnail_image) ?? ''"
                        :alt="card.name"
                        class="max-h-[380px] w-full object-contain"
                    />

                    <div v-else class="py-8 text-center">
                        <ImageOff :size="30" class="mx-auto text-[#84958A]" />
                        <p class="mt-2 text-sm font-medium text-[#435D4C]">
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

                <p
                    v-if="card.thumbnail_image"
                    class="mt-2 break-all text-xs text-[#718477]"
                >
                    {{ card.thumbnail_image }}
                </p>

                <a
                    v-if="card.generated_image"
                    :href="storageUrl(card.generated_image) ?? ''"
                    target="_blank"
                    rel="noopener"
                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-[#187744] hover:underline"
                >
                    <ExternalLink :size="14" />
                    Ver imagen final guardada
                </a>
            </section>

            <!-- Datos generales -->
            <section class="rounded-xl border border-[#DCE7DF] bg-white p-4 lg:col-span-2">
                <div class="mb-4 flex items-center gap-2">
                    <Info :size="18" class="text-[#187744]" />
                    <h2 class="text-sm font-semibold text-[#244334]">
                        Información general
                    </h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-[#F6F9F6] p-3">
                        <p class="text-xs text-[#718477]">Especie</p>
                        <p class="mt-1 text-sm font-semibold text-[#244334]">
                            {{ card.species.common_name }}
                        </p>
                        <p class="mt-0.5 text-xs italic text-[#718477]">
                            {{ card.species.scientific_name }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-[#F6F9F6] p-3">
                        <p class="text-xs text-[#718477]">Rareza</p>
                        <span
                            class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="rarityClass(card.rarity)"
                        >
                            {{ rarityLabel(card.rarity) }}
                        </span>
                    </div>

                    <div class="rounded-lg border border-[#E5ECE7] p-3">
                        <p class="text-xs text-[#718477]">Orden de visualización</p>
                        <p class="mt-1 text-sm font-medium text-[#244334]">
                            {{ card.sort_order }}
                        </p>
                    </div>

                    <div class="rounded-lg border border-[#E5ECE7] p-3">
                        <p class="text-xs text-[#718477]">Preguntas por duelo</p>
                        <p class="mt-1 text-sm font-semibold text-[#244334]">
                            {{ card.effective_questions_per_duel ?? 3 }} preguntas
                            <span class="text-xs font-normal text-[#718477]">
                                ({{ duelSettingsLabel }})
                            </span>
                        </p>
                    </div>

                    <div class="rounded-lg border border-[#E5ECE7] p-3">
                        <p class="text-xs text-[#718477]">Límite de reintentos</p>
                        <p class="mt-1 text-sm font-semibold text-[#244334]">
                            {{ card.effective_retries ?? 3 }}
                            <span class="text-xs font-normal text-[#718477]">
                                ({{ duelSettingsLabel }})
                            </span>
                        </p>
                    </div>

                    <div class="rounded-lg border border-[#E5ECE7] p-3">
                        <p class="text-xs text-[#718477]">Espera tras agotar reintentos</p>
                        <p class="mt-1 text-sm font-semibold text-[#244334]">
                            {{ card.effective_cooldown_minutes ?? 1440 }} min
                            <span class="text-xs font-normal text-[#718477]">
                                ({{ duelSettingsLabel }})
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Estadísticas de combate -->
                <div class="mt-4 border-t border-[#E5ECE7] pt-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-[#435D4C]">
                        Estadísticas de combate
                    </p>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-lg bg-[#EAF5EE] p-3 text-center">
                            <p class="text-xs text-[#718477]">Astucia</p>
                            <p class="mt-0.5 text-lg font-bold text-[#187744]">
                                {{ card.astucia ?? 50 }}<span class="text-xs font-normal text-[#718477]">/100</span>
                            </p>
                        </div>

                        <div class="rounded-lg bg-[#EAF5EE] p-3 text-center">
                            <p class="text-xs text-[#718477]">Rapidez</p>
                            <p class="mt-0.5 text-lg font-bold text-[#187744]">
                                {{ card.rapidez ?? 50 }}<span class="text-xs font-normal text-[#718477]">/100</span>
                            </p>
                        </div>

                        <div class="rounded-lg bg-[#EAF5EE] p-3 text-center">
                            <p class="text-xs text-[#718477]">Fuerza</p>
                            <p class="mt-0.5 text-lg font-bold text-[#187744]">
                                {{ card.fuerza ?? 50 }}<span class="text-xs font-normal text-[#718477]">/100</span>
                            </p>
                        </div>
                    </div>
                </div>

            </section>
        </div>

        <!-- Vista previa de la tarjeta -->
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

        <!-- Modelo 3D -->
        <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Box :size="19" class="text-[#187744]" />
                    <div>
                        <h2 class="text-sm font-semibold text-[#244334]">
                            Modelo 3D
                        </h2>
                        <p class="text-xs text-[#718477]">
                            Recursos digitales de la tarjeta
                        </p>
                    </div>
                </div>

                <span
                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="card.model_file
                        ? 'bg-[#EAF5ED] text-[#187744]'
                        : 'bg-[#F2F4F2] text-[#718477]'"
                >
                    {{ card.model_file ? 'Disponible' : 'Sin modelo' }}
                </span>
            </div>

            <ModelViewer
                v-if="modelPreviewSource"
                :source="modelPreviewSource"
                extension="glb"
                height-class="h-[520px]"
                class="mb-3"
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
                <span class="rounded-md bg-[#F2F7F4] px-2 py-1 text-xs font-semibold uppercase text-[#435D4C]">
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
        </section>

        <!-- Códigos QR -->
        <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <QrCode :size="19" class="text-[#187744]" />
                    <div>
                        <h2 class="text-sm font-semibold text-[#244334]">
                            Códigos QR ({{ card.qr_codes_count ?? 0 }})
                        </h2>
                        <p class="text-xs text-[#718477]">
                            Los visitantes escanean estos códigos para iniciar un duelo por la tarjeta
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.cards.qrCodes.index(card.id).url"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#DCE7DF] px-3 py-1.5 text-xs font-semibold text-[#187744] hover:bg-[#F2F7F4]"
                >
                    <QrCode :size="13" />
                    Gestionar códigos QR
                </Link>
            </div>
        </section>

        <!-- Preguntas del Quiz -->
        <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <HelpCircle :size="19" class="text-[#187744]" />
                    <div>
                        <h2 class="text-sm font-semibold text-[#244334]">
                            Preguntas del quiz ({{ card.quiz_questions_count ?? 0 }})
                        </h2>
                        <p class="text-xs text-[#718477]">
                            {{ card.active_quiz_questions_count ?? 0 }}
                            {{ card.active_quiz_questions_count === 1 ? 'pregunta activa' : 'preguntas activas' }}
                            · cada duelo usa {{ card.effective_questions_per_duel ?? 3 }}
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.cards.quiz.index(card.id).url"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#DCE7DF] px-3 py-1.5 text-xs font-semibold text-[#187744] hover:bg-[#F2F7F4]"
                >
                    <Pencil :size="13" />
                    Gestionar preguntas
                </Link>
            </div>

            <p
                v-if="(card.active_quiz_questions_count ?? 0) < (card.effective_questions_per_duel ?? 3)"
                class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
            >
                <Info :size="14" class="mt-0.5 shrink-0" />
                {{
                    (card.active_quiz_questions_count ?? 0) === 0
                        ? 'Sin preguntas activas no se puede iniciar un duelo por esta tarjeta.'
                        : 'Hay menos preguntas activas que las configuradas por duelo; cada duelo usará todas las activas.'
                }}
            </p>
        </section>
    </div>
</template>