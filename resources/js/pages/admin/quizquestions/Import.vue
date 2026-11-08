<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    ChevronDown,
    ClipboardPaste,
    Copy,
    Download,
    FileJson,
    ListChecks,
    Sparkles,
    Upload,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    EXAMPLE_IMPORT,
    buildQuizPrompt,
    checkQuizRow,
    normalizeQuestionText,
    parseQuizImport,
} from '@/lib/quizImport';
import type { QuizImportRow, QuizRowCheck } from '@/lib/quizImport';
import admin from '@/routes/admin';

const props = defineProps<{
    card: {
        id: number;
        name: string;
        species: { common_name: string; scientific_name: string } | null;
    };
    existingQuestions: string[];
    maxQuestions: number;
}>();

/*
 * 1. Prompt para el LLM.
 */
const promptOpen = ref(true);
const questionCount = ref(10);
const copied = ref(false);

const prompt = computed(() =>
    buildQuizPrompt(
        {
            name: props.card.name,
            commonName: props.card.species?.common_name,
            scientificName: props.card.species?.scientific_name,
        },
        Math.min(Math.max(Number(questionCount.value) || 10, 1), 50),
        props.existingQuestions,
    ),
);

const copyPrompt = async () => {
    try {
        await navigator.clipboard.writeText(prompt.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
};

const downloadExample = () => {
    const blob = new Blob([JSON.stringify(EXAMPLE_IMPORT, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = 'preguntas-ejemplo.json';
    link.click();
    URL.revokeObjectURL(url);
};

/*
 * 2. Pegar o subir el resultado.
 */
const mode = ref<'paste' | 'file'>('paste');
const text = ref('');
const fileName = ref<string | null>(null);

const readFile = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    fileName.value = file.name;

    const reader = new FileReader();
    reader.onload = () => {
        text.value = String(reader.result ?? '');
    };
    reader.readAsText(file, 'utf-8');
};

/*
 * 3. Vista previa.
 */
const parsed = computed(() => parseQuizImport(text.value));

const existingKeys = computed(
    () => new Set(props.existingQuestions.map(normalizeQuestionText)),
);

const rows = computed<(QuizImportRow & QuizRowCheck)[]>(() => {
    const seen = new Set<string>();

    return parsed.value.rows.map((row) => ({
        ...row,
        ...checkQuizRow(row, existingKeys.value, seen),
    }));
});

// Preguntas que el administrador quitó de la importación.
const excluded = ref(new Set<number>());

const isIncluded = (index: number) =>
    rows.value[index].status === 'ready' && !excluded.value.has(index);

const toggle = (index: number) => {
    const next = new Set(excluded.value);

    if (next.has(index)) {
        next.delete(index);
    } else {
        next.add(index);
    }

    excluded.value = next;
};

const counts = computed(() => ({
    ready: rows.value.filter((row) => row.status === 'ready').length,
    error: rows.value.filter((row) => row.status === 'error').length,
    duplicate: rows.value.filter((row) => row.status === 'duplicate').length,
}));

const selected = computed(() =>
    rows.value
        .map((row, index) => ({ row, index }))
        .filter(({ index }) => isIncluded(index)),
);

const tooMany = computed(() => selected.value.length > props.maxQuestions);

/*
 * 4. Importar.
 */
const form = useForm({
    questions: [] as QuizImportRow[],
    is_active: true,
});

// Índice de la fila de la vista previa para cada pregunta enviada.
const sentIndexes = ref<number[]>([]);

const serverErrors = computed(() => {
    const byRow = new Map<number, string[]>();

    Object.entries(form.errors as Record<string, string>).forEach(
        ([key, message]) => {
            const match = key.match(/^questions\.(\d+)\./);

            if (!match) {
                return;
            }

            const row = sentIndexes.value[Number(match[1])];
            byRow.set(row, [...(byRow.get(row) ?? []), message]);
        },
    );

    return byRow;
});

const generalError = computed(
    () => (form.errors as Record<string, string>).questions ?? null,
);

const submit = async () => {
    const total = selected.value.length;

    const result = await Swal.fire({
        title: `¿Importar ${total} ${total === 1 ? 'pregunta' : 'preguntas'}?`,
        text: form.is_active
            ? 'Se usarán en los duelos de esta tarjeta desde ahora.'
            : 'Se importarán inactivas; podrás activarlas después.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, importar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    });

    if (!result.isConfirmed) {
        return;
    }

    sentIndexes.value = selected.value.map(({ index }) => index);

    form.transform((data) => ({
        ...data,
        questions: selected.value.map(({ row }) => ({
            question: row.question,
            correct_answer: row.correct_answer,
            wrong_answers: row.wrong_answers,
        })),
    })).post(admin.cards.quiz.import.store(props.card.id).url, {
        preserveScroll: true,
    });
};

const STATUS = {
    ready: {
        label: 'Lista',
        class: 'border-[#C8DCCF] bg-[#EAF5EE] text-[#187744]',
    },
    error: {
        label: 'Con errores',
        class: 'border-red-200 bg-red-50 text-red-700',
    },
    duplicate: {
        label: 'Repetida',
        class: 'border-amber-200 bg-amber-50 text-amber-800',
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
                title: 'Preguntas',
                href: admin.cards.index(),
            },
            {
                title: 'Importar preguntas',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Importar preguntas: ${card.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Importar preguntas"
            :description="`Agrega varias preguntas a la vez a la tarjeta ${card.name}.`"
            section="Quiz de tarjeta"
            :icon="ListChecks"
            :action-icon="ArrowLeft"
            action-label="Volver a las preguntas"
            :action-href="admin.cards.quiz.index(card.id).url"
        />

        <CardContextInfo
            :card="card"
            message="Las preguntas importadas se agregarán a esta tarjeta."
        />

        <!-- 1. Generar con IA -->
        <Collapsible v-model:open="promptOpen">
            <AdminCard :padding="false">
                <CollapsibleTrigger as-child>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-[#F7FAF8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#187744]/20 focus-visible:ring-inset sm:px-6"
                        :aria-expanded="promptOpen"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                        >
                            <Sparkles :size="20" aria-hidden="true" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-semibold text-[#244334]">
                                1. Generar preguntas con IA
                            </h2>
                            <p class="mt-1 text-sm leading-5 text-[#718477]">
                                Copia este texto en ChatGPT, Gemini u otro
                                asistente. Te dará un archivo preguntas.json
                                para descargar o el JSON en texto plano para
                                pegar.
                            </p>
                        </div>

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#435D4C] transition-colors"
                            :class="
                                promptOpen
                                    ? 'bg-[#E4F2E8] text-[#187744]'
                                    : 'bg-[#F2F7F4]'
                            "
                            aria-hidden="true"
                        >
                            <ChevronDown
                                :size="22"
                                class="transition-transform duration-200"
                                :class="{ 'rotate-180': promptOpen }"
                            />
                        </span>
                    </button>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <div
                        class="space-y-4 border-t border-[#E5ECE7] px-5 py-5 sm:px-6"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="form-field w-40">
                                <label for="question_count" class="form-label">
                                    Cantidad de preguntas
                                </label>
                                <input
                                    id="question_count"
                                    v-model.number="questionCount"
                                    type="number"
                                    min="1"
                                    max="50"
                                    class="form-input"
                                />
                            </div>

                            <p class="form-help mb-2 flex-1">
                                El texto ya incluye
                                {{
                                    existingQuestions.length
                                        ? `las ${existingQuestions.length} preguntas actuales para que no las repita`
                                        : 'la especie y las reglas del juego'
                                }}.
                            </p>
                        </div>

                        <textarea
                            :value="prompt"
                            readonly
                            rows="10"
                            class="form-input font-mono text-xs leading-5"
                            aria-label="Texto para el asistente de IA"
                            @focus="
                                ($event.target as HTMLTextAreaElement).select()
                            "
                        />

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-[#187744] bg-[#187744] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#146338]"
                                @click="copyPrompt"
                            >
                                <Check v-if="copied" :size="16" />
                                <Copy v-else :size="16" />
                                {{ copied ? 'Copiado' : 'Copiar texto' }}
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C] transition hover:bg-[#F2F7F4]"
                                @click="downloadExample"
                            >
                                <Download :size="16" />
                                Descargar ejemplo (.json)
                            </button>
                        </div>

                        <p class="form-help">
                            Revisa siempre lo que genera la IA: puede
                            equivocarse con datos de la especie.
                        </p>
                    </div>
                </CollapsibleContent>
            </AdminCard>
        </Collapsible>

        <!-- 2. Pegar o subir -->
        <AdminCard
            title="2. Pega o sube el resultado"
            description="Pega la respuesta completa del asistente (el panel ignora el texto alrededor del JSON) o sube un archivo .json."
            :icon="FileJson"
        >
            <div class="space-y-4">
                <div
                    class="inline-flex rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-1"
                    role="tablist"
                >
                    <button
                        v-for="option in [
                            {
                                value: 'paste',
                                label: 'Pegar',
                                icon: ClipboardPaste,
                            },
                            {
                                value: 'file',
                                label: 'Subir archivo',
                                icon: Upload,
                            },
                        ] as const"
                        :key="option.value"
                        type="button"
                        role="tab"
                        :aria-selected="mode === option.value"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                        :class="
                            mode === option.value
                                ? 'bg-white text-[#187744] shadow-sm'
                                : 'text-[#718477] hover:text-[#244334]'
                        "
                        @click="mode = option.value"
                    >
                        <component :is="option.icon" :size="16" />
                        {{ option.label }}
                    </button>
                </div>

                <textarea
                    v-if="mode === 'paste'"
                    v-model="text"
                    rows="8"
                    class="form-input font-mono text-xs"
                    placeholder='{ "questions": [ { "question": "…", "correct_answer": "…", "wrong_answers": ["…", "…"] } ] }'
                    aria-label="Respuesta del asistente"
                />

                <label
                    v-else
                    for="import_file"
                    class="form-upload sm:flex-row sm:text-left"
                >
                    <div class="form-upload-icon">
                        <FileJson :size="22" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium break-all text-[#244334]">
                            {{ fileName || 'Seleccionar archivo .json' }}
                        </p>
                        <p class="form-upload-description mt-1">
                            Formato JSON · como el ejemplo
                        </p>
                    </div>

                    <span
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C]"
                    >
                        <Upload :size="16" />
                        Examinar
                    </span>

                    <input
                        id="import_file"
                        type="file"
                        accept=".json,application/json"
                        class="form-upload-input"
                        @change="readFile"
                    />
                </label>

                <Alert
                    v-if="parsed.error"
                    class="border-red-200 bg-red-50 text-red-800"
                >
                    <AlertTriangle />
                    <AlertTitle>No se pudo leer</AlertTitle>
                    <AlertDescription class="text-red-700">
                        {{ parsed.error }}
                    </AlertDescription>
                </Alert>
            </div>
        </AdminCard>

        <!-- 3. Vista previa -->
        <AdminCard
            v-if="rows.length"
            title="3. Revisa las preguntas"
            description="Solo se importan las preguntas marcadas. Las repetidas y las que tienen errores no se importan."
            :icon="ListChecks"
        >
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span
                        class="rounded-lg border px-2.5 py-1"
                        :class="STATUS.ready.class"
                    >
                        {{ counts.ready }}
                        {{ counts.ready === 1 ? 'lista' : 'listas' }}
                    </span>
                    <span
                        v-if="counts.error"
                        class="rounded-lg border px-2.5 py-1"
                        :class="STATUS.error.class"
                    >
                        {{ counts.error }} con errores
                    </span>
                    <span
                        v-if="counts.duplicate"
                        class="rounded-lg border px-2.5 py-1"
                        :class="STATUS.duplicate.class"
                    >
                        {{ counts.duplicate }}
                        {{ counts.duplicate === 1 ? 'repetida' : 'repetidas' }}
                    </span>
                </div>

                <Alert
                    v-if="generalError || tooMany"
                    class="border-red-200 bg-red-50 text-red-800"
                >
                    <AlertTriangle />
                    <AlertDescription class="text-red-700">
                        {{
                            generalError ??
                            `Importa como máximo ${maxQuestions} preguntas a la vez.`
                        }}
                    </AlertDescription>
                </Alert>

                <ol class="space-y-2">
                    <li
                        v-for="(row, index) in rows"
                        :key="index"
                        class="flex gap-3 rounded-xl border p-3.5"
                        :class="
                            row.status === 'ready'
                                ? isIncluded(index)
                                    ? 'border-[#C8DCCF] bg-white'
                                    : 'border-[#DCE7DF] bg-[#F7FAF8] opacity-70'
                                : row.status === 'error'
                                  ? 'border-red-200 bg-red-50/40'
                                  : 'border-amber-200 bg-amber-50/40'
                        "
                    >
                        <input
                            type="checkbox"
                            class="mt-1 accent-[#187744]"
                            :checked="isIncluded(index)"
                            :disabled="row.status !== 'ready'"
                            :aria-label="`Importar la pregunta ${index + 1}`"
                            @change="toggle(index)"
                        />

                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-start gap-2">
                                <p
                                    class="min-w-0 flex-1 text-sm font-semibold text-[#244334]"
                                >
                                    {{ index + 1 }}.
                                    {{ row.question || '(sin pregunta)' }}
                                </p>

                                <span
                                    class="shrink-0 rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="STATUS[row.status].class"
                                >
                                    {{ STATUS[row.status].label }}
                                </span>
                            </div>

                            <ul class="grid gap-1 text-sm sm:grid-cols-3">
                                <li
                                    class="flex items-start gap-1.5 text-[#187744]"
                                >
                                    <Check :size="14" class="mt-0.5 shrink-0" />
                                    {{ row.correct_answer || '—' }}
                                </li>
                                <li
                                    v-for="(
                                        answer, answerIndex
                                    ) in row.wrong_answers"
                                    :key="answerIndex"
                                    class="flex items-start gap-1.5 text-[#718477]"
                                >
                                    <X :size="14" class="mt-0.5 shrink-0" />
                                    {{ answer || '—' }}
                                </li>
                            </ul>

                            <p
                                v-for="message in [
                                    ...row.messages,
                                    ...(serverErrors.get(index) ?? []),
                                ]"
                                :key="message"
                                class="text-xs"
                                :class="
                                    row.status === 'duplicate'
                                        ? 'text-amber-800'
                                        : 'text-red-700'
                                "
                            >
                                {{ message }}
                            </p>
                        </div>
                    </li>
                </ol>

                <ActiveSwitch
                    id="is_active"
                    v-model="form.is_active"
                    label="Importar como activas"
                    description="Las preguntas activas se usan en los duelos de esta tarjeta."
                />
            </div>
        </AdminCard>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
        >
            <CancelButton
                :href="admin.cards.quiz.index(card.id).url"
                :icon="ArrowLeft"
                label="Cancelar"
            />

            <AdminButton
                type="button"
                variant="primary"
                :icon="Upload"
                :disabled="!selected.length || tooMany"
                :loading="form.processing"
                loading-text="Importando..."
                @click="submit"
            >
                Importar {{ selected.length }}
                {{ selected.length === 1 ? 'pregunta' : 'preguntas' }}
            </AdminButton>
        </div>
    </div>
</template>
