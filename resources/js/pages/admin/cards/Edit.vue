<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    ChevronDown,
    ExternalLink,
    FileBox,
    FileImage,
    Info,
    Layers3,
    Save,
    Tag,
    Trash2,
    Upload,
    Zap,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, ref, useTemplateRef } from 'vue';

import { useObjectUrl } from '@/composables/useObjectUrl';
import { rarityOptions } from '@/lib/cardRarity';
import { DEFAULT_FADE_COLOR } from '@/lib/cardColors';
import type { AccentColor } from '@/lib/cardColors';
import { DEFAULT_TITLE_FONT } from '@/lib/cardFonts';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';
import type { CardRaritySetting, DuelSettingsSource } from '@/lib/cardRarity';
import { storageUrl } from '@/lib/modelPreview';
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
import CardStatField from '@/components/admin/cards/CardStatField.vue';
import DuelSettingsFields from '@/components/admin/cards/DuelSettingsFields.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';
import { cardStat } from '@/lib/cardStats';
import type { DuelMessages } from '@/lib/duelMessages';

interface Species {
    id: number;
    species_category_id?: number;
    category?: { id: number; name: string };
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    thumbnail_image: string | null;
    artwork_image: string | null;
    generated_image: string | null;
    fade_color: string;
    accent_color: AccentColor | null;
    title_font_family: CardFontId;
    title_font_weight: number;
    title_font_size: TitleSize;
    model_file: string | null;
    is_active: boolean;
    sort_order: number;
    astucia?: number;
    rapidez?: number;
    fuerza?: number;
    duel_settings_source?: DuelSettingsSource;
    questions_per_duel?: number | null;
    retries?: number | null;
    cooldown_minutes?: number | null;
    messages_source?: 'default' | 'card';
    win_message?: string | null;
    retry_message?: string | null;
    locked_message?: string | null;
    species?: Species;
}

const props = defineProps<{
    card: Card;
    species: Species[];
    raritySettings: CardRaritySetting[];
    duelMessageDefaults: DuelMessages;
}>();

const form = useForm({
    species_id: String(props.card.species_id),
    name: props.card.name ?? '',
    rarity: props.card.rarity ?? 'comun',
    thumbnail_image: null as File | null,
    artwork_image: null as File | null,
    generated_image: null as File | null,
    fade_color: props.card.fade_color ?? DEFAULT_FADE_COLOR,
    accent_color: props.card.accent_color ?? null,
    title_font_family: props.card.title_font_family ?? DEFAULT_TITLE_FONT.family,
    title_font_weight: props.card.title_font_weight ?? DEFAULT_TITLE_FONT.weight,
    title_font_size: props.card.title_font_size ?? DEFAULT_TITLE_FONT.size,
    model_file: null as File | null,
    is_active: props.card.is_active,
    sort_order: props.card.sort_order ?? 0,
    astucia: props.card.astucia ?? 50,
    rapidez: props.card.rapidez ?? 50,
    fuerza: props.card.fuerza ?? 50,
    duel_settings_source: (props.card.duel_settings_source ??
        'rarity') as DuelSettingsSource,
    questions_per_duel: props.card.questions_per_duel ?? null,
    retries: props.card.retries ?? null,
    cooldown_minutes: props.card.cooldown_minutes ?? null,
    messages_source: props.card.messages_source ?? 'default',
    win_message: props.card.win_message ?? null,
    retry_message: props.card.retry_message ?? null,
    locked_message: props.card.locked_message ?? null,
});

/*
 * Vista previa de la tarjeta generada a partir de la plantilla.
 */
const preview = useTemplateRef<InstanceType<typeof CardPreview>>('preview');

const selectedSpecies = computed(() =>
    props.species.find((item) => String(item.id) === form.species_id),
);

const artworkFilePreviewUrl = useObjectUrl(() => form.artwork_image);

const artworkPreviewUrl = computed(
    () => artworkFilePreviewUrl.value ?? storageUrl(props.card.artwork_image),
);

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

    form.transform((data) => ({
        ...data,
        _method: 'PUT',
    })).post(admin.cards.update(props.card.id).url, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const handleModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.model_file = target.files?.[0] ?? null;
};

/*
 * Vista previa: archivo nuevo o, si no hay, el archivo guardado.
 */
const modelFilePreviewUrl = useObjectUrl(() => form.model_file);

const modelPreviewSource = computed(
    () => modelFilePreviewUrl.value ?? storageUrl(props.card.model_file),
);

const deleteImage = (image: 'thumbnail' | 'artwork') => {
    Swal.fire({
        title: '¿Eliminar imagen?',
        text:
            image === 'thumbnail'
                ? 'La miniatura actual de esta tarjeta será eliminada.'
                : 'La imagen actual de la plantilla será eliminada.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(admin.cards.image.destroy([props.card.id, image]).url, {
            preserveScroll: true,
            onSuccess: () => {
                form[`${image}_image` as const] = null;
            },
        });
    });
};

const deleteModel = () => {
    Swal.fire({
        title: '¿Eliminar modelo 3D?',
        text: 'El archivo 3D actual de esta tarjeta será eliminado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (!result.isConfirmed) return;

        form.delete(admin.cards.model.destroy(props.card.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.model_file = null;
            },
        });
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
                title: 'Editar tarjeta',
                href: admin.cards.edit,
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar tarjeta: ${card.name}`" />

    <div
        class="flex h-full min-w-0 flex-1 flex-col gap-5 bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar tarjeta"
            :description="`Actualiza la información de ${card.name}.`"
            section="Colección"
            :icon="Tag"
            :action-icon="ArrowLeft"
            action-label="Volver a tarjetas"
            :action-href="admin.cards.index().url"
        />

        <form
            id="card-form"
            class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_30rem] xl:items-start"
            @submit.prevent="submit"
        >
            <!--
                Vista previa: fija a la derecha en pantallas anchas;
                en pantallas pequeñas aparece antes del formulario.
            -->
            <div class="min-w-0 xl:sticky xl:top-5 xl:order-2">
                <CardPreview
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

            <div class="min-w-0 space-y-5 xl:order-1">
                <!-- Contenido de la tarjeta -->
                <AdminCard
                    title="Contenido de la tarjeta"
                    description="Especie y nombre que aparecen en la tarjeta."
                    :icon="Info"
                >
                    <div class="form-grid">
                        <!-- Especie -->
                        <div class="form-field">
                            <label for="species_id" class="form-label">
                                Especie <span class="form-required">*</span>
                            </label>

                            <div class="form-select-wrapper">
                                <select
                                    id="species_id"
                                    v-model="form.species_id"
                                    class="form-select appearance-none"
                                >
                                    <option value="">
                                        Selecciona una especie
                                    </option>

                                    <option
                                        v-for="item in species"
                                        :key="item.id"
                                        :value="String(item.id)"
                                    >
                                        {{ item.common_name }} —
                                        {{ item.scientific_name }}
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
                                <span class="form-required">*</span>
                            </label>

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                placeholder="Ej. Jaguar del Norte"
                                class="form-input"
                            />

                            <p v-if="form.errors.name" class="form-error">
                                {{ form.errors.name }}
                            </p>
                        </div>
                    </div>
                </AdminCard>

                <!-- Estadísticas de la tarjeta (Astucia, Rapidez, Fuerza: 1-100) -->
                <AdminCard
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
                    title="Imagen de la tarjeta"
                    description="Fondo de la plantilla de la tarjeta."
                    :icon="FileImage"
                >
                    <CardImageField
                        id="artwork_image"
                        v-model:file="form.artwork_image"
                        label="Imagen de la tarjeta"
                        help="Fondo de la plantilla de la tarjeta."
                        :current-path="card.artwork_image"
                        :alt="card.name"
                        :error="form.errors.artwork_image"
                        :disabled="form.processing"
                        @delete="deleteImage('artwork')"
                    />

                    <p v-if="form.errors.generated_image" class="form-error mt-3">
                        {{ form.errors.generated_image }}
                    </p>
                </AdminCard>

                <!-- Diseño de la tarjeta -->
                <CardDesignFields
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
                                    Rareza <span class="form-required">*</span>
                                </label>

                                <div class="form-select-wrapper">
                                    <select
                                        id="rarity"
                                        v-model="form.rarity"
                                        class="form-select appearance-none"
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

                                <p
                                    v-if="form.errors.rarity"
                                    class="form-error"
                                >
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
                            v-model:file="form.thumbnail_image"
                            label="Miniatura"
                            help="Se muestra en los listados de la colección."
                            :current-path="card.thumbnail_image"
                            :alt="card.name"
                            :error="form.errors.thumbnail_image"
                            :disabled="form.processing"
                            @delete="deleteImage('thumbnail')"
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
                    title="Modelo 3D"
                    description="Administra el modelo 3D asociado a esta tarjeta."
                    :icon="Box"
                >
                    <div class="space-y-5">
                        <!-- Modelo actual -->
                        <div
                            v-if="card.model_file"
                            class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:p-5"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                                >
                                    <FileBox :size="20" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-sm font-semibold text-[#244334]"
                                    >
                                        Modelo actual
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div
                                    class="min-w-0 rounded-lg border border-[#DCE7DF] bg-white p-3"
                                >
                                    <p class="text-xs text-[#718477]">
                                        Archivo
                                    </p>

                                    <a
                                        :href="storageUrl(card.model_file) ?? ''"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 flex items-start gap-1 break-all text-sm font-medium text-[#187744] hover:underline"
                                    >
                                        <span>{{ card.model_file }}</span>
                                        <ExternalLink
                                            :size="14"
                                            class="mt-0.5 shrink-0"
                                        />
                                    </a>
                                </div>
                            </div>

                            <button
                                v-if="card.model_file"
                                type="button"
                                :disabled="form.processing"
                                class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="deleteModel"
                            >
                                <Trash2 :size="16" />
                                Eliminar archivo 3D
                            </button>
                        </div>

                        <!-- Archivo 3D -->
                        <div class="form-field">
                            <label for="model_file" class="form-label">
                                {{
                                    card.model_file
                                        ? 'Reemplazar archivo 3D'
                                        : 'Archivo del modelo'
                                }}
                            </label>

                            <label
                                for="model_file"
                                class="form-upload sm:flex-row sm:text-left"
                            >
                                <div class="form-upload-icon">
                                    <FileBox :size="22" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p
                                        class="break-all text-sm font-medium text-[#244334]"
                                    >
                                        {{
                                            form.model_file?.name ||
                                            'Seleccionar archivo 3D'
                                        }}
                                    </p>

                                    <p class="form-upload-description mt-1">
                                        Formato GLB · Máximo 50 MB
                                    </p>
                                </div>

                                <span
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#435D4C] transition group-hover:border-[#A9CBB5]"
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
                                height-class="h-[460px]"
                            />
                        </div>
                    </div>
                </AdminCard>

                <!-- Configuración -->
                <AdminCard
                    title="Configuración"
                    description="Controla la disponibilidad de la tarjeta en el sistema."
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

                <!-- Acciones -->
                <div
                    class="sticky bottom-0 z-10 flex flex-col gap-3 rounded-2xl border border-[#DCE7DF] bg-white p-4 border-t-2 border-t-[#DCE7DF] sm:flex-row sm:items-center sm:justify-end sm:px-6"
                >
                    <p class="form-help sm:mr-auto">
                        Actualiza los campos necesarios antes de guardar.
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
                        loading-text="Guardando cambios..."
                        :icon="Save"
                    >
                        Guardar cambios
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>
