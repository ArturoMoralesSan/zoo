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
    RotateCcw,
    Swords,
    Tag,
    Zap,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

interface SpeciesCategory {
    id: number;
    name: string;
    retry_limit?: number;
}

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
    category?: SpeciesCategory;
}

interface Answer {
    id: number;
    answer: string;
    is_correct: boolean;
    sort_order: number;
}

interface QuizQuestion {
    id: number;
    question: string;
    is_active: boolean;
    sort_order: number;
    answers?: Answer[];
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
    model_description: string | null;
    is_active: boolean;
    sort_order: number;
    astucia?: number;
    rapidez?: number;
    fuerza?: number;
    questions_per_duel?: number;
    retry_type?: 'card' | 'category';
    retries?: number | null;
    particular_retries?: number;
    species: Species;
    quiz_questions?: QuizQuestion[];
}

defineProps<{ card: Card }>();

const fileUrl = (path: string | null): string | null => {
    if (!path) return null;
    if (/^(https?:\/\/|\/)/.test(path)) return path;
    return `/storage/${path}`;
};

const rarityLabels: Record<string, string> = {
    comun: 'Común',
    rara: 'Rara',
    epica: 'Épica',
    edicion_especial: 'Edición especial',
};

const rarityClasses: Record<string, string> = {
    comun: 'bg-[#F2F7F4] text-[#718477]',
    rara: 'bg-blue-50 text-blue-700',
    epica: 'bg-purple-50 text-purple-700',
    edicion_especial: 'bg-amber-50 text-amber-700',
};

const rarityLabel = (value: string) => rarityLabels[value] ?? value;

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
            <!-- Imagen -->
            <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
                <div class="mb-3 flex items-center gap-2">
                    <FileImage :size="18" class="text-[#187744]" />
                    <h2 class="text-sm font-semibold text-[#244334]">
                        Imagen de la tarjeta
                    </h2>
                </div>

                <div class="flex min-h-64 items-center justify-center rounded-lg bg-[#F6F9F6] p-3">
                    <img
                        v-if="card.card_image"
                        :src="fileUrl(card.card_image) ?? ''"
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
                    v-if="card.card_image"
                    class="mt-2 break-all text-xs text-[#718477]"
                >
                    {{ card.card_image }}
                </p>
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
                        <p class="text-xs text-[#718477]">Edición</p>
                        <p class="mt-1 text-sm font-medium text-[#244334]">
                            {{ card.edition || 'General' }}
                        </p>
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
                            {{ card.questions_per_duel ?? 3 }} preguntas
                        </p>
                    </div>

                    <div class="rounded-lg border border-[#E5ECE7] p-3">
                        <p class="text-xs text-[#718477]">Límite de reintentos</p>
                        <p class="mt-1 text-sm font-semibold text-[#244334]">
                            {{ card.particular_retries ?? card.retries ?? 3 }}
                            <span class="text-xs font-normal text-[#718477]">
                                ({{ card.retry_type === 'category' ? 'Por categoría' : 'Por tarjeta' }})
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

                <div class="mt-4 border-t border-[#E5ECE7] pt-3">
                    <p class="text-xs font-semibold text-[#435D4C]">
                        Descripción
                    </p>
                    <p
                        v-if="card.description"
                        class="mt-1 whitespace-pre-line text-sm leading-6 text-[#5F7566]"
                    >
                        {{ card.description }}
                    </p>
                    <p v-else class="mt-1 text-sm text-[#84958A]">
                        Sin descripción registrada.
                    </p>
                </div>
            </section>
        </div>

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
                    :class="card.model_file || card.model_url
                        ? 'bg-[#EAF5ED] text-[#187744]'
                        : 'bg-[#F2F4F2] text-[#718477]'"
                >
                    {{ card.model_file || card.model_url ? 'Disponible' : 'Sin modelo' }}
                </span>
            </div>

            <div
                v-if="card.model_file || card.model_url"
                class="grid gap-3 md:grid-cols-2"
            >
                <div class="space-y-3">
                    <div
                        v-if="card.model_name || card.model_format"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span class="text-sm font-medium text-[#244334]">
                            {{ card.model_name || 'Modelo 3D' }}
                        </span>
                        <span
                            v-if="card.model_format"
                            class="rounded-md bg-[#F2F7F4] px-2 py-1 text-xs font-semibold uppercase text-[#435D4C]"
                        >
                            {{ card.model_format }}
                        </span>
                    </div>

                    <a
                        v-if="card.model_file"
                        :href="fileUrl(card.model_file) ?? ''"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2 rounded-lg border border-[#DCE7DF] p-3 text-sm text-[#187744] hover:bg-[#F6F9F6]"
                    >
                        <Box :size="17" class="shrink-0" />
                        <span class="min-w-0 flex-1 break-all">
                            {{ card.model_file }}
                        </span>
                        <ExternalLink :size="15" class="shrink-0" />
                    </a>

                    <a
                        v-if="card.model_url"
                        :href="card.model_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2 rounded-lg border border-[#DCE7DF] p-3 text-sm text-[#187744] hover:bg-[#F6F9F6]"
                    >
                        <ExternalLink :size="16" class="shrink-0" />
                        <span class="min-w-0 flex-1 break-all">
                            {{ card.model_url }}
                        </span>
                    </a>
                </div>

                <div class="rounded-lg bg-[#F6F9F6] p-3">
                    <p class="text-xs font-semibold text-[#435D4C]">
                        Descripción del modelo
                    </p>
                    <p
                        v-if="card.model_description"
                        class="mt-1 whitespace-pre-line text-sm leading-6 text-[#5F7566]"
                    >
                        {{ card.model_description }}
                    </p>
                    <p v-else class="mt-1 text-sm text-[#84958A]">
                        Sin descripción adicional.
                    </p>
                </div>
            </div>

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

        <!-- Preguntas del Quiz -->
        <section class="rounded-xl border border-[#DCE7DF] bg-white p-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <HelpCircle :size="19" class="text-[#187744]" />
                    <div>
                        <h2 class="text-sm font-semibold text-[#244334]">
                            Preguntas del quiz
                        </h2>
                        <p class="text-xs text-[#718477]">
                            Preguntas configuradas para los duelos de esta tarjeta
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.cards.edit(card.id).url"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#DCE7DF] px-3 py-1.5 text-xs font-semibold text-[#187744] hover:bg-[#F2F7F4]"
                >
                    <Pencil :size="13" />
                    Gestionar preguntas
                </Link>
            </div>

            <div
                v-if="card.quiz_questions && card.quiz_questions.length > 0"
                class="space-y-3"
            >
                <div
                    v-for="(q, qIdx) in card.quiz_questions"
                    :key="q.id"
                    class="rounded-xl border border-[#E5ECE7] bg-[#FCFEFC] p-3.5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2.5">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-[#187744] text-xs font-bold text-white">
                                {{ qIdx + 1 }}
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ q.question }}
                                </p>
                                <span
                                    class="mt-1 inline-block rounded px-2 py-0.5 text-[11px] font-medium"
                                    :class="q.is_active ? 'bg-[#EAF5EE] text-[#187744]' : 'bg-[#F2F4F2] text-[#718477]'"
                                >
                                    {{ q.is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="q.answers && q.answers.length > 0"
                        class="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3"
                    >
                        <div
                            v-for="(ans, aIdx) in q.answers"
                            :key="ans.id"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-xs"
                            :class="ans.is_correct ? 'border-[#BFCFC6] bg-[#EAF5EE] font-semibold text-[#187744]' : 'border-[#E5ECE7] bg-white text-[#435D4C]'"
                        >
                            <span class="font-bold">{{ String.fromCharCode(65 + aIdx) }}.</span>
                            <span class="truncate">{{ ans.answer }}</span>
                            <span v-if="ans.is_correct" class="ml-auto rounded bg-[#187744] px-1.5 py-0.5 text-[10px] text-white">
                                Correcta
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-[#DCE7DF] bg-[#F8FAF8] px-4 py-6 text-center"
            >
                <p class="text-sm text-[#718477]">
                    Esta tarjeta no tiene preguntas asignadas todavía.
                </p>
                <Link
                    :href="admin.cards.edit(card.id).url"
                    class="mt-2 inline-block text-sm font-semibold text-[#187744] hover:underline"
                >
                    Agregar preguntas
                </Link>
            </div>
        </section>
    </div>
</template>