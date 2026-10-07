<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import {
    ArrowLeft,
    CheckCircle2,
    HelpCircle,
    Save,
} from 'lucide-vue-next';

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
        {
            answer: '',
            is_correct: true,
        },
        {
            answer: '',
            is_correct: false,
        },
        {
            answer: '',
            is_correct: false,
        },
    ],
});

const selectCorrectAnswer = (index: number) => {
    form.answers.forEach((answer, answerIndex) => {
        answer.is_correct = answerIndex === index;
    });
};

const submit = () => {
    form.post(
        admin.cards.quiz.store(props.card.id).url,
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Nueva pregunta" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <Link
                        :href="admin.cards.quiz.index(card.id).url"
                        class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition hover:text-foreground"
                    >
                        <ArrowLeft :size="17" />
                        Regresar al quiz
                    </Link>

                    <div class="mb-2 flex items-center gap-2">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <HelpCircle :size="20" />
                        </div>

                        <span
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Quiz de card
                        </span>
                    </div>

                    <h1 class="text-2xl font-semibold tracking-tight">
                        Nueva pregunta
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ card.name }}

                        <span v-if="card.species?.common_name">
                            · {{ card.species.common_name }}
                        </span>

                        <span v-if="card.edition">
                            · {{ card.edition }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Formulario -->
        <form
            @submit.prevent="submit"
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <div class="p-6">
                <!-- Pregunta -->
                <section>
                    <div class="mb-5">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <HelpCircle :size="17" />
                            </div>

                            <h2 class="text-base font-semibold">
                                Pregunta
                            </h2>
                        </div>

                        <p
                            class="mt-1 ml-10 text-sm text-muted-foreground"
                        >
                            Escribe la pregunta que verá el usuario.
                        </p>
                    </div>

                    <div>
                        <label
                            for="question"
                            class="mb-2 block text-sm font-medium"
                        >
                            Pregunta
                            <span class="text-red-500">*</span>
                        </label>

                        <textarea
                            id="question"
                            v-model="form.question"
                            rows="4"
                            maxlength="5000"
                            placeholder="Ej. ¿Cuál es el hábitat natural de esta especie?"
                            class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-3 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
                            :class="{
                                'border-red-500 focus:border-red-500 focus:ring-red-500/20':
                                    form.errors.question,
                            }"
                        />

                        <p
                            v-if="form.errors.question"
                            class="mt-1.5 text-xs text-red-500"
                        >
                            {{ form.errors.question }}
                        </p>
                    </div>
                </section>

                <!-- Respuestas -->
                <section
                    class="mt-8 border-t border-sidebar-border/70 pt-8 dark:border-sidebar-border"
                >
                    <div class="mb-5">
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <CheckCircle2 :size="17" />
                            </div>

                            <h2 class="text-base font-semibold">
                                Respuestas
                            </h2>
                        </div>

                        <p
                            class="mt-1 ml-10 text-sm text-muted-foreground"
                        >
                            Agrega exactamente tres respuestas y selecciona
                            una como correcta.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(answer, index) in form.answers"
                            :key="index"
                            class="rounded-xl border p-4 transition"
                            :class="
                                answer.is_correct
                                    ? 'border-green-500/30 bg-green-500/5'
                                    : 'border-sidebar-border bg-background'
                            "
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start"
                            >
                                <!-- Número -->
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold"
                                    :class="
                                        answer.is_correct
                                            ? 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-500'
                                            : 'border-sidebar-border bg-muted/40 text-muted-foreground'
                                    "
                                >
                                    {{ String.fromCharCode(65 + index) }}
                                </div>

                                <!-- Campo -->
                                <div class="min-w-0 flex-1">
                                    <label
                                        :for="`answer-${index}`"
                                        class="mb-2 block text-sm font-medium"
                                    >
                                        Respuesta
                                        {{ String.fromCharCode(65 + index) }}

                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        :id="`answer-${index}`"
                                        v-model="answer.answer"
                                        type="text"
                                        maxlength="1000"
                                        :placeholder="`Escribe la respuesta ${String.fromCharCode(65 + index)}`"
                                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
                                        :class="{
                                            'border-red-500 focus:border-red-500 focus:ring-red-500/20':
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
                                        class="mt-1.5 text-xs text-red-500"
                                    >
                                        {{
                                            form.errors[
                                                `answers.${index}.answer`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <!-- Correcta -->
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border px-3 py-2.5 text-xs font-medium transition focus:outline-none focus:ring-2"
                                    :class="
                                        answer.is_correct
                                            ? 'border-green-500/30 bg-green-500/10 text-green-600 hover:bg-green-500/15 focus:ring-green-500/20 dark:text-green-500'
                                            : 'border-sidebar-border text-muted-foreground hover:bg-accent focus:ring-primary/20'
                                    "
                                    @click="selectCorrectAnswer(index)"
                                >
                                    <CheckCircle2 :size="16" />

                                    {{
                                        answer.is_correct
                                            ? 'Correcta'
                                            : 'Marcar correcta'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.answers"
                        class="mt-3 text-sm text-red-500"
                    >
                        {{ form.errors.answers }}
                    </p>
                </section>

                <!-- Configuración -->
                <section
                    class="mt-8 border-t border-sidebar-border/70 pt-8 dark:border-sidebar-border"
                >
                    <div class="mb-5">
                        <h2 class="text-base font-semibold">
                            Configuración
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Define el orden y estado de la pregunta.
                        </p>
                    </div>

                    <div
                        class="grid gap-5 md:grid-cols-2"
                    >
                        <!-- Orden -->
                        <div>
                            <label
                                for="sort_order"
                                class="mb-2 block text-sm font-medium"
                            >
                                Orden
                            </label>

                            <input
                                id="sort_order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                :class="{
                                    'border-red-500 focus:border-red-500 focus:ring-red-500/20':
                                        form.errors.sort_order,
                                }"
                            />

                            <p
                                v-if="form.errors.sort_order"
                                class="mt-1.5 text-xs text-red-500"
                            >
                                {{ form.errors.sort_order }}
                            </p>
                        </div>

                        <!-- Estado -->
                        <div>
                            <span
                                class="mb-2 block text-sm font-medium"
                            >
                                Estado
                            </span>

                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border p-3 transition hover:bg-accent"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-sidebar-border text-primary focus:ring-primary/20"
                                />

                                <div>
                                    <div class="text-sm font-medium">
                                        Pregunta activa
                                    </div>

                                    <div
                                        class="text-xs text-muted-foreground"
                                    >
                                        Disponible para los usuarios.
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 bg-muted/20 p-4 sm:flex-row sm:justify-end dark:border-sidebar-border"
            >
                <Link
                    :href="admin.cards.quiz.index(card.id).url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                >
                    Cancelar
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <Save :size="18" />

                    {{
                        form.processing
                            ? 'Guardando...'
                            : 'Guardar pregunta'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>