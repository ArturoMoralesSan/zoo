<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import QuizCountPicker from '@/components/admin/cards/QuizCountPicker.vue';
import QuizReviewList from '@/components/admin/cards/QuizReviewList.vue';
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
    parseQuizImport,
} from '@/lib/quizImport';
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
        Math.min(
            Math.max(Number(questionCount.value) || 10, 1),
            props.maxQuestions,
        ),
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
 * 3. Vista previa y guardado (QuizReviewList).
 */
const parsed = computed(() => parseQuizImport(text.value));

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

        <p class="form-help">
            Esta opción es para usuarios avanzados. Para crear preguntas con un
            clic, usa
            <Link
                :href="admin.cards.quiz.generate(card.id).url"
                class="font-semibold text-[#187744] underline"
            >
                Generar con IA</Link
            >.
        </p>

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
                        <QuizCountPicker
                            v-model="questionCount"
                            input-id="question_count"
                            :limit="maxQuestions"
                            :max="maxQuestions"
                            :saved="existingQuestions.length"
                        />

                        <p class="form-help">
                            El texto ya incluye
                            {{
                                existingQuestions.length
                                    ? `las ${existingQuestions.length} preguntas actuales para que no las repita`
                                    : 'la especie y las reglas del juego'
                            }}.
                        </p>

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

        <QuizReviewList
            :card-id="card.id"
            :items="parsed.rows"
            :existing-questions="existingQuestions"
            :max-questions="maxQuestions"
        />
    </div>
</template>
