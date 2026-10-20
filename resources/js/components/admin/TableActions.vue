
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    HelpCircle,
    Eye,
    Pencil,
    Trash2,
} from 'lucide-vue-next';

interface ActionLink {
    url: string;
    title?: string;
    label?: string;
}

withDefaults(defineProps<{
    viewUrl?: string;
    editUrl?: string;
    questionsUrl?: string;
    deleteTitle?: string;
    deleteLabel?: string;
    itemName?: string;
    showView?: boolean;
    showEdit?: boolean;
    showQuestions?: boolean;
    showDelete?: boolean;
    loading?: boolean;
}>(), {
    showView: true,
    showEdit: true,
    showQuestions: false,
    showDelete: true,
    deleteTitle: '',
    deleteLabel: 'Eliminar',
    itemName: 'registro',
    loading: false,
});

const emit = defineEmits<{
    delete: [];
}>();
</script>

<template>
    <td class="px-5 py-4 sm:px-6">
        <div
            class="flex min-w-[220px] items-center justify-end gap-1.5"
        >
            <!-- Administrar preguntas -->
            <Link
                v-if="showQuestions && questionsUrl"
                :href="questionsUrl"
                title="Administrar preguntas"
                aria-label="Administrar preguntas"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-[#52685a] transition hover:bg-[#e4f2e8] hover:text-[#187744] focus:outline-none focus:ring-2 focus:ring-[#187744]/15"
            >
                <HelpCircle :size="16" />
                Preguntas
            </Link>

            <!-- Ver -->
            <Link
                v-if="showView && viewUrl"
                :href="viewUrl"
                :title="`Ver ${itemName}`"
                :aria-label="`Ver ${itemName}`"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-[#187744] transition hover:bg-[#e4f2e8] focus:outline-none focus:ring-2 focus:ring-[#187744]/15"
            >
                <Eye :size="17" />
            </Link>

            <!-- Editar -->
            <Link
                v-if="showEdit && editUrl"
                :href="editUrl"
                :title="`Editar ${itemName}`"
                :aria-label="`Editar ${itemName}`"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-[#b9cfc0] bg-white px-3 text-xs font-semibold text-[#244334] transition hover:bg-[#edf4ef] focus:outline-none focus:ring-2 focus:ring-[#187744]/15"
            >
                <Pencil :size="15" />
                Editar
            </Link>

            <!-- Eliminar -->
            <button
                v-if="showDelete"
                type="button"
                :title="`Eliminar ${itemName}`"
                :aria-label="`Eliminar ${itemName}`"
                :disabled="loading"
                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-[#dfb9b9] bg-white px-3 text-xs font-semibold text-[#a33d3d] transition hover:bg-[#fff2f2] focus:outline-none focus:ring-2 focus:ring-red-500/15 disabled:cursor-not-allowed disabled:opacity-50"
                @click="emit('delete')"
            >
                <Trash2 :size="15" />
                {{ deleteLabel }}
            </button>
        </div>
    </td>
</template>
