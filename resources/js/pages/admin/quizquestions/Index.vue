<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import {
    Plus,
    Search,
    Eye,
    Pencil,
    Trash2,
    HelpCircle,
    FilterX,
    CheckCircle2,
    CircleOff,
} from 'lucide-vue-next';

import Swal from 'sweetalert2';

import { ref } from 'vue';

import admin from '@/routes/admin';

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id?: number;
    name: string;
    rarity?: string;
    edition?: string | null;
    description?: string | null;
    card_image?: string | null;
    species?: Species | null;
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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface QuestionsPagination {
    data: Question[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    card: Card;

    questions: QuestionsPagination;

    filters: {
        search?: string;
        is_active?: string | boolean | null;
    };
}>();

const search = ref(props.filters?.search ?? '');

const isActive = ref(
    props.filters?.is_active === true ||
    props.filters?.is_active === '1' ||
    props.filters?.is_active === 'true'
        ? '1'
        : props.filters?.is_active === false ||
            props.filters?.is_active === '0' ||
            props.filters?.is_active === 'false'
          ? '0'
          : '',
);

const submitSearch = () => {
    router.get(
        admin.cards.quiz.index(props.card.id).url,
        {
            search: search.value || undefined,
            is_active: isActive.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const applyFilters = () => {
    router.get(
        admin.cards.quiz.index(props.card.id).url,
        {
            search: search.value || undefined,
            is_active: isActive.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';
    isActive.value = '';

    applyFilters();
};

const hasFilters = () => {
    return Boolean(
        search.value ||
        isActive.value,
    );
};

const deleteQuestion = (question: Question) => {
    Swal.fire({
        title: '¿Eliminar pregunta?',
        text: `Se eliminará la pregunta "${question.question}". También se eliminarán sus respuestas. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.cards.quiz.destroy({
                    card: props.card.id,
                    quizQuestion: question.id,
                }).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const answerLabel = (index: number) => {
    return String.fromCharCode(65 + index);
};

const getAnswerCount = (question: Question) => {
    return question.answers?.length ?? 0;
};

const getCorrectAnswer = (question: Question) => {
    return question.answers?.find(
        (answer) => answer.is_correct,
    );
};
</script>

<template>
    <Head title="Quiz de card" />

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
                        {{ card.name }}
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ card.species?.common_name ?? 'Sin especie' }}

                        <span v-if="card.edition">
                            · {{ card.edition }}
                        </span>
                    </p>
                </div>

                <Link
                    :href="admin.cards.quiz.create(card.id).url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/30"
                >
                    <Plus :size="18" />
                    Nueva pregunta
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador y filtros -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <form
                        @submit.prevent="submitSearch"
                        class="flex w-full flex-col gap-2 lg:max-w-4xl lg:flex-row"
                    >
                        <!-- Buscar -->
                        <div class="relative min-w-0 flex-1">
                            <Search
                                :size="18"
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                            />

                            <input
                                v-model="search"
                                type="search"
                                placeholder="Buscar pregunta o respuesta..."
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <!-- Estado -->
                        <select
                            v-model="isActive"
                            class="rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            @change="applyFilters"
                        >
                            <option value="">
                                Todos los estados
                            </option>

                            <option value="1">
                                Activas
                            </option>

                            <option value="0">
                                Inactivas
                            </option>
                        </select>

                        <!-- Buscar -->
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <Search :size="17" />
                            Buscar
                        </button>
                    </form>

                    <!-- Total -->
                    <div class="text-sm text-muted-foreground">
                        {{ questions.total }}
                        {{
                            questions.total === 1
                                ? 'donación'
                                : 'donaciones'
                        }}
                    </div>
                </div>

                <!-- Filtros activos -->
                <div
                    v-if="hasFilters()"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span class="text-xs text-muted-foreground">
                        Filtros activos:
                    </span>

                    <span
                        v-if="search"
                        class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        Búsqueda: "{{ search }}"
                    </span>

                    <span
                        v-if="isActive === '1'"
                        class="rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs text-green-600 dark:text-green-500"
                    >
                        Estado: Activas
                    </span>

                    <span
                        v-if="isActive === '0'"
                        class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs"
                    >
                        Estado: Inactivas
                    </span>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-primary transition hover:bg-primary/10"
                        @click="clearFilters"
                    >
                        <FilterX :size="14" />
                        Limpiar
                    </button>
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Pregunta
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Respuestas
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Correcta
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Orden
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 text-right font-semibold"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="question in questions.data"
                            :key="question.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Pregunta -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <HelpCircle :size="19" />
                                    </div>

                                    <div class="min-w-0">
                                        <div
                                            class="max-w-xl font-medium"
                                        >
                                            {{ question.question }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ question.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Respuestas -->
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="(
                                            answer, answerIndex
                                        ) in question.answers"
                                        :key="answer.id"
                                        class="inline-flex max-w-[180px] items-center gap-1.5 truncate rounded-full border px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            answer.is_correct
                                                ? 'border-green-500/30 bg-green-500/5 text-green-600 dark:text-green-500'
                                                : 'border-sidebar-border bg-muted/40 text-muted-foreground'
                                        "
                                        :title="answer.answer"
                                    >
                                        <span
                                            class="font-semibold"
                                        >
                                            {{ answerLabel(answerIndex) }}
                                        </span>

                                        <span class="truncate">
                                            {{ answer.answer }}
                                        </span>
                                    </span>
                                </div>

                                <div
                                    v-if="getAnswerCount(question) !== 3"
                                    class="mt-1 text-xs text-red-500"
                                >
                                    {{ getAnswerCount(question) }}
                                    respuestas
                                </div>
                            </td>

                            <!-- Correcta -->
                            <td class="px-6 py-4">
                                <div
                                    v-if="getCorrectAnswer(question)"
                                    class="max-w-xs"
                                >
                                    <span
                                        class="inline-flex max-w-full items-center gap-1.5 truncate rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-500"
                                        :title="
                                            getCorrectAnswer(question)?.answer
                                        "
                                    >
                                        <CheckCircle2 :size="14" />

                                        <span class="truncate">
                                            {{
                                                getCorrectAnswer(question)
                                                    ?.answer
                                            }}
                                        </span>
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/5 px-2.5 py-1 text-xs font-medium text-red-500"
                                >
                                    <CircleOff :size="14" />
                                    Sin correcta
                                </span>
                            </td>

                            <!-- Orden -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ question.sort_order }}
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
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
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-1.5"
                                >
                                    <Link
                                        :href="
                                            admin.cards.quiz.show({
                                                card: card.id,
                                                quizQuestion: question.id,
                                            }).url
                                        "
                                        title="Ver pregunta"
                                        aria-label="Ver pregunta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        <Eye :size="16" />
                                        <span>Ver</span>
                                    </Link>

                                    <Link
                                        :href="
                                            admin.cards.quiz.edit({
                                                card: card.id,
                                                quizQuestion: question.id,
                                            }).url
                                        "
                                        title="Editar pregunta"
                                        aria-label="Editar pregunta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    >
                                        <Pencil :size="16" />
                                        <span>Editar</span>
                                    </Link>

                                    <button
                                        type="button"
                                        title="Eliminar pregunta"
                                        aria-label="Eliminar pregunta"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10 focus:outline-none focus:ring-2 focus:ring-red-500/20"
                                        @click="deleteQuestion(question)"
                                    >
                                        <Trash2 :size="16" />
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Estado vacío -->
                        <tr v-if="questions.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-16"
                            >
                                <div
                                    class="mx-auto flex max-w-md flex-col items-center justify-center text-center"
                                >
                                    <!-- Icono -->
                                    <div
                                        class="mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                                    >
                                        <FilterX
                                            v-if="hasFilters()"
                                            :size="30"
                                        />

                                        <HelpCircle
                                            v-else
                                            :size="30"
                                        />
                                    </div>

                                    <!-- Título -->
                                    <h3 class="text-base font-semibold">
                                        {{
                                            hasFilters()
                                                ? 'No se encontraron resultados'
                                                : 'Aún no hay preguntas'
                                        }}
                                    </h3>

                                    <!-- Descripción -->
                                    <p
                                        class="mt-1 max-w-sm text-sm leading-6 text-muted-foreground"
                                    >
                                        {{
                                            hasFilters()
                                                ? 'No encontramos preguntas que coincidan con los filtros seleccionados. Intenta cambiar los criterios de búsqueda.'
                                                : 'Todavía no has registrado ninguna pregunta para este quiz. Comienza creando la primera.'
                                        }}
                                    </p>                                    
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="questions.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in questions.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-2 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border hover:bg-accent'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="rounded-lg border border-sidebar-border px-3 py-2 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>