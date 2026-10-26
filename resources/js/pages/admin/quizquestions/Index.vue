
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleOff,
    FilterX,
    HelpCircle,
    Plus,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

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
    thumbnail_image?: string | null;
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

const hasFilters = (): boolean => {
    return Boolean(search.value.trim() || isActive.value);
};

const applyFilters = (): void => {
    router.get(
        admin.cards.quiz.index(props.card.id).url,
        {
            search: search.value.trim() || undefined,
            is_active: isActive.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const submitSearch = (): void => {
    applyFilters();
};

const clearFilters = (): void => {
    search.value = '';
    isActive.value = '';

    applyFilters();
};

const deleteQuestion = async (question: Question): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar pregunta?',
        text: `Se eliminará la pregunta "${question.question}". También se eliminarán sus respuestas. Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#6B7280',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.cards.quiz.destroy({
            card: props.card.id,
            quizQuestion: question.id,
        }).url,
        {
            preserveScroll: true,
        },
    );
};

const answerLabel = (index: number): string => {
    return String.fromCharCode(65 + index);
};

const getAnswerCount = (question: Question): number => {
    return question.answers?.length ?? 0;
};

const getCorrectAnswer = (question: Question): Answer | undefined => {
    return question.answers?.find((answer) => answer.is_correct);
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
        ],
    },
});
</script>

<template>
    <Head :title="`Quiz: ${card.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            :title="card.name"
            :description="card.species?.common_name ?? 'Sin especie'"
            section="Quiz de tarjeta"
            :icon="HelpCircle"
            :action-icon="Plus"
            action-label="Nueva pregunta"
            :action-href="admin.cards.quiz.create(card.id).url"
        />

        <DataTable
            :pagination="questions"
            :is-empty="questions.data.length === 0"
            :empty-colspan="6"
            :search="search"
            search-placeholder="Buscar pregunta o respuesta..."
            :has-filters="hasFilters()"
            @update:search="search = $event"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <select
                    v-model="isActive"
                    aria-label="Filtrar por estado"
                    class="w-full rounded-lg border border-[#DCE7DF] bg-white px-3 py-2.5 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/15 sm:w-auto"
                    @change="applyFilters"
                >
                    <option value="">Todos los estados</option>
                    <option value="1">Activas</option>
                    <option value="0">Inactivas</option>
                </select>
            </template>

            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-3 py-1 text-xs font-medium text-[#42604C]"
                >
                    Búsqueda: {{ search }}
                </span>

                <span
                    v-if="isActive === '1'"
                    class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-medium text-green-700"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    Estado: Activas
                </span>

                <span
                    v-if="isActive === '0'"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-3 py-1 text-xs font-medium text-[#52685A]"
                >
                    <CircleOff class="h-3.5 w-3.5" />
                    Estado: Inactivas
                </span>

                <button
                    v-if="hasFilters()"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium text-[#187744] transition hover:bg-[#E4F2E8]"
                    @click="clearFilters"
                >
                    <FilterX class="h-3.5 w-3.5" />
                    Limpiar filtros
                </button>
            </template>

            <template #table-head>
                <tr>
                    <th class="min-w-[240px] px-5 py-4 font-semibold sm:px-6">
                        Pregunta
                    </th>

                    <th class="min-w-[220px] px-5 py-4 font-semibold sm:px-6">
                        Respuestas
                    </th>

                    <th class="min-w-[160px] px-5 py-4 font-semibold sm:px-6">
                        Correcta
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Orden
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Estado
                    </th>

                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="question in questions.data"
                    :key="question.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <HelpCircle class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="max-w-xl font-medium text-[#244334]">
                                    {{ question.question }}
                                </p>

                                <p class="mt-1 text-xs text-[#718477]">
                                    ID: {{ question.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(answer, answerIndex) in question.answers"
                                :key="answer.id"
                                class="inline-flex max-w-[180px] items-center gap-1.5 truncate rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="
                                    answer.is_correct
                                        ? 'border-green-200 bg-green-50 text-green-700'
                                        : 'border-[#DCE7DF] bg-[#F5F8F5] text-[#52685A]'
                                "
                                :title="answer.answer"
                            >
                                <span class="font-semibold">
                                    {{ answerLabel(answerIndex) }}
                                </span>

                                <span class="truncate">
                                    {{ answer.answer }}
                                </span>
                            </span>
                        </div>

                        <p
                            v-if="getAnswerCount(question) !== 3"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ getAnswerCount(question) }} respuestas
                        </p>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="getCorrectAnswer(question)"
                            class="inline-flex max-w-[200px] items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                            :title="getCorrectAnswer(question)?.answer"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5 shrink-0" />

                            <span class="truncate">
                                {{ getCorrectAnswer(question)?.answer }}
                            </span>
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-600"
                        >
                            <CircleOff class="h-3.5 w-3.5" />
                            Sin correcta
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ question.sort_order }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="question.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#718477]"
                        >
                            <CircleOff class="h-3.5 w-3.5" />
                            Inactiva
                        </span>
                    </td>

                    <TableActions
                        :view-url="admin.cards.quiz.show({
                            card: card.id,
                            quizQuestion: question.id,
                        }).url"
                        :edit-url="admin.cards.quiz.edit({
                            card: card.id,
                            quizQuestion: question.id,
                        }).url"
                        item-name="pregunta"
                        @delete="deleteQuestion(question)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <FilterX v-if="hasFilters()" class="h-6 w-6" />
                        <HelpCircle v-else class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay preguntas'
                        }}
                    </h3>

                    <p class="mt-1 max-w-md text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos preguntas que coincidan con los filtros seleccionados. Intenta cambiar los criterios de búsqueda.'
                                : 'Todavía no has registrado ninguna pregunta para este quiz. Comienza creando la primera.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
