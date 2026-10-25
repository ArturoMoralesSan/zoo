<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
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

interface AnswerForm {
    answer: string;
    is_correct: boolean;
}

const props = defineProps<{
    card: Card;
}>();

const form = useForm<{
    question: string;
    is_active: boolean;
    sort_order: number;
    answers: AnswerForm[];
}>({
    question: '',
    is_active: true,
    sort_order: 0,
    answers: [
        { answer: '', is_correct: true },
        { answer: '', is_correct: false },
        { answer: '', is_correct: false },
    ],
});

const selectCorrectAnswer = (index: number) => {
    form.answers.forEach((answer, answerIndex) => {
        answer.is_correct = answerIndex === index;
    });
};

const submit = () => {
    form.post(admin.cards.quiz.store(props.card.id).url, {
        preserveScroll: true,
    });
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
                title: 'Nueva pregunta',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nueva pregunta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nueva pregunta"
            description="Crea una pregunta y define sus respuestas para el quiz de la tarjeta."
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

                — La pregunta se asociará a esta tarjeta.
            </p>
        </div>

        <form class="form-container" @submit.prevent="submit">
            <!-- Pregunta -->
            <AdminCard
                title="Pregunta"
                description="Escribe la pregunta que verá el usuario al responder el quiz."
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
                            Escribe una pregunta clara y relacionada con la
                            especie de la tarjeta.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Respuestas -->
            <AdminCard
                title="Respuestas"
                description="Agrega exactamente tres respuestas y selecciona una como correcta."
                :icon="CheckCircle2"
            >
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(answer, index) in form.answers"
                        :key="index"
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

                            <!-- Seleccionar respuesta correcta -->
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
                description="Define el orden y el estado de la pregunta."
                :icon="ToggleLeft"
            >
                <div class="form-grid">
                    <!-- Orden -->
                    <div class="form-field">
                        <label for="sort_order" class="form-label">
                            Orden
                        </label>

                        <input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            step="1"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.sort_order,
                            }"
                        />

                        <p v-if="form.errors.sort_order" class="form-error">
                            {{ form.errors.sort_order }}
                        </p>

                        <p v-else class="form-help">
                            Determina la posición de la pregunta dentro del
                            quiz.
                        </p>
                    </div>

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

            <!-- Información adicional -->
            <div class="form-info">
                <div class="form-info-icon">
                    <Check class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    Antes de guardar, revisa el texto de la pregunta, completa
                    las tres respuestas y confirma cuál es la correcta.
                </p>
            </div>

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
                    Guardar pregunta
                </AdminButton>
            </div>
        </form>
    </div>
</template>