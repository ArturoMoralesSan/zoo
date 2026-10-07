<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import {
    ArrowLeft,
    CheckCircle2,
    CircleOff,
    Edit,
    HelpCircle,
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
    sort_order: number;
    answers: Answer[];
}

const props = defineProps<{
    card: Card;
    question: Question;
}>();

const answerLabel = (index: number) => {
    return String.fromCharCode(65 + index);
};
</script>

<template>
    <Head title="Ver pregunta" />

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
                        Ver pregunta
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

                <Link
                    :href="
                        admin.cards.quiz.edit({
                            card: card.id,
                            quizQuestion: question.id,
                        }).url
                    "
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                    <Edit :size="18" />
                    Editar pregunta
                </Link>
            </div>
        </div>

        <!-- Contenido -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Pregunta -->
            <section
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border lg:col-span-2"
            >
                <div
                    class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
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
                </div>

                <div class="p-6">
                    <p
                        class="text-lg font-medium leading-8 text-foreground"
                    >
                        {{ question.question }}
                    </p>
                </div>
            </section>

            <!-- Información -->
            <section
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <h2 class="text-base font-semibold">
                        Información
                    </h2>
                </div>

                <div class="space-y-5 p-6">
                    <!-- Estado -->
                    <div>
                        <span
                            class="mb-2 block text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Estado
                        </span>

                        <span
                            v-if="question.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-500"
                        >
                            <CheckCircle2 :size="14" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                        >
                            <CircleOff :size="14" />
                            Inactiva
                        </span>
                    </div>

                    <!-- Orden -->
                    <div>
                        <span
                            class="mb-2 block text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Orden
                        </span>

                        <span
                            class="inline-flex rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium"
                        >
                            {{ question.sort_order }}
                        </span>
                    </div>

                    <!-- ID -->
                    <div>
                        <span
                            class="mb-2 block text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            ID de pregunta
                        </span>

                        <span class="text-sm font-medium">
                            #{{ question.id }}
                        </span>
                    </div>

                    <!-- Card -->
                    <div>
                        <span
                            class="mb-2 block text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Card
                        </span>

                        <span class="text-sm font-medium">
                            {{ card.name }}
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Respuestas -->
        <section
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <div
                class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-base font-semibold">
                            Respuestas
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            La respuesta marcada corresponde a la opción
                            correcta.
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium"
                    >
                        {{ question.answers.length }} respuestas
                    </span>
                </div>
            </div>

            <div class="p-6">
                <div class="grid gap-3">
                    <div
                        v-for="(answer, index) in question.answers"
                        :key="answer.id"
                        class="rounded-xl border p-4 transition"
                        :class="
                            answer.is_correct
                                ? 'border-green-500/30 bg-green-500/5'
                                : 'border-sidebar-border bg-background'
                        "
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center"
                        >
                            <!-- Letra -->
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold"
                                :class="
                                    answer.is_correct
                                        ? 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-500'
                                        : 'border-sidebar-border bg-muted/40 text-muted-foreground'
                                "
                            >
                                {{ answerLabel(index) }}
                            </div>

                            <!-- Respuesta -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="text-sm font-medium leading-6"
                                >
                                    {{ answer.answer }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    ID: {{ answer.id }}
                                </div>
                            </div>

                            <!-- Correcta -->
                            <div class="shrink-0">
                                <span
                                    v-if="answer.is_correct"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/5 px-3 py-1.5 text-xs font-medium text-green-600 dark:text-green-500"
                                >
                                    <CheckCircle2 :size="15" />
                                    Correcta
                                </span>

                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    Incorrecta
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-2 rounded-xl border border-sidebar-border/70 bg-background p-4 sm:flex-row sm:justify-end dark:border-sidebar-border"
        >
            <Link
                :href="admin.cards.quiz.index(card.id).url"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
            >
                <ArrowLeft :size="17" />
                Regresar al quiz
            </Link>

            <Link
                :href="
                    admin.cards.quiz.edit({
                        card: card.id,
                        quizQuestion: question.id,
                    }).url
                "
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30"
            >
                <Edit :size="17" />
                Editar pregunta
            </Link>
        </div>
    </div>
</template>