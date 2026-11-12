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
import { computed, ref, useTemplateRef, watch } from 'vue';

import { useCardModelSize } from '@/composables/useCardModelSize';
import type { ModelFormFields } from '@/composables/useCardModelSize';
import { useObjectUrl } from '@/composables/useObjectUrl';
import { usePreviewTab } from '@/composables/usePreviewTab';
import type { GlbSizeConfig } from '@/lib/glb/modelSize';
import { rarityOptions } from '@/lib/cardRarity';
import { DEFAULT_FADE_COLOR } from '@/lib/cardColors';
import type { AccentColor } from '@/lib/cardColors';
import { DEFAULT_TITLE_FONT } from '@/lib/cardFonts';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';
import type { CardRaritySetting, DuelSettingsSource } from '@/lib/cardRarity';
import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import CardDesignFields from '@/components/admin/cards/CardDesignFields.vue';
import CardImageField from '@/components/admin/cards/CardImageField.vue';
import CardMessagesFields from '@/components/admin/cards/CardMessagesFields.vue';
import CardPreview from '@/components/admin/cards/CardPreview.vue';
import CardPreviewPanel from '@/components/admin/cards/CardPreviewPanel.vue';
import CardStatField from '@/components/admin/cards/CardStatField.vue';
import DuelSettingsFields from '@/components/admin/cards/DuelSettingsFields.vue';
import ModelSizeField from '@/components/admin/cards/ModelSizeField.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';
import { cardStat } from '@/lib/cardStats';
import { optimizeUpload } from '@/lib/imageOptimize';
import type { DuelMessages } from '@/lib/duelMessages';

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
    duelMessageDefaults: DuelMessages;
    glbConfig: GlbSizeConfig;
}>();

const form = useForm({
    species_id: '',
    name: '',
    rarity: 'comun',
    thumbnail_image: null as File | null,
    artwork_image: null as File | null,
    generated_image: null as File | null,
    fade_color: DEFAULT_FADE_COLOR,
    accent_color: null as AccentColor | null,
    title_font_family: DEFAULT_TITLE_FONT.family as CardFontId,
    title_font_weight: DEFAULT_TITLE_FONT.weight,
    title_font_size: DEFAULT_TITLE_FONT.size as TitleSize,
    is_active: true,
    sort_order: 0,
    astucia: 50,
    rapidez: 50,
    fuerza: 50,
    duel_settings_source: 'rarity' as DuelSettingsSource,
    questions_per_duel: null as number | null,
    retries: null as number | null,
    cooldown_minutes: null as number | null,
    messages_source: 'default' as 'default' | 'card',
    win_message: null as string | null,
    retry_message: null as string | null,
    locked_message: null as string | null,
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

const SAVING_TEXT = 'Guardando...';

const submit = async () => {
    /*
     * El modelo 3D se escala en el navegador antes de enviarlo.
     */
    const modelFields = await processModel();

    if (!modelFields) {
        return;
    }

    /*
     * La imagen final se genera al guardar para que coincida
     * con los datos enviados.
     */
    generatingImage.value = true;

    // Peso de cada imagen antes de comprimirla, para mostrar el ahorro.
    const originalBytes: Record<string, number> = {};

    try {
        const generated = await preview.value?.generateCardFile();

        form.generated_image = generated?.file ?? null;

        if (generated) {
            originalBytes.generated = generated.originalBytes;
        }
    } catch {
        form.generated_image = null;
    }

    if (form.thumbnail_image) {
        originalBytes.thumbnail = form.thumbnail_image.size;
    }

    if (form.artwork_image) {
        originalBytes.artwork = form.artwork_image.size;
    }

    /*
     * Las imágenes subidas se envían en WebP si así pesan menos; el
     * formulario conserva los originales por si hay que reenviarlo.
     */
    const [thumbnail, artwork] = await Promise.all([
        form.thumbnail_image &&
            optimizeUpload(form.thumbnail_image, 'thumbnail'),
        form.artwork_image && optimizeUpload(form.artwork_image, 'artwork'),
    ]);

    generatingImage.value = false;

    form.transform((data) => ({
        ...data,
        ...modelFields,
        thumbnail_image: thumbnail,
        artwork_image: artwork,
        image_original_bytes: originalBytes,
    })).post(admin.cards.store().url, {
        forceFormData: true,
    });
};

/*
 * Archivo 3D elegido (original). Se envía junto con el archivo escalado.
 */
const modelFile = ref<File | null>(null);

/*
 * Con archivos nuevos la tarjeta se crea inactiva: sus créditos se registran
 * después, en la sección Créditos, y entonces se activa.
 */
const hasUploads = computed(() =>
    Boolean(form.artwork_image || form.thumbnail_image || modelFile.value),
);

watch(hasUploads, (uploads) => {
    if (uploads) {
        form.is_active = false;
    }
});


const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    modelFile.value = target.files?.[0] ?? null;
};

const modelSize = useCardModelSize(props.glbConfig, modelFile);

/*
 * Vista previa lateral (Tarjeta | Modelo 3D): cambia sola según la sección
 * que se edita. Al elegir un modelo se muestra su vista previa.
 */
const {
    tab: previewTab,
    isWide,
    onFormInteraction,
} = usePreviewTab();

watch(modelFile, (file) => {
    if (file) {
        previewTab.value = 'model';
    }
});

/*
 * Alto del visor en la barra lateral: cabe en la pantalla junto con el
 * encabezado y la lista de animaciones.
 */
const SIDEBAR_VIEWER_HEIGHT = 'min(520px, calc(100dvh - 16rem))';

/*
 * Vista previa del archivo seleccionado.
 */
const modelFilePreviewUrl = useObjectUrl(() => modelFile.value);

const modelPreviewSource = computed(() => modelFilePreviewUrl.value);

/*
 * Alto que muestra la vista previa (null = tamaño del archivo).
 */
const previewHeight = computed(() =>
    modelSize.heightError === null ? modelSize.height : null,
);

const modelErrors = computed(
    () => form.errors as Partial<Record<keyof ModelFormFields, string>>,
);

const processingModel = ref(false);

/*
 * Devuelve los campos del modelo a enviar, o null si no se pudo procesar
 * (el error se muestra en el panel de tamaño).
 */
const processModel = async (): Promise<ModelFormFields | null> => {
    processingModel.value = true;

    try {
        return await modelSize.process();
    } catch {
        previewTab.value = 'model';

        document
            .getElementById('model_target_height')
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });

        return null;
    } finally {
        processingModel.value = false;
    }
};

const uploadPercentage = computed(() =>
    Math.round(form.progress?.percentage ?? 0),
);

const saving = computed(
    () => processingModel.value || generatingImage.value || form.processing,
);

const savingText = computed(() => {
    if (processingModel.value) {
        return 'Procesando modelo…';
    }

    if (form.processing && form.progress) {
        return `Subiendo ${uploadPercentage.value}%`;
    }

    return SAVING_TEXT;
});


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
        class="flex h-full min-w-0 flex-1 flex-col gap-5 bg-[#F2F7F4] p-4 sm:p-5"
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
            class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_34rem] xl:items-start 2xl:grid-cols-[minmax(0,1fr)_38rem]"
            @submit.prevent="submit"
        >
            <!--
                Vista previa: fija a la derecha en pantallas anchas, con
                pestañas Tarjeta | Modelo 3D; en pantallas pequeñas aparece
                antes del formulario (el modelo se ve en su sección).
            -->
            <div class="min-w-0 xl:sticky xl:top-5 xl:order-2">
                <CardPreviewPanel
                    v-if="isWide"
                    v-model:tab="previewTab"
                    :has-model="Boolean(modelPreviewSource)"
                >
                    <template #card>
                        <CardPreview
                            ref="preview"
                            embedded
                            :title="form.name"
                            :scientific-name="selectedSpecies?.scientific_name ?? ''"
                            :astucia="form.astucia"
                            :rapidez="form.rapidez"
                            :fuerza="form.fuerza"
                            :artwork-url="artworkPreviewUrl"
                            :fade-color="form.fade_color"
                            :accent-color="form.accent_color"
                            :title-font-family="form.title_font_family"
                            :title-font-weight="form.title_font_weight"
                            :title-font-size="form.title_font_size"
                        />
                    </template>

                    <template #model>
                        <ModelViewer
                            :source="modelPreviewSource"
                            extension="glb"
                            :height="SIDEBAR_VIEWER_HEIGHT"
                            :size-preview="modelSize.hasModel"
                            :target-height="previewHeight"
                            :paused="previewTab !== 'model'"
                        />
                    </template>
                </CardPreviewPanel>

                <CardPreview
                    v-else
                    ref="preview"
                    :title="form.name"
                    :scientific-name="selectedSpecies?.scientific_name ?? ''"
                    :astucia="form.astucia"
                    :rapidez="form.rapidez"
                    :fuerza="form.fuerza"
                    :artwork-url="artworkPreviewUrl"
                    :fade-color="form.fade_color"
                    :accent-color="form.accent_color"
                    :title-font-family="form.title_font_family"
                    :title-font-weight="form.title_font_weight"
                    :title-font-size="form.title_font_size"
                />
            </div>

            <div
                class="min-w-0 space-y-5 xl:order-1"
                @focusin="onFormInteraction"
                @pointerdown="onFormInteraction"
            >
                <!-- Contenido de la tarjeta -->
                <AdminCard
                    data-preview="card"
                    title="Contenido de la tarjeta"
                    description="Especie y nombre que aparecen en la tarjeta."
                    :icon="Info"
                >
                    <div class="form-grid">
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
                    </div>
                </AdminCard>

                <!-- Estadísticas de la tarjeta (Astucia, Rapidez, Fuerza: 1-100) -->
                <AdminCard
                    data-preview="card"
                    title="Estadísticas de la tarjeta"
                    description="Asigna un valor de 1 a 100 para cada atributo de la tarjeta."
                    :icon="Zap"
                >
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <CardStatField
                            id="astucia"
                            v-model="form.astucia"
                            :icon="cardStat('astucia').icon"
                            label="Astucia"
                            help="Nivel de ingenio y astucia."
                            :error="form.errors.astucia"
                        />

                        <CardStatField
                            id="rapidez"
                            v-model="form.rapidez"
                            :icon="cardStat('rapidez').icon"
                            label="Rapidez"
                            help="Velocidad y agilidad."
                            :error="form.errors.rapidez"
                        />

                        <CardStatField
                            id="fuerza"
                            v-model="form.fuerza"
                            :icon="cardStat('fuerza').icon"
                            label="Fuerza"
                            help="Potencia y resistencia física."
                            :error="form.errors.fuerza"
                        />
                    </div>
                </AdminCard>

                <!-- Imagen de la tarjeta -->
                <AdminCard
                    data-preview="card"
                    title="Imagen de la tarjeta"
                    description="Fondo de la plantilla de la tarjeta."
                    :icon="FileImage"
                >
                    <CardImageField
                        id="artwork_image"
                        kind="artwork"
                        v-model:file="form.artwork_image"
                        label="Imagen de la tarjeta"
                        help="Fondo de la plantilla de la tarjeta."
                        :error="form.errors.artwork_image"
                    />

                    <p v-if="form.errors.generated_image" class="form-error mt-3">
                        {{ form.errors.generated_image }}
                    </p>
                </AdminCard>

                <!-- Diseño de la tarjeta -->
                <CardDesignFields
                    data-preview="card"
                    v-model:fade-color="form.fade_color"
                    v-model:accent-color="form.accent_color"
                    v-model:title-font-family="form.title_font_family"
                    v-model:title-font-weight="form.title_font_weight"
                    v-model:title-font-size="form.title_font_size"
                    :errors="form.errors"
                />

                <!-- Colección y duelos -->
                <h2
                    class="px-1 pt-3 text-xs font-semibold tracking-wider text-[#718477] uppercase"
                >
                    Colección y duelos
                </h2>

                <!-- Colección -->
                <AdminCard
                    title="Colección"
                    description="Rareza, orden de aparición y miniatura de la tarjeta."
                    :icon="Layers3"
                >
                    <div class="space-y-5">
                        <div class="form-grid">
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

                        <CardImageField
                            id="thumbnail_image"
                            kind="thumbnail"
                            v-model:file="form.thumbnail_image"
                            label="Miniatura"
                            help="Se muestra en los listados de la colección."
                            :error="form.errors.thumbnail_image"
                        />
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

                <!-- Mensajes del duelo -->
                <CardMessagesFields
                    v-model:source="form.messages_source"
                    v-model:win-message="form.win_message"
                    v-model:retry-message="form.retry_message"
                    v-model:locked-message="form.locked_message"
                    :rarity="form.rarity"
                    :rarity-settings="raritySettings"
                    :defaults="duelMessageDefaults"
                    :card-name="form.name"
                    :duel-settings-source="form.duel_settings_source"
                    :retries="form.retries"
                    :cooldown-minutes="form.cooldown_minutes"
                    :errors="form.errors"
                />

                <!-- Modelo 3D -->
                <AdminCard
                    data-preview="model"
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
                                        {{ modelFile?.name || 'Seleccionar archivo 3D' }}
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

                                v-if="modelErrors.model_file || modelErrors.model_original_file"

                                class="form-error"

                            >

                                {{ modelErrors.model_file ?? modelErrors.model_original_file }}

                            </p>

                        </div>


                        <!-- Tamaño real -->

                        <ModelSizeField

                            v-if="modelSize.hasModel"

                            :model="modelSize"

                            :errors="modelErrors"

                        />

                        <!--
                            Vista previa del modelo: en pantallas anchas está
                            en la barra lateral (pestaña "Modelo 3D").
                        -->
                        <p
                            v-if="isWide && modelSize.hasModel"
                            class="flex items-center gap-2 text-xs text-[#718477]"
                        >
                            <Info :size="14" class="shrink-0 text-[#187744]" />
                            La vista previa a escala está en el panel derecho,
                            pestaña "Modelo 3D".
                        </p>

                        <div v-if="!isWide" class="form-field">
                            <span class="form-label">Vista previa</span>

                            <ModelViewer
                                :source="modelPreviewSource"
                                extension="glb"
                                height-class="h-[540px]"
                                :size-preview="modelSize.hasModel"
                                :target-height="previewHeight"
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
                        :disabled="hasUploads"
                    />

                    <p v-if="hasUploads" class="form-help mt-3">
                        La tarjeta se creará inactiva. Al guardar se abrirá su
                        sección Créditos para indicar de dónde salió cada imagen
                        y el modelo 3D; después podrás activarla.
                    </p>
                </AdminCard>

                <!-- Acciones: pie -->
                <AdminCard
                    :padding="false"
                    class="sticky bottom-0 z-10 border-t-2 border-t-[#DCE7DF]"
                >
                    <template #default>
                        <!-- Progreso de la subida -->
                        <div
                            v-if="form.processing && form.progress"
                            class="absolute inset-x-0 top-0 h-1 overflow-hidden rounded-t-2xl bg-[#E4EEE7]"
                            role="progressbar"
                            :aria-valuenow="uploadPercentage"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full bg-[#187744] transition-[width] duration-200"
                                :style="{ width: `${uploadPercentage}%` }"
                            />
                        </div>

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
                                :disabled="saving || modelSize.inspecting"
                                :loading="saving"
                                :icon="Save"
                                :loading-text="savingText"
                            >
                                Guardar tarjeta
                            </AdminButton>
                        </div>
                    </template>
                </AdminCard>
            </div>
        </form>
    </div>
</template>
