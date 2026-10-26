<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    HelpCircle,
    Save,
    ToggleLeft,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

interface AnswerForm {
    id: number;
    answer: string;
    is_correct: boolean;
}

const props = defineProps<{
    card: Card;
    question: Question;
}>();

const form = useForm<{
    question: string;
    is_active: boolean;
    answers: AnswerForm[];
}>({
    question: props.question.question,
    is_active: props.question.is_active,
    answers: props.question.answers
        .slice()
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((answer) => ({
            id: answer.id,
            answer: answer.answer,
            is_correct: answer.is_correct,
        })),
});

const selectCorrectAnswer = (index: number) => {
    form.answers.forEach((answer, answerIndex) => {
        answer.is_correct = answerIndex === index;
    });
};

const submit = () => {
    form.put(
        admin.cards.quiz.update({
            card: props.card.id,
            quizQuestion: props.question.id,
        }).url,
        {
            preserveScroll: true,
        },
    );
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
                title: 'Preguntas del quiz',
                href: admin.cards.index(),
            },
            {
                title: 'Editar pregunta',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar pregunta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar pregunta"
            description="Modifica la pregunta y sus respuestas para el quiz de la tarjeta."
            section="Colección"
            :icon="HelpCircle"
            :action-icon="ArrowLeft"
            action-label="Volver al quiz"
            :action-href="admin.cards.quiz.index(card.id).url"
        />

        <!-- Información de la tarjeta -->
        <div class="form-info">
            <div class="form-info-icon">
                <CheckCircle2 class="h-5 w-5" />
            </div>

            <p class="form-info-text">
                <span class="font-semibold">{{ card.name }}</span>

                <span v-if="card.species?.common_name">
                    · {{ card.species.common_name }}
                </span>

                — Estás editando una pregunta asociada a esta tarjeta.
            </p>
        </div>

        <form class="form-container" @submit.prevent="submit">
            <!-- Pregunta -->
            <AdminCard
                title="Pregunta"
                description="Modifica el texto que verá el usuario al responder el quiz."
                :icon="HelpCircle"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="question" class="form-label">
                            Texto de la pregunta
                            <span class="form-required">*</span>
                        </label>

                        <textarea
                            id="question"
                            v-model="form.question"
                            rows="4"
                            maxlength="5000"
                            required
                            placeholder="Ej. ¿Cuál es el hábitat natural de esta especie?"
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.question,
                            }"
                        />

                        <p v-if="form.errors.question" class="form-error">
                            {{ form.errors.question }}
                        </p>

                        <p v-else class="form-help">
                            Revisa que la pregunta sea clara y esté relacionada
                            con la especie de la tarjeta.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Respuestas -->
            <AdminCard
                title="Respuestas"
                description="Modifica las respuestas existentes y selecciona una como correcta."
                :icon="CheckCircle2"
            >
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(answer, index) in form.answers"
                        :key="answer.id"
                        class="rounded-xl border p-4 transition-colors"
                        :class="
                            answer.is_correct
                                ? 'border-[#BFCFC6] bg-[#EAF5EE]'
                                : 'border-[#DCE7DF] bg-white'
                        "
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            <!-- Identificador -->
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold"
                                :class="
                                    answer.is_correct
                                        ? 'border-[#BFCFC6] bg-[#D8EDE0] text-[#187744]'
                                        : 'border-[#DCE7DF] bg-[#F2F7F4] text-[#718477]'
                                "
                            >
                                {{ String.fromCharCode(65 + index) }}
                            </div>

                            <!-- Campo de respuesta -->
                            <div class="form-field min-w-0 flex-1">
                                <label
                                    :for="`answer-${index}`"
                                    class="form-label"
                                >
                                    Respuesta
                                    {{ String.fromCharCode(65 + index) }}
                                    <span class="form-required">*</span>
                                </label>

                                <input
                                    :id="`answer-${index}`"
                                    v-model="answer.answer"
                                    type="text"
                                    maxlength="1000"
                                    required
                                    :placeholder="`Escribe la respuesta ${String.fromCharCode(65 + index)}`"
                                    class="form-input"
                                    :class="{
                                        'form-input-error':
                                            form.errors[
                                                `answers.${index}.answer`
                                            ],
                                    }"
                                />

                                <p
                                    v-if="
                                        form.errors[
                                            `answers.${index}.answer`
                                        ]
                                    "
                                    class="form-error"
                                >
                                    {{
                                        form.errors[
                                            `answers.${index}.answer`
                                        ]
                                    }}
                                </p>
                            </div>

                            <!-- Respuesta correcta -->
                            <button
                                type="button"
                                class="form-button shrink-0"
                                :class="
                                    answer.is_correct
                                        ? 'border border-[#BFCFC6] bg-[#D8EDE0] text-[#187744]'
                                        : 'form-button-secondary'
                                "
                                @click="selectCorrectAnswer(index)"
                            >
                                <CheckCircle2 class="h-4 w-4" />

                                {{
                                    answer.is_correct
                                        ? 'Correcta'
                                        : 'Marcar correcta'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="form.errors.answers" class="form-error mt-3">
                    {{ form.errors.answers }}
                </p>

                <p class="form-help mt-3">
                    Solo una respuesta puede marcarse como correcta.
                </p>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define el estado de la pregunta."
                :icon="ToggleLeft"
            >
                <div class="form-grid">
                    <!-- Estado -->
                    <div class="form-field form-field-full">
                        <ActiveSwitch
                            v-model="form.is_active"
                            label="Pregunta activa"
                            description="Permite que esta pregunta esté disponible para los usuarios."
                            :error="form.errors.is_active"
                        />
                    </div>
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
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Guardando..."
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>