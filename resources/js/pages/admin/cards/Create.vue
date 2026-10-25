<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    ChevronDown,
    FileBox,
    FileImage,
    Info,
    Layers3,
    Save,
    Tag,
    Upload,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, useTemplateRef } from 'vue';

import { useObjectUrl } from '@/composables/useObjectUrl';
import { rarityOptions } from '@/lib/cardRarity';
import type { CardRaritySetting, DuelSettingsSource } from '@/lib/cardRarity';
import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import CardImageField from '@/components/admin/cards/CardImageField.vue';
import CardPreview from '@/components/admin/cards/CardPreview.vue';
import DuelSettingsFields from '@/components/admin/cards/DuelSettingsFields.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';

interface Species {
    id: number;
    species_category_id?: number;
    category?: { id: number; name: string };
    common_name: string;
    scientific_name: string;
}

const props = defineProps<{
    species: Species[];
    raritySettings: CardRaritySetting[];
}>();

const form = useForm({
    species_id: '',
    name: '',
    rarity: 'comun',
    thumbnail_image: null as File | null,
    artwork_image: null as File | null,
    generated_image: null as File | null,
    model_file: null as File | null,
    is_active: true,
    sort_order: 0,
    astucia: 50,
    rapidez: 50,
    fuerza: 50,
    duel_settings_source: 'rarity' as DuelSettingsSource,
    questions_per_duel: null as number | null,
    retries: null as number | null,
    cooldown_minutes: null as number | null,
});

/*
 * Vista previa de la tarjeta generada a partir de la plantilla.
 */
const preview = useTemplateRef<InstanceType<typeof CardPreview>>('preview');

const selectedSpecies = computed(() =>
    props.species.find((item) => String(item.id) === form.species_id),
);

const artworkPreviewUrl = useObjectUrl(() => form.artwork_image);

const generatingImage = ref(false);

const submit = async () => {
    /*
     * La imagen final se genera al guardar para que coincida
     * con los datos enviados.
     */
    generatingImage.value = true;

    try {
        form.generated_image = (await preview.value?.generatePngFile()) ?? null;
    } catch {
        form.generated_image = null;
    } finally {
        generatingImage.value = false;
    }

    form.post(admin.cards.store().url, {
        forceFormData: true,
    });
};

const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.model_file = target.files?.[0] ?? null;
};

/*
 * Vista previa del archivo seleccionado.
 */
const modelFilePreviewUrl = useObjectUrl(() => form.model_file);

const modelPreviewSource = computed(() => modelFilePreviewUrl.value);

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

            <!-- Imágenes de la tarjeta -->
            <AdminCard
                title="Imágenes de la tarjeta"
                description="Sube la miniatura y la imagen que se usará en la plantilla."
                :icon="FileImage"
            >
                <div class="grid gap-5 lg:grid-cols-2">
                    <CardImageField
                        id="thumbnail_image"
                        v-model:file="form.thumbnail_image"
                        label="Miniatura"
                        help="Se muestra en los listados de la colección."
                        :error="form.errors.thumbnail_image"
                    />

                    <CardImageField
                        id="artwork_image"
                        v-model:file="form.artwork_image"
                        label="Imagen de la tarjeta"
                        help="Fondo de la plantilla de la tarjeta."
                        :error="form.errors.artwork_image"
                    />
                </div>

                <p v-if="form.errors.generated_image" class="form-error mt-3">
                    {{ form.errors.generated_image }}
                </p>
            </AdminCard>

            <!-- Vista previa de la tarjeta -->
            <CardPreview
                ref="preview"
                :title="form.name"
                :scientific-name="selectedSpecies?.scientific_name ?? ''"
                :astucia="form.astucia"
                :rapidez="form.rapidez"
                :fuerza="form.fuerza"
                :artwork-url="artworkPreviewUrl"
            />

            <!-- Modelo 3D -->
            <AdminCard
                title="Modelo 3D"
                description="Añade un modelo interactivo asociado a la tarjeta."
                :icon="Box"
            >
                <div class="space-y-5">
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
                                    Formato GLB · Máximo 50 MB
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
                                accept=".glb"
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

                    <!-- Vista previa del modelo -->
                    <div class="form-field">
                        <span class="form-label">Vista previa</span>

                        <ModelViewer
                            :source="modelPreviewSource"
                            extension="glb"
                        />
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
                            :disabled="form.processing || generatingImage"
                            :loading="form.processing || generatingImage"
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
