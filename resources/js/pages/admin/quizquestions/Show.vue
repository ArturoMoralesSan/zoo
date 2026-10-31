<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    CircleOff,
    HelpCircle,
    Info,
    ListChecks,
    Pencil,
} from 'lucide-vue-next';
import { computed } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import CardDetailField from '@/components/admin/cards/CardDetailField.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PageHeaderLink from '@/components/admin/PageHeaderLink.vue';
import admin from '@/routes/admin';

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    thumbnail_image: string | null;
    model_file: string | null;
    is_active: boolean;
    sort_order: number;
    species: Species;
}

interface Answer {
    id: number;
    quiz_question_id: number;
    answer: string;
    is_correct: boolean;
    sort_order: number;
}

interface Question {
    id: number;
    card_id: number;
    question: string;
    is_active: boolean;
    answers: Answer[];
}

const props = defineProps<{
    card: Card;
    question: Question;
}>();

const answerLabel = (index: number) => {
    return String.fromCharCode(65 + index);
};

const quizUrl = computed(() => admin.cards.quiz.index(props.card.id).url);

const editUrl = computed(
    () =>
        admin.cards.quiz.edit({
            card: props.card.id,
            quizQuestion: props.question.id,
        }).url,
);

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
                title: 'Preguntas del quiz',
                href: admin.cards.index(),
            },
            {
                title: 'Ver pregunta',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Ver pregunta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Ver pregunta"
            description="Detalle de la pregunta y sus respuestas para el quiz de la tarjeta."
            section="Colección"
            :icon="HelpCircle"
            :action-icon="Pencil"
            action-label="Editar pregunta"
            :action-href="editUrl"
        >
            <template #actions>
                <PageHeaderLink
                    :href="quizUrl"
                    label="Volver al quiz"
                    :icon="ArrowLeft"
                />
            </template>
        </PageHeader>

        <!-- Tarjeta asociada -->
        <CardContextInfo
            :card="card"
            message="Esta pregunta forma parte del quiz de esta tarjeta."
        />

        <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
            <!-- Pregunta -->
            <AdminCard
                title="Pregunta"
                description="Texto que verá el usuario al responder el quiz."
                :icon="HelpCircle"
                class="lg:col-span-2"
            >
                <p class="text-base leading-7 font-medium text-[#244334]">
                    {{ question.question }}
                </p>
            </AdminCard>

            <!-- Información -->
            <AdminCard title="Información" :icon="Info">
                <div class="space-y-3">
                    <CardDetailField label="Estado">
                        <span
                            v-if="question.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#c9e4d2] bg-[#dcefe2] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            <span class="h-2 w-2 rounded-full bg-[#187744]" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#e1e7e2] bg-[#f2f5f3] px-3 py-1.5 text-xs font-medium text-[#718477]"
                        >
                            <CircleOff :size="14" />
                            Inactiva
                        </span>
                    </CardDetailField>

                    <CardDetailField label="ID de la pregunta">
                        #{{ question.id }}
                    </CardDetailField>

                    <CardDetailField label="Tarjeta">
                        <Link
                            :href="admin.cards.show(card.id).url"
                            class="text-[#187744] hover:underline"
                        >
                            {{ card.name }}
                        </Link>
                    </CardDetailField>
                </div>
            </AdminCard>
        </div>

        <!-- Respuestas -->
        <AdminCard
            title="Respuestas"
            :description="`${question.answers.length} respuestas · La respuesta resaltada es la correcta.`"
            :icon="ListChecks"
        >
            <div class="flex flex-col gap-4">
                <div
                    v-for="(answer, index) in question.answers"
                    :key="answer.id"
                    class="rounded-xl border p-4"
                    :class="
                        answer.is_correct
                            ? 'border-[#BFCFC6] bg-[#EAF5EE]'
                            : 'border-[#DCE7DF] bg-white'
                    "
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <!-- Identificador -->
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold"
                            :class="
                                answer.is_correct
                                    ? 'border-[#BFCFC6] bg-[#D8EDE0] text-[#187744]'
                                    : 'border-[#DCE7DF] bg-[#F2F7F4] text-[#718477]'
                            "
                        >
                            {{ answerLabel(index) }}
                        </div>

                        <!-- Respuesta -->
                        <div class="min-w-0 flex-1">
                            <p class="text-sm leading-6 font-medium text-[#244334]">
                                {{ answer.answer }}
                            </p>

                            <p class="mt-1 text-xs text-[#84958a]">
                                ID {{ answer.id }}
                            </p>
                        </div>

                        <!-- Correcta -->
                        <span
                            v-if="answer.is_correct"
                            class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg border border-[#BFCFC6] bg-[#D8EDE0] px-3 py-1.5 text-xs font-semibold text-[#187744]"
                        >
                            <CheckCircle2 :size="14" />
                            Correcta
                        </span>

                        <span
                            v-else
                            class="shrink-0 text-xs text-[#84958a]"
                        >
                            Incorrecta
                        </span>
                    </div>
                </div>
            </div>
        </AdminCard>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
        >
            <CancelButton
                :href="quizUrl"
                :icon="ArrowLeft"
                label="Volver al quiz"
            />

            <Link
                :href="editUrl"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border-b-[3px] border-b-[#092f1a] bg-[#187642] px-4 py-2.5 text-sm font-semibold text-white transition-colors duration-200 hover:bg-[#126236] focus:outline-none focus:ring-2 focus:ring-[#187642]/20 focus:ring-offset-2"
            >
                <Pencil :size="16" />
                Editar pregunta
            </Link>
        </div>
    </div>
</template>
