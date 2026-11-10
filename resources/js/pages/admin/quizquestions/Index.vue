
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CircleOff,
    Eye,
    FilterX,
    HelpCircle,
    Plus,
    Sparkles,
    Upload,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import DataTable from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PageHeaderLink from '@/components/admin/PageHeaderLink.vue';
import TableActions from '@/components/admin/TableActions.vue';
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
        cancelButtonColor: '#84958a',
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
            :description="`${card.species?.common_name ?? 'Sin especie'} · Preguntas que se muestran en el duelo por esta tarjeta.`"
            section="Quiz de tarjeta"
            :icon="HelpCircle"
            :action-icon="Plus"
            action-label="Nueva pregunta"
            :action-href="admin.cards.quiz.create(card.id).url"
        >
            <template #actions>
                <PageHeaderLink
                    :href="admin.cards.quiz.generate(card.id).url"
                    label="Generar con IA"
                    :icon="Sparkles"
                />

                <PageHeaderLink
                    :href="admin.cards.quiz.import(card.id).url"
                    label="Importar JSON (avanzado)"
                    :icon="Upload"
                />

                <PageHeaderLink
                    :href="admin.cards.show(card.id).url"
                    label="Ver tarjeta"
                    :icon="Eye"
                />
            </template>
        </PageHeader>

        <DataTable
            v-model:search="search"
            :pagination="questions"
            :is-empty="questions.data.length === 0"
            :empty-colspan="5"
            search-placeholder="Buscar pregunta o respuesta..."
            :has-filters="hasFilters()"
            @search="applyFilters"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <select
                    v-model="isActive"
                    aria-label="Filtrar por estado"
                    class="h-[42px] w-full min-w-0 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 sm:w-auto sm:min-w-[170px]"
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
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>

                <span
                    v-if="isActive"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Estado: {{ isActive === '1' ? 'Activas' : 'Inactivas' }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th class="min-w-[240px] border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Pregunta
                    </th>

                    <th class="min-w-[220px] border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Respuestas
                    </th>

                    <th class="min-w-[160px] border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Correcta
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Estado
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="question in questions.data"
                    :key="question.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Pregunta -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]"
                            >
                                <HelpCircle :size="20" />
                            </span>

                            <div class="min-w-0">
                                <p class="max-w-xl text-sm font-semibold text-[#244334]">
                                    {{ question.question }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID {{ question.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Respuestas -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="(answer, answerIndex) in question.answers"
                                :key="answer.id"
                                class="inline-flex max-w-[180px] items-center gap-1.5 truncate rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="
                                    answer.is_correct
                                        ? 'border-[#c9e4d2] bg-[#dcefe2] text-[#176b3d]'
                                        : 'border-[#dce7df] bg-[#f2f7f4] text-[#435d4c]'
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

                    <!-- Correcta -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="getCorrectAnswer(question)"
                            class="inline-flex max-w-[200px] items-center gap-1.5 rounded-lg border border-[#dce7df] bg-white px-2.5 py-1.5 text-xs font-medium text-[#435d4c]"
                            :title="getCorrectAnswer(question)?.answer"
                        >
                            <CheckCircle2 :size="14" class="shrink-0 text-[#187744]" />

                            <span class="truncate">
                                {{ getCorrectAnswer(question)?.answer }}
                            </span>
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-600"
                        >
                            <CircleOff :size="14" />
                            Sin correcta
                        </span>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
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
                    </td>

                    <!-- Acciones -->
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
                <div class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]">
                        <FilterX v-if="hasFilters()" :size="27" />
                        <HelpCircle v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay preguntas'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos preguntas que coincidan con los filtros seleccionados.'
                                : 'Crea la primera pregunta del quiz para que los visitantes puedan iniciar un duelo por esta tarjeta.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
