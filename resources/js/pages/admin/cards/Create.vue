<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    CheckCircle2,
    ChevronDown,
    FileBox,
    FileImage,
    HelpCircle,
    Info,
    Layers3,
    Plus,
    Save,
    Tag,
    Trash2,
    Upload,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

import { useObjectUrl } from '@/composables/useObjectUrl';
import { rarityOptions } from '@/lib/cardRarity';
import type { CardRaritySetting, DuelSettingsSource } from '@/lib/cardRarity';
import { modelExtension } from '@/lib/modelPreview';
import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import DuelSettingsFields from '@/components/admin/cards/DuelSettingsFields.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';

interface Species {
    id: number;
    species_category_id?: number;
    category?: { id: number; name: string };
    common_name: string;
    scientific_name: string;
}

interface AnswerForm {
    id?: number;
    answer: string;
    is_correct: boolean;
    sort_order?: number;
}

interface QuestionForm {
    id?: number;
    question: string;
    is_active: boolean;
    sort_order: number;
    answers: AnswerForm[];
}

const props = defineProps<{
    species: Species[];
    raritySettings: CardRaritySetting[];
}>();

const form = useForm({
    species_id: '',
    name: '',
    rarity: 'comun',
    edition: '',
    description: '',
    card_image: null as File | null,
    model_name: '',
    model_file: null as File | null,
    model_url: '',
    model_format: '',
    model_description: '',
    is_active: true,
    sort_order: 0,
    astucia: 50,
    rapidez: 50,
    fuerza: 50,
    duel_settings_source: 'rarity' as DuelSettingsSource,
    questions_per_duel: null as number | null,
    retries: null as number | null,
    cooldown_minutes: null as number | null,
    questions: [] as QuestionForm[],
});

const addQuestion = () => {
    form.questions.push({
        question: '',
        is_active: true,
        sort_order: form.questions.length,
        answers: [
            { answer: '', is_correct: true, sort_order: 0 },
            { answer: '', is_correct: false, sort_order: 1 },
            { answer: '', is_correct: false, sort_order: 2 },
        ],
    });
};

const removeQuestion = (qIndex: number) => {
    form.questions.splice(qIndex, 1);
    form.questions.forEach((q, idx) => {
        q.sort_order = idx;
    });
};

const addAnswer = (qIndex: number) => {
    const question = form.questions[qIndex];
    if (!question) return;
    question.answers.push({
        answer: '',
        is_correct: question.answers.length === 0,
        sort_order: question.answers.length,
    });
};

const removeAnswer = (qIndex: number, aIndex: number) => {
    const question = form.questions[qIndex];
    if (!question || question.answers.length <= 2) return;
    const wasCorrect = question.answers[aIndex].is_correct;
    question.answers.splice(aIndex, 1);
    question.answers.forEach((ans, idx) => {
        ans.sort_order = idx;
    });
    if (wasCorrect && question.answers.length > 0) {
        question.answers[0].is_correct = true;
    }
};

const selectCorrectAnswer = (qIndex: number, aIndex: number) => {
    const question = form.questions[qIndex];
    if (!question) return;
    question.answers.forEach((ans, idx) => {
        ans.is_correct = idx === aIndex;
    });
};

const submit = () => {
    form.post(admin.cards.store().url, {
        forceFormData: true,
    });
};

const handleCardImage = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.card_image = target.files?.[0] ?? null;
};

const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.model_file = target.files?.[0] ?? null;
};

/*
 * Vista previa: el archivo seleccionado tiene prioridad sobre la URL.
 */
const modelFilePreviewUrl = useObjectUrl(() => form.model_file);

const modelPreviewSource = computed(
    () => modelFilePreviewUrl.value ?? (form.model_url.trim() || null),
);

const modelPreviewExtension = computed(() =>
    form.model_file
        ? modelExtension(form.model_file.name)
        : modelExtension(form.model_url),
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
                href: admin.cards.index,
            },
            {
                title: 'Agregar tarjeta',
                href: admin.cards.create,
            },
        ],
    },
});
</script>

<template>
    <Head title="Nueva tarjeta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado -->
        <PageHeader
            title="Nueva tarjeta"
            description="Crea una nueva tarjeta coleccionable para una especie."
            section="Colección"
            :icon="Tag"
            :action-icon="ArrowLeft"
            action-label="Volver a tarjetas"
            :action-href="admin.cards.index().url"
        />

        <form
            id="card-form"
            class="space-y-5"
            @submit.prevent="submit"
        >
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Datos principales e identificación de la tarjeta."
                :icon="Info"
            >
                <div class="space-y-5">
                    <!-- Especie -->
                    <div class="form-field">
                        <label for="species_id" class="form-label">
                            Especie
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="species_id"
                                v-model="form.species_id"
                                class="form-select appearance-none"
                                :class="{
                                    'form-input-error': form.errors.species_id,
                                }"
                            >
                                <option value="">
                                    Selecciona una especie
                                </option>

                                <option
                                    v-for="item in props.species"
                                    :key="item.id"
                                    :value="String(item.id)"
                                >
                                    {{ item.common_name }} — {{ item.scientific_name }}
                                    <template v-if="item.category">
                                        ({{ item.category.name }})
                                    </template>
                                </option>
                            </select>

                            <ChevronDown
                                :size="16"
                                class="form-select-icon"
                            />
                        </div>

                        <p
                            v-if="form.errors.species_id"
                            class="form-error"
                        >
                            {{ form.errors.species_id }}
                        </p>
                    </div>

                    <div class="form-grid">
                        <!-- Nombre -->
                        <div class="form-field">
                            <label for="name" class="form-label">
                                Nombre de la tarjeta
                                <span class="text-red-500">*</span>
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Ej. Jaguar del Norte"
                                class="form-input"
                                :class="{
                                    'form-input-error': form.errors.name,
                                }"
                            />

                            <p v-if="form.errors.name" class="form-error">
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Rareza -->
                        <div class="form-field">
                            <label for="rarity" class="form-label">
                                Rareza
                                <span class="text-red-500">*</span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="rarity"
                                    v-model="form.rarity"
                                    class="form-select appearance-none"
                                    :class="{
                                        'form-input-error': form.errors.rarity,
                                    }"
                                >
                                    <option
                                        v-for="option in rarityOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <ChevronDown
                                    :size="16"
                                    class="form-select-icon"
                                />
                            </div>

                            <p v-if="form.errors.rarity" class="form-error">
                                {{ form.errors.rarity }}
                            </p>
                        </div>

                        <!-- Edición -->
                        <div class="form-field">
                            <label for="edition" class="form-label">
                                Edición
                            </label>

                            <input
                                id="edition"
                                v-model="form.edition"
                                type="text"
                                placeholder="Ej. Colección Sahuatoba 2026"
                                class="form-input"
                                :class="{
                                    'form-input-error': form.errors.edition,
                                }"
                            />

                            <p class="form-help">
                                Opcional. Identifica la colección a la que pertenece.
                            </p>

                            <p v-if="form.errors.edition" class="form-error">
                                {{ form.errors.edition }}
                            </p>
                        </div>

                        <!-- Orden -->
                        <div class="form-field">
                            <label for="sort_order" class="form-label">
                                Orden de aparición
                            </label>

                            <input
                                id="sort_order"
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="form-input"
                                :class="{
                                    'form-input-error': form.errors.sort_order,
                                }"
                            />

                            <p class="form-help">
                                Un número menor aparece primero.
                            </p>

                            <p
                                v-if="form.errors.sort_order"
                                class="form-error"
                            >
                                {{ form.errors.sort_order }}
                            </p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe las características de esta tarjeta..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.description,
                            }"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Estadísticas de la tarjeta (Astucia, Rapidez, Fuerza: 1-100) -->
            <AdminCard
                title="Estadísticas de la tarjeta"
                description="Asigna un valor numérico de 1 a 100 para cada atributo de la tarjeta."
                :icon="Zap"
            >
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <!-- Astucia -->
                    <div class="form-field rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                        <label for="astucia" class="form-label flex items-center justify-between">
                            <span>Astucia</span>
                            <span class="rounded bg-[#EAF5EE] px-2 py-0.5 text-xs font-bold text-[#187744]">
                                {{ form.astucia }}/100
                            </span>
                        </label>

                        <input
                            id="astucia"
                            v-model.number="form.astucia"
                            type="number"
                            min="1"
                            max="100"
                            required
                            class="form-input text-center text-lg font-semibold"
                            :class="{
                                'form-input-error': form.errors.astucia,
                            }"
                        />

                        <p class="form-help text-center">
                            Nivel de ingenio y astucia (1 - 100).
                        </p>

                        <p v-if="form.errors.astucia" class="form-error text-center">
                            {{ form.errors.astucia }}
                        </p>
                    </div>

                    <!-- Rapidez -->
                    <div class="form-field rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                        <label for="rapidez" class="form-label flex items-center justify-between">
                            <span>Rapidez</span>
                            <span class="rounded bg-[#EAF5EE] px-2 py-0.5 text-xs font-bold text-[#187744]">
                                {{ form.rapidez }}/100
                            </span>
                        </label>

                        <input
                            id="rapidez"
                            v-model.number="form.rapidez"
                            type="number"
                            min="1"
                            max="100"
                            required
                            class="form-input text-center text-lg font-semibold"
                            :class="{
                                'form-input-error': form.errors.rapidez,
                            }"
                        />

                        <p class="form-help text-center">
                            Velocidad y agilidad (1 - 100).
                        </p>

                        <p v-if="form.errors.rapidez" class="form-error text-center">
                            {{ form.errors.rapidez }}
                        </p>
                    </div>

                    <!-- Fuerza -->
                    <div class="form-field rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                        <label for="fuerza" class="form-label flex items-center justify-between">
                            <span>Fuerza</span>
                            <span class="rounded bg-[#EAF5EE] px-2 py-0.5 text-xs font-bold text-[#187744]">
                                {{ form.fuerza }}/100
                            </span>
                        </label>

                        <input
                            id="fuerza"
                            v-model.number="form.fuerza"
                            type="number"
                            min="1"
                            max="100"
                            required
                            class="form-input text-center text-lg font-semibold"
                            :class="{
                                'form-input-error': form.errors.fuerza,
                            }"
                        />

                        <p class="form-help text-center">
                            Potencia y resistencia física (1 - 100).
                        </p>

                        <p v-if="form.errors.fuerza" class="form-error text-center">
                            {{ form.errors.fuerza }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración de duelos y reintentos -->
            <DuelSettingsFields
                v-model:source="form.duel_settings_source"
                v-model:questions-per-duel="form.questions_per_duel"
                v-model:retries="form.retries"
                v-model:cooldown-minutes="form.cooldown_minutes"
                :rarity="form.rarity"
                :rarity-settings="raritySettings"
                :errors="form.errors"
            />

            <!-- Preguntas y opciones del quiz -->
            <AdminCard
                title="Preguntas del quiz"
                description="Agrega y personaliza las preguntas y opciones de respuesta para esta tarjeta."
                :icon="HelpCircle"
            >
                <div class="space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E1EBE4] pb-4">
                        <p class="text-sm text-[#718477]">
                            Total de preguntas agregadas: <strong>{{ form.questions.length }}</strong>
                        </p>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#187744] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#126238]"
                            @click="addQuestion"
                        >
                            <Plus :size="16" />
                            Agregar pregunta
                        </button>
                    </div>

                    <!-- Estado vacío -->
                    <div
                        v-if="form.questions.length === 0"
                        class="rounded-xl border border-dashed border-[#B8D2C1] bg-[#F7FAF8] p-8 text-center"
                    >
                        <HelpCircle :size="36" class="mx-auto text-[#84958A]" />
                        <h4 class="mt-2 text-sm font-semibold text-[#244334]">
                            No hay preguntas añadidas
                        </h4>
                        <p class="mt-1 text-xs text-[#718477]">
                            Haz clic en "Agregar pregunta" para configurar preguntas y opciones para los duelos de esta tarjeta.
                        </p>
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-3.5 py-2 text-xs font-semibold text-[#187744] transition hover:bg-[#EDF5EF]"
                            @click="addQuestion"
                        >
                            <Plus :size="14" />
                            Agregar primera pregunta
                        </button>
                    </div>

                    <!-- Lista de preguntas -->
                    <div
                        v-for="(question, qIndex) in form.questions"
                        :key="qIndex"
                        class="space-y-4 rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4 sm:p-5"
                    >
                        <!-- Cabecera de la pregunta -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E1EBE4] pb-3">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#187744] text-xs font-bold text-white"
                                >
                                    {{ qIndex + 1 }}
                                </span>
                                <h4 class="text-sm font-bold text-[#244334]">
                                    Pregunta #{{ qIndex + 1 }}
                                </h4>
                            </div>

                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 text-xs text-[#435D4C]">
                                    <input
                                        v-model="question.is_active"
                                        type="checkbox"
                                        class="h-4 w-4 rounded accent-[#187744]"
                                    />
                                    <span>Activa</span>
                                </label>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                    @click="removeQuestion(qIndex)"
                                >
                                    <Trash2 :size="14" />
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        <!-- Texto de la pregunta -->
                        <div class="form-field">
                            <label :for="`question-${qIndex}`" class="form-label">
                                Texto de la pregunta
                                <span class="text-red-500">*</span>
                            </label>

                            <textarea
                                :id="`question-${qIndex}`"
                                v-model="question.question"
                                rows="2"
                                required
                                placeholder="Ej. ¿Cuál es la principal característica de esta especie?"
                                class="form-textarea"
                                :class="{
                                    'form-textarea-error': form.errors[`questions.${qIndex}.question`],
                                }"
                            />

                            <p
                                v-if="form.errors[`questions.${qIndex}.question`]"
                                class="form-error"
                            >
                                {{ form.errors[`questions.${qIndex}.question`] }}
                            </p>
                        </div>

                        <!-- Opciones de respuesta -->
                        <div class="space-y-3 pt-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold uppercase tracking-wider text-[#435D4C]">
                                    Opciones de respuesta (marca la correcta)
                                </label>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#187744] hover:underline"
                                    @click="addAnswer(qIndex)"
                                >
                                    <Plus :size="13" />
                                    Agregar opción
                                </button>
                            </div>

                            <div class="space-y-2.5">
                                <div
                                    v-for="(answer, aIndex) in question.answers"
                                    :key="aIndex"
                                    class="flex items-center gap-2 rounded-lg border p-2.5 transition"
                                    :class="
                                        answer.is_correct
                                            ? 'border-[#BFCFC6] bg-[#EAF5EE]'
                                            : 'border-[#E1EBE4] bg-white'
                                    "
                                >
                                    <!-- Letra de opción -->
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-xs font-bold"
                                        :class="
                                            answer.is_correct
                                                ? 'bg-[#187744] text-white'
                                                : 'bg-[#F2F7F4] text-[#718477]'
                                        "
                                    >
                                        {{ String.fromCharCode(65 + aIndex) }}
                                    </span>

                                    <!-- Campo de texto de la opción -->
                                    <div class="min-w-0 flex-1">
                                        <input
                                            v-model="answer.answer"
                                            type="text"
                                            required
                                            :placeholder="`Opción ${String.fromCharCode(65 + aIndex)}`"
                                            class="form-input py-2 text-sm"
                                            :class="{
                                                'form-input-error': form.errors[`questions.${qIndex}.answers.${aIndex}.answer`],
                                            }"
                                        />
                                    </div>

                                    <!-- Botón para marcar correcta -->
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                                        :class="
                                            answer.is_correct
                                                ? 'border-[#187744] bg-[#187744] text-white'
                                                : 'border-[#C8DCCF] bg-white text-[#435D4C] hover:bg-[#F2F7F4]'
                                        "
                                        @click="selectCorrectAnswer(qIndex, aIndex)"
                                    >
                                        <CheckCircle2 :size="14" />
                                        {{ answer.is_correct ? 'Correcta' : 'Elegir' }}
                                    </button>

                                    <!-- Eliminar opción -->
                                    <button
                                        v-if="question.answers.length > 2"
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#718477] transition hover:bg-red-50 hover:text-red-600"
                                        title="Eliminar opción"
                                        @click="removeAnswer(qIndex, aIndex)"
                                    >
                                        <Trash2 :size="15" />
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="form.errors[`questions.${qIndex}.answers`]"
                                class="form-error"
                            >
                                {{ form.errors[`questions.${qIndex}.answers`] }}
                            </p>
                        </div>
                    </div>
                </div>
            </AdminCard>

            <!-- Imagen de la tarjeta -->
            <AdminCard
                title="Imagen de la tarjeta"
                description="Sube la imagen que se mostrará en la colección."
                :icon="FileImage"
            >
                <div>
                    <label for="card_image" class="form-upload">
                        <div class="form-upload-icon">
                            <Upload :size="24" />
                        </div>

                        <span class="form-upload-title">
                            {{ form.card_image?.name || 'Selecciona una imagen' }}
                        </span>

                        <span class="form-upload-description">
                            Haz clic para buscar un archivo en tu dispositivo.
                        </span>

                        <span class="form-upload-description">
                            JPG, JPEG, PNG o WEBP · Máximo 5 MB
                        </span>

                        <input
                            id="card_image"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            class="form-upload-input"
                            @change="handleCardImage"
                        />
                    </label>

                    <p
                        v-if="form.errors.card_image"
                        class="form-error mt-3"
                    >
                        {{ form.errors.card_image }}
                    </p>
                </div>
            </AdminCard>

            <!-- Modelo 3D -->
            <AdminCard
                title="Modelo 3D"
                description="Añade un modelo interactivo asociado a la tarjeta."
                :icon="Box"
            >
                <div class="space-y-5">
                    <div class="form-grid">
                        <!-- Nombre del modelo -->
                        <div class="form-field">
                            <label for="model_name" class="form-label">
                                Nombre del modelo
                            </label>

                            <input
                                id="model_name"
                                v-model="form.model_name"
                                type="text"
                                placeholder="Ej. Jaguar 3D"
                                class="form-input"
                                :class="{
                                    'form-input-error': form.errors.model_name,
                                }"
                            />

                            <p
                                v-if="form.errors.model_name"
                                class="form-error"
                            >
                                {{ form.errors.model_name }}
                            </p>
                        </div>

                        <!-- Formato -->
                        <div class="form-field">
                            <label for="model_format" class="form-label">
                                Formato del modelo
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="model_format"
                                    v-model="form.model_format"
                                    class="form-select appearance-none"
                                    :class="{
                                        'form-input-error': form.errors.model_format,
                                    }"
                                >
                                    <option value="">
                                        Seleccionar formato
                                    </option>
                                    <option value="glb">GLB</option>
                                    <option value="gltf">GLTF</option>
                                </select>

                                <ChevronDown
                                    :size="16"
                                    class="form-select-icon"
                                />
                            </div>

                            <p
                                v-if="form.errors.model_format"
                                class="form-error"
                            >
                                {{ form.errors.model_format }}
                            </p>
                        </div>
                    </div>

                    <!-- Archivo del modelo -->
                    <div class="form-field">
                        <label for="model_file" class="form-label">
                            Archivo del modelo
                        </label>

                        <label
                            for="model_file"
                            class="form-upload sm:flex-row sm:text-left"
                        >
                            <div class="form-upload-icon">
                                <FileBox :size="22" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-medium text-[#244334]">
                                    {{ form.model_file?.name || 'Seleccionar archivo 3D' }}
                                </p>

                                <p class="form-upload-description mt-1">
                                    Formatos GLB o GLTF · Máximo 50 MB
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C] transition hover:bg-[#F2F7F4]"
                            >
                                <Upload :size="16" />
                                Examinar
                            </span>

                            <input
                                id="model_file"
                                type="file"
                                accept=".glb,.gltf"
                                class="form-upload-input"
                                @change="handleModelFile"
                            />
                        </label>

                        <p
                            v-if="form.errors.model_file"
                            class="form-error"
                        >
                            {{ form.errors.model_file }}
                        </p>
                    </div>

                    <!-- URL del modelo -->
                    <div class="form-field">
                        <label for="model_url" class="form-label">
                            URL del modelo 3D
                        </label>

                        <input
                            id="model_url"
                            v-model="form.model_url"
                            type="url"
                            placeholder="https://ejemplo.com/modelo.glb"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.model_url,
                            }"
                        />

                        <p class="form-help">
                            Opcional. Utiliza este campo si el modelo está alojado externamente.
                        </p>

                        <p
                            v-if="form.errors.model_url"
                            class="form-error"
                        >
                            {{ form.errors.model_url }}
                        </p>
                    </div>

                    <!-- Vista previa del modelo -->
                    <div class="form-field">
                        <span class="form-label">Vista previa</span>

                        <ModelViewer
                            :source="modelPreviewSource"
                            :extension="modelPreviewExtension"
                        />
                    </div>

                    <!-- Descripción del modelo -->
                    <div class="form-field">
                        <label for="model_description" class="form-label">
                            Descripción del modelo
                        </label>

                        <textarea
                            id="model_description"
                            v-model="form.model_description"
                            rows="3"
                            placeholder="Información adicional sobre el modelo 3D..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.model_description,
                            }"
                        />

                        <p
                            v-if="form.errors.model_description"
                            class="form-error"
                        >
                            {{ form.errors.model_description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define la disponibilidad de la tarjeta en el sistema."
                :icon="Layers3"
            >
                <ActiveSwitch
                    id="is_active"
                    v-model="form.is_active"
                    label="Tarjeta activa"
                    description="La tarjeta estará disponible para mostrarse y utilizarse en el sistema."
                    :error="form.errors.is_active"
                />
            </AdminCard>

            <!-- Acciones: pie -->
            <AdminCard :padding="false">
                <template #default>
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-end sm:px-6">
                        <p class="form-help sm:mr-auto">
                            <span class="text-red-500">*</span>
                            Campos obligatorios según la validación del formulario.
                        </p>

                        <CancelButton
                            :href="admin.cards.index().url"
                            :icon="ArrowLeft"
                        >
                            Cancelar
                        </CancelButton>

                        <AdminButton
                            type="submit"
                            :disabled="form.processing"
                            :loading="form.processing"
                            :icon="Save"
                            loading-text="Guardando..."
                        >
                            Guardar tarjeta
                        </AdminButton>
                    </div>
                </template>
            </AdminCard>
        </form>
    </div>
</template>
