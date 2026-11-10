<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ListChecks,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import QuizCountPicker from '@/components/admin/cards/QuizCountPicker.vue';
import QuizReviewList from '@/components/admin/cards/QuizReviewList.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { isValidQuestionCount } from '@/lib/quizImport';
import type { QuizImportRow } from '@/lib/quizImport';
import admin from '@/routes/admin';

const props = defineProps<{
    card: {
        id: number;
        name: string;
        species: { common_name: string; scientific_name: string } | null;
    };
    existingQuestions: string[];
    maxQuestions: number;
    aiEnabled: boolean;
    topics: { value: string; label: string }[];
}>();

const count = ref(10);
const topic = ref(props.topics[0]?.value ?? 'general');

const generating = ref(false);
const error = ref<string | null>(null);
const items = ref<QuizImportRow[]>([]);

const hasResults = computed(() => items.value.length > 0);

// Lo que aún cabe en la revisión: no se pueden guardar más de maxQuestions a la vez.
const remaining = computed(() => props.maxQuestions - items.value.length);

const countIsValid = computed(() =>
    isValidQuestionCount(count.value, remaining.value),
);

const errorMessage = (
    status: number,
    body: { success?: boolean; message?: string } | null,
): string => {
    if (body?.success === false && body.message) {
        return body.message;
    }

    if (status === 429) {
        return 'Hiciste muchas solicitudes seguidas. Espera un minuto e intenta de nuevo.';
    }

    if (status === 419) {
        return 'Tu sesión expiró. Recarga la página e intenta de nuevo.';
    }

    return 'No se pudieron generar las preguntas. Intenta de nuevo.';
};

const generate = async () => {
    generating.value = true;
    error.value = null;

    try {
        const response = await fetch(
            admin.cards.quiz.generate.store(props.card.id).url,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document
                            .querySelector('meta[name="csrf-token"]')
                            ?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    count: count.value,
                    topic: topic.value,
                    exclude: items.value.map((item) => item.question),
                }),
            },
        );

        const body = await response.json().catch(() => null);

        if (!response.ok) {
            error.value = errorMessage(response.status, body);

            return;
        }

        // "Generar más" agrega al final: los índices de la revisión no cambian.
        items.value = [...items.value, ...body.data.questions];

        if (remaining.value > 0) {
            count.value = Math.min(count.value, remaining.value);
        }
    } catch {
        error.value =
            'No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.';
    } finally {
        generating.value = false;
    }
};

const optionClass = (active: boolean) =>
    active
        ? 'border-[#187744] bg-[#EAF5EE] text-[#187744]'
        : 'border-[#C8DCCF] bg-white text-[#435D4C] hover:bg-[#F2F7F4]';

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
                title: 'Generar con IA',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Generar preguntas: ${card.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Generar preguntas con IA"
            :description="`Crea preguntas para la tarjeta ${card.name} con un clic.`"
            section="Quiz de tarjeta"
            :icon="ListChecks"
            :action-icon="ArrowLeft"
            action-label="Volver a las preguntas"
            :action-href="admin.cards.quiz.index(card.id).url"
        />

        <CardContextInfo
            :card="card"
            message="Las preguntas se agregarán a esta tarjeta cuando las guardes."
        />

        <Alert
            v-if="!aiEnabled"
            class="border-amber-200 bg-amber-50 text-amber-900"
        >
            <AlertTriangle />
            <AlertTitle>La generación con IA aún no está activada</AlertTitle>
            <AlertDescription class="text-amber-800">
                <p>
                    Avisa al equipo técnico. Mientras tanto puedes usar la
                    <Link
                        :href="admin.cards.quiz.import(card.id).url"
                        class="font-semibold underline"
                    >
                        importación por JSON</Link
                    >.
                </p>
            </AlertDescription>
        </Alert>

        <AdminCard
            v-else
            title="1. Elige qué preguntas quieres"
            description="La IA usa los datos de la especie y no repite las preguntas que ya tiene esta tarjeta."
            :icon="Sparkles"
        >
            <div class="space-y-5">
                <QuizCountPicker
                    v-model="count"
                    input-id="generate_count"
                    :limit="remaining"
                    :max="maxQuestions"
                    :saved="existingQuestions.length"
                    :pending="items.length"
                />

                <fieldset>
                    <legend class="form-label">¿Sobre qué tema?</legend>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="option in topics"
                            :key="option.value"
                            type="button"
                            class="rounded-lg border px-4 py-2.5 text-sm font-semibold transition"
                            :class="optionClass(topic === option.value)"
                            :aria-pressed="topic === option.value"
                            @click="topic = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </fieldset>

                <Alert
                    v-if="error"
                    class="border-red-200 bg-red-50 text-red-800"
                    role="alert"
                >
                    <AlertTriangle />
                    <AlertDescription class="text-red-700">
                        {{ error }}
                    </AlertDescription>
                </Alert>

                <div class="flex flex-wrap items-center gap-3">
                    <AdminButton
                        type="button"
                        variant="primary"
                        :icon="Sparkles"
                        :disabled="!countIsValid"
                        :loading="generating"
                        loading-text="Generando preguntas..."
                        @click="generate"
                    >
                        {{ hasResults ? 'Generar más' : 'Generar preguntas' }}
                    </AdminButton>

                    <p v-if="generating" class="form-help" aria-live="polite">
                        Esto puede tardar hasta un minuto.
                    </p>
                </div>
            </div>
        </AdminCard>

        <QuizReviewList
            v-if="hasResults"
            :card-id="card.id"
            :items="items"
            :existing-questions="existingQuestions"
            :max-questions="maxQuestions"
            variant="generate"
        />
    </div>
</template>
