<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    ListChecks,
    Upload,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref } from 'vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { checkQuizRow, normalizeQuestionText } from '@/lib/quizImport';
import type { QuizImportRow, QuizRowCheck } from '@/lib/quizImport';
import admin from '@/routes/admin';

/*
 * Revisión y guardado de preguntas en lote, ya sean importadas de un JSON o
 * generadas con IA.
 */
const props = withDefaults(
    defineProps<{
        cardId: number;
        items: QuizImportRow[];
        existingQuestions: string[];
        maxQuestions: number;
        variant?: 'import' | 'generate';
    }>(),
    { variant: 'import' },
);

const TEXT = computed(() =>
    props.variant === 'generate'
        ? {
              title: '2. Revisa las preguntas',
              description:
                  'Solo se guardan las preguntas marcadas. Las repetidas y las que tienen errores no se guardan.',
              verb: 'Guardar',
              confirm: 'Sí, guardar',
              loading: 'Guardando...',
              inactive: 'Se guardarán inactivas; podrás activarlas después.',
              switchLabel: 'Guardar como activas',
              limit: 'Guarda',
              rowLabel: 'Guardar',
          }
        : {
              title: '3. Revisa las preguntas',
              description:
                  'Solo se importan las preguntas marcadas. Las repetidas y las que tienen errores no se importan.',
              verb: 'Importar',
              confirm: 'Sí, importar',
              loading: 'Importando...',
              inactive: 'Se importarán inactivas; podrás activarlas después.',
              switchLabel: 'Importar como activas',
              limit: 'Importa',
              rowLabel: 'Importar',
          },
);

const existingKeys = computed(
    () => new Set(props.existingQuestions.map(normalizeQuestionText)),
);

const rows = computed<(QuizImportRow & QuizRowCheck)[]>(() => {
    const seen = new Set<string>();

    return props.items.map((row) => ({
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
        title: `¿${TEXT.value.verb} ${total} ${total === 1 ? 'pregunta' : 'preguntas'}?`,
        text: form.is_active
            ? 'Se usarán en los duelos de esta tarjeta desde ahora.'
            : TEXT.value.inactive,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: TEXT.value.confirm,
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
    })).post(admin.cards.quiz.import.store(props.cardId).url, {
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
</script>

<template>
    <!-- 3. Vista previa -->
    <AdminCard
        v-if="rows.length"
        :title="TEXT.title"
        :description="TEXT.description"
        :icon="ListChecks"
    >
        <div class="space-y-4">
            <Alert class="border-amber-200 bg-amber-50 text-amber-900">
                <AlertTriangle />
                <AlertTitle>Revisa cada pregunta antes de continuar</AlertTitle>
                <AlertDescription class="text-amber-800">
                    La IA puede equivocarse o inventar datos. Lee las preguntas
                    y sus respuestas, y desmarca las que no sean correctas.
                </AlertDescription>
            </Alert>

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

                <span
                    class="rounded-lg border px-2.5 py-1 sm:ml-auto"
                    :class="
                        tooMany
                            ? STATUS.error.class
                            : 'border-[#DCE7DF] bg-[#F7FAF8] text-[#435D4C]'
                    "
                    aria-live="polite"
                >
                    {{ selected.length }} de {{ maxQuestions }} marcadas ·
                    {{
                        tooMany
                            ? `sobran ${selected.length - maxQuestions}`
                            : `caben ${maxQuestions - selected.length} más`
                    }}
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
                        `${TEXT.limit} como máximo ${maxQuestions} preguntas a la vez.`
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
                        :aria-label="`${TEXT.rowLabel} la pregunta ${index + 1}`"
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
                            <li class="flex items-start gap-1.5 text-[#187744]">
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
                :label="TEXT.switchLabel"
                description="Las preguntas activas se usan en los duelos de esta tarjeta."
            />
        </div>
    </AdminCard>

    <!-- Acciones -->
    <div
        class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
    >
        <CancelButton
            :href="admin.cards.quiz.index(cardId).url"
            :icon="ArrowLeft"
            label="Cancelar"
        />

        <AdminButton
            type="button"
            variant="primary"
            :icon="Upload"
            :disabled="!selected.length || tooMany"
            :loading="form.processing"
            :loading-text="TEXT.loading"
            @click="submit"
        >
            {{ TEXT.verb }} {{ selected.length }}
            {{ selected.length === 1 ? 'pregunta' : 'preguntas' }}
        </AdminButton>
    </div>
</template>
