<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Crop,
    Copyright,
    Image as ImageIcon,
    Info,
    Leaf,
    Lock,
    MapPin,
    Save,
    Tags,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import SizeSavings from '@/components/admin/cards/SizeSavings.vue';
import ImageCropDialog from '@/components/admin/ImageCropDialog.vue';
import MapMarkerMap from '@/components/admin/MapMarkerMap.vue';
import MissingCreditsAlert from '@/components/admin/MissingCreditsAlert.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PageHeaderLink from '@/components/admin/PageHeaderLink.vue';
import FocalPointPicker from '@/components/admin/species/FocalPointPicker.vue';
import SpeciesAiFillDialog from '@/components/admin/species/SpeciesAiFillDialog.vue';
import type { SpeciesAiValues } from '@/components/admin/species/SpeciesAiFillDialog.vue';
import SpeciesClassificationFields from '@/components/admin/species/SpeciesClassificationFields.vue';
import SpeciesGalleryField from '@/components/admin/species/SpeciesGalleryField.vue';
import type { GalleryLayoutItem } from '@/components/admin/species/SpeciesGalleryField.vue';
import { useSpeciesImageCrop } from '@/composables/useSpeciesImageCrop';
import {
    optimizeSpeciesUploads,
    useEstimatedSize,
} from '@/composables/useSpeciesImageUploads';
import { missingImagesText } from '@/lib/credits/missing';
import { cropMemory, cropSource, rememberCrop } from '@/lib/imageCrop';
import type { SavedCrop } from '@/lib/imageCrop';
import type { HabitatCode } from '@/lib/speciesCatalogs';
import admin from '@/routes/admin';

interface GeoJsonGeometry {
    type: 'Polygon';
    coordinates: number[][][];
}

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface Zone {
    id: number;
    name: string;
    geometry: GeoJsonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
}

interface SpeciesTag {
    id: number;
    name: string;
}

interface SpeciesLocation {
    id: number;
    zone_id: number | null;
    name: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
    description: string | null;
}

interface SpeciesImage {
    id: number;
    species_id: number;
    type: 'main' | 'thumbnail' | 'gallery' | string;
    path: string;
    alt_text: string | null;
    // Punto focal (0 a 1); null = centro.
    focal_x?: number | null;
    focal_y?: number | null;
    // Copia de la foto completa y último recuadro (solo la principal).
    source_path?: string | null;
    crop?: SavedCrop | null;
    is_active: boolean;
    sort_order: number;
    // Peso actual en disco y antes de comprimir (null si se desconoce).
    bytes?: number | null;
    original_bytes?: number | null;
}

interface Species {
    id: number;
    species_category_id: number | null;
    common_name: string;
    scientific_name: string | null;
    description: string | null;
    habitats: HabitatCode[] | null;
    origin: string | null;
    diet: string | null;
    conservation_status: string | null;
    is_active: boolean;
    tags: SpeciesTag[];
    locations: SpeciesLocation[];
    images: SpeciesImage[];
}

const props = defineProps<{
    species: Species;
    categories: Category[];
    tags: Tag[];
    zones: Zone[];
    // Imágenes guardadas sin crédito de origen.
    uncreditedImageIds: number[];
    aiEnabled: boolean;
}>();

const activeTab = ref('information');

const tabs = [
    { id: 'information', name: 'Información', icon: Info },
    { id: 'tags', name: 'Etiquetas', icon: Tags },
    { id: 'images', name: 'Imágenes', icon: ImageIcon },
    { id: 'location', name: 'Ubicación', icon: MapPin },
];

const currentLocation = props.species.locations?.[0] ?? null;

const initialZoneId =
    currentLocation?.zone_id != null ? String(currentLocation.zone_id) : '';

const initialLatitude =
    currentLocation?.latitude != null ? Number(currentLocation.latitude) : null;

const initialLongitude =
    currentLocation?.longitude != null
        ? Number(currentLocation.longitude)
        : null;

const existingImages = computed<SpeciesImage[]>(
    () => props.species.images ?? [],
);

const imageUrl = (path: string | null | undefined): string | null => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('blob:')
    ) {
        return path;
    }

    if (path.startsWith('/')) {
        return path;
    }

    if (path.startsWith('storage/')) {
        return `/${path}`;
    }

    return `/storage/${path}`;
};

const existingMainImage = computed<SpeciesImage | null>(
    () =>
        existingImages.value.find(
            (image) => image.type === 'main' && image.is_active,
        ) ?? null,
);

const existingThumbnailImage = computed<SpeciesImage | null>(
    () =>
        existingImages.value.find(
            (image) => image.type === 'thumbnail' && image.is_active,
        ) ?? null,
);

const existingGalleryImages = computed<SpeciesImage[]>(() =>
    existingImages.value
        .filter((image) => image.type === 'gallery' && image.is_active)
        .sort((a, b) => a.sort_order - b.sort_order),
);

/*
 * Imágenes que quedarán sin licencia al guardar: las guardadas sin crédito que
 * no se borran, más las nuevas (una principal o miniatura que no existía, y
 * las fotos nuevas de la galería). Se puede activar, pero con aviso.
 */
const missingCredits = computed(() => {
    const uncredited = new Set(props.uncreditedImageIds);
    const deleted = new Set(form.deleted_gallery_ids);

    const saved = existingImages.value.filter(
        (image) => uncredited.has(image.id) && !deleted.has(image.id),
    ).length;

    const added =
        (form.main_image && !existingMainImage.value ? 1 : 0) +
        (form.thumbnail_image && !existingThumbnailImage.value ? 1 : 0) +
        form.gallery_images.length;

    return missingImagesText(saved + added);
});

const savedGallery = existingGalleryImages.value.map((image) => ({
    id: image.id,
    url: imageUrl(image.path) ?? '',
    alt_text: image.alt_text,
    focal_x: image.focal_x ?? null,
    focal_y: image.focal_y ?? null,
    bytes: image.bytes,
    original_bytes: image.original_bytes,
}));

const existingMainImageUrl = computed(() =>
    imageUrl(existingMainImage.value?.path),
);

// Recorta desde la foto completa guardada si la hay; si no, desde la actual.
const recropMain = () => {
    if (form.main_image) {
        openCrop('main', form.main_image);

        return;
    }

    const image = existingMainImage.value;
    const sourceUrl = imageUrl(image?.source_path);

    if (sourceUrl && image?.crop) {
        rememberCrop(sourceUrl, cropMemory(image.crop));
        openCrop('main', sourceUrl);
    } else {
        openCrop('main', existingMainImageUrl.value ?? null);
    }
};

const existingThumbnailUrl = computed(() =>
    imageUrl(existingThumbnailImage.value?.path),
);

const form = useForm({
    species_category_id:
        props.species.species_category_id != null
            ? String(props.species.species_category_id)
            : '',
    common_name: props.species.common_name ?? '',
    scientific_name: props.species.scientific_name ?? '',
    description: props.species.description ?? '',
    habitats: [...(props.species.habitats ?? [])],
    origin: props.species.origin ?? '',
    diet: props.species.diet ?? '',
    conservation_status: props.species.conservation_status ?? '',
    is_active: props.species.is_active,
    tags: props.species.tags?.map((tag) => tag.id) ?? [],
    main_image: null as File | null,
    // Punto focal de la imagen principal (0 a 1); null = centro.
    main_focal_x: (existingMainImage.value?.focal_x ?? null) as number | null,
    main_focal_y: (existingMainImage.value?.focal_y ?? null) as number | null,
    thumbnail_image: null as File | null,
    gallery_images: [] as File[],
    gallery: [] as GalleryLayoutItem[],
    deleted_gallery_ids: [] as number[],
    zone_id: initialZoneId,
    location_name: currentLocation?.name ?? '',
    latitude: initialLatitude,
    longitude: initialLongitude,
    location_description: currentLocation?.description ?? '',
});

const thumbnailPreviewUrl = ref<string | null>(existingThumbnailUrl.value);
const thumbnailObjectUrl = ref<string | null>(null);

const mainPreviewUrl = ref<string | null>(existingMainImageUrl.value);
const mainObjectUrl = ref<string | null>(null);

const selectedZone = computed<Zone | null>(
    () => props.zones.find((zone) => zone.id === Number(form.zone_id)) ?? null,
);

watch(
    () => form.zone_id,
    (newZone, oldZone) => {
        if (newZone !== oldZone) {
            form.latitude = null;
            form.longitude = null;
        }
    },
);

const applyMainImage = (file: File | null) => {
    if (mainObjectUrl.value) {
        URL.revokeObjectURL(mainObjectUrl.value);
        mainObjectUrl.value = null;
    }

    form.main_image = file;
    // El punto focal anterior era de otra foto.
    form.main_focal_x = file
        ? null
        : (existingMainImage.value?.focal_x ?? null);
    form.main_focal_y = file
        ? null
        : (existingMainImage.value?.focal_y ?? null);

    if (file) {
        const objectUrl = URL.createObjectURL(file);
        mainObjectUrl.value = objectUrl;
        mainPreviewUrl.value = objectUrl;
    } else {
        mainPreviewUrl.value = existingMainImageUrl.value;
    }
};

const applyThumbnailImage = (file: File | null) => {
    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(thumbnailObjectUrl.value);
        thumbnailObjectUrl.value = null;
    }

    form.thumbnail_image = file;

    if (file) {
        const objectUrl = URL.createObjectURL(file);
        thumbnailObjectUrl.value = objectUrl;
        thumbnailPreviewUrl.value = objectUrl;
    } else {
        thumbnailPreviewUrl.value = existingThumbnailUrl.value;
    }
};

const nextTab = () => {
    const index = tabs.findIndex((tab) => tab.id === activeTab.value);

    if (index < tabs.length - 1) {
        activeTab.value = tabs[index + 1].id;
    }
};

const previousTab = () => {
    const index = tabs.findIndex((tab) => tab.id === activeTab.value);

    if (index > 0) {
        activeTab.value = tabs[index - 1].id;
    }
};

const goToTab = (tabId: string) => {
    activeTab.value = tabId;
};

const { cropOpen, cropDialog, openCrop, pickFor, onCropped, onSkip } =
    useSpeciesImageCrop({
        main: applyMainImage,
        thumbnail: applyThumbnailImage,
    });

const { bytes: mainBytes, estimating: mainEstimating } = useEstimatedSize(
    () => form.main_image,
    'main',
);

const { bytes: thumbnailBytes, estimating: thumbnailEstimating } =
    useEstimatedSize(() => form.thumbnail_image, 'thumbnail');

// Las imágenes se comprimen a WebP antes de enviarlas.
const compressing = ref(false);

const submit = async () => {
    if (compressing.value || form.processing) {
        return;
    }

    compressing.value = true;

    const uploads = await optimizeSpeciesUploads(form).finally(() => {
        compressing.value = false;
    });

    form.transform((data) => ({ ...data, ...uploads })).put(
        admin.species.update(props.species.id).url,
        {
            forceFormData: true,
        },
    );
};

onBeforeUnmount(() => {
    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(thumbnailObjectUrl.value);
    }

    if (mainObjectUrl.value) {
        URL.revokeObjectURL(mainObjectUrl.value);
    }
});
</script>

<template>
    <Head :title="`Editar ${species.common_name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar especie"
            :description="`Actualiza la información de ${species.common_name}.`"
            section="Catálogo"
            :icon="Leaf"
            :action-icon="ArrowLeft"
            action-label="Volver a especies"
            :action-href="admin.species.index().url"
        >
            <template #actions>
                <PageHeaderLink
                    :href="admin.species.credits.index(species.id).url"
                    label="Créditos de imágenes"
                    :icon="Copyright"
                />
            </template>
        </PageHeader>

        <div
            class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white"
        >
            <form @submit.prevent="submit">
                <!-- PESTAÑAS -->
                <div
                    class="border-b border-[#DCE7DF] px-4 pt-4 sm:px-6 sm:pt-6"
                >
                    <div class="flex gap-2 overflow-x-auto">
                        <button
                            v-for="(tab, index) in tabs"
                            :key="tab.id"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-2 rounded-t-xl border-b-2 px-4 py-3 text-sm font-medium transition"
                            :class="
                                activeTab === tab.id
                                    ? 'border-[#187744] bg-[#EAF4ED] text-[#187744]'
                                    : 'border-transparent text-[#718477] hover:bg-[#F2F7F4] hover:text-[#244334]'
                            "
                            @click="goToTab(tab.id)"
                        >
                            <component :is="tab.icon" class="h-4 w-4" />
                            <span class="text-xs opacity-70"
                                >{{ index + 1 }}.</span
                            >
                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <!-- INFORMACIÓN -->
                    <AdminCard
                        v-if="activeTab === 'information'"
                        title="Información de la especie"
                        description="Actualiza los datos y características principales."
                        :icon="Info"
                    >
                        <div class="flex flex-col gap-5">
                            <div class="form-field">
                                <label
                                    for="species_category_id"
                                    class="form-label"
                                >
                                    Categoría
                                </label>
                                <select
                                    id="species_category_id"
                                    v-model="form.species_category_id"
                                    class="form-select"
                                    :class="{
                                        'form-select-error':
                                            form.errors.species_category_id,
                                    }"
                                >
                                    <option value="">
                                        Selecciona una categoría
                                    </option>
                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <p
                                    v-if="form.errors.species_category_id"
                                    class="form-error"
                                >
                                    {{ form.errors.species_category_id }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="form-field">
                                    <label for="common_name" class="form-label">
                                        Nombre común
                                        <span class="form-required">*</span>
                                    </label>
                                    <input
                                        id="common_name"
                                        v-model="form.common_name"
                                        type="text"
                                        class="form-input"
                                        :class="{
                                            'form-input-error':
                                                form.errors.common_name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.common_name"
                                        class="form-error"
                                    >
                                        {{ form.errors.common_name }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label
                                        for="scientific_name"
                                        class="form-label"
                                    >
                                        Nombre científico
                                    </label>
                                    <input
                                        id="scientific_name"
                                        v-model="form.scientific_name"
                                        type="text"
                                        class="form-input italic"
                                        :class="{
                                            'form-input-error':
                                                form.errors.scientific_name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.scientific_name"
                                        class="form-error"
                                    >
                                        {{ form.errors.scientific_name }}
                                    </p>
                                </div>
                            </div>

                            <SpeciesAiFillDialog
                                :common-name="form.common_name"
                                :scientific-name="form.scientific_name"
                                :values="form"
                                :categories="props.categories"
                                :tags="props.tags"
                                :ai-enabled="props.aiEnabled"
                                @apply="
                                    (patch: Partial<SpeciesAiValues>) =>
                                        Object.assign(form, patch)
                                "
                            />

                            <div class="form-field">
                                <label for="description" class="form-label"
                                    >Descripción</label
                                >
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    class="form-textarea"
                                    :class="{
                                        'form-textarea-error':
                                            form.errors.description,
                                    }"
                                />
                                <p
                                    v-if="form.errors.description"
                                    class="form-error"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <SpeciesClassificationFields
                                v-model:diet="form.diet"
                                v-model:conservation-status="
                                    form.conservation_status
                                "
                                v-model:habitats="form.habitats"
                                :errors="form.errors"
                            >
                                <div class="form-field">
                                    <label for="origin" class="form-label"
                                        >Origen</label
                                    >
                                    <input
                                        id="origin"
                                        v-model="form.origin"
                                        type="text"
                                        class="form-input"
                                    />
                                    <p
                                        v-if="form.errors.origin"
                                        class="form-error"
                                    >
                                        {{ form.errors.origin }}
                                    </p>
                                </div>
                            </SpeciesClassificationFields>

                            <ActiveSwitch
                                v-model="form.is_active"
                                label="Especie activa"
                                description="Determina si la especie estará disponible en el sistema."
                                :error="form.errors.is_active"
                            />

                            <MissingCreditsAlert
                                v-if="form.is_active && missingCredits"
                                :missing="missingCredits"
                                kind="especie"
                                :href="
                                    admin.species.credits.index(species.id).url
                                "
                            />
                        </div>
                    </AdminCard>

                    <!-- ETIQUETAS -->
                    <AdminCard
                        v-else-if="activeTab === 'tags'"
                        title="Etiquetas"
                        description="Selecciona las etiquetas que describen a esta especie."
                        :icon="Tags"
                    >
                        <div class="flex flex-col gap-5">
                            <div
                                v-if="tags.length"
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                            >
                                <label
                                    v-for="tag in tags"
                                    :key="tag.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#DCE7DF] px-4 py-3 transition hover:border-[#187744]/40 hover:bg-[#F2F7F4]"
                                >
                                    <input
                                        v-model="form.tags"
                                        type="checkbox"
                                        :value="tag.id"
                                        class="form-checkbox"
                                    />
                                    <span
                                        class="text-sm font-medium text-[#244334]"
                                    >
                                        {{ tag.name }}
                                    </span>
                                </label>
                            </div>

                            <div
                                v-else
                                class="rounded-xl border border-dashed border-[#DCE7DF] p-8 text-center"
                            >
                                <Tags class="mx-auto h-6 w-6 text-[#84958A]" />
                                <p class="mt-3 text-sm text-[#718477]">
                                    No hay etiquetas disponibles.
                                </p>
                            </div>

                            <div
                                v-if="form.tags.length"
                                class="rounded-xl border border-[#DCE7DF] bg-[#F2F7F4] p-4 text-sm text-[#244334]"
                            >
                                <strong>{{ form.tags.length }}</strong>
                                {{
                                    form.tags.length === 1
                                        ? 'etiqueta seleccionada'
                                        : 'etiquetas seleccionadas'
                                }}
                            </div>

                            <p v-if="form.errors.tags" class="form-error">
                                {{ form.errors.tags }}
                            </p>
                        </div>
                    </AdminCard>

                    <!-- IMÁGENES -->
                    <AdminCard
                        v-else-if="activeTab === 'images'"
                        title="Imágenes"
                        description="Las imágenes existentes se conservan si no seleccionas reemplazos."
                        :icon="ImageIcon"
                    >
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <!-- Imagen principal -->
                            <div
                                class="rounded-xl border border-[#DCE7DF] p-4 sm:p-5"
                            >
                                <label for="main_image" class="form-label">
                                    Imagen principal
                                </label>

                                <div
                                    class="my-4 overflow-hidden rounded-xl border border-[#DCE7DF] bg-[#F7FAF8]"
                                >
                                    <FocalPointPicker
                                        v-if="mainPreviewUrl"
                                        v-model:x="form.main_focal_x"
                                        v-model:y="form.main_focal_y"
                                        class="p-3"
                                        :src="mainPreviewUrl"
                                        alt="Imagen principal"
                                    />
                                    <div
                                        v-else
                                        class="flex h-48 flex-col items-center justify-center"
                                    >
                                        <ImageIcon
                                            class="h-7 w-7 text-[#84958A]"
                                        />
                                        <p class="mt-2 text-sm text-[#718477]">
                                            Sin imagen principal
                                        </p>
                                    </div>
                                </div>

                                <input
                                    id="main_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="form-input text-sm"
                                    @change="pickFor('main')($event)"
                                />

                                <p class="form-help">
                                    {{
                                        mainObjectUrl
                                            ? 'Nueva imagen seleccionada.'
                                            : 'Selecciona un archivo para reemplazar la imagen actual.'
                                    }}
                                    Se comprime a WebP antes de subirla.
                                </p>

                                <SizeSavings
                                    class="mt-2"
                                    :original="
                                        form.main_image
                                            ? cropSource(form.main_image).size
                                            : (existingMainImage?.original_bytes ??
                                              null)
                                    "
                                    :optimized="
                                        form.main_image
                                            ? mainBytes
                                            : (existingMainImage?.bytes ?? null)
                                    "
                                    :estimating="mainEstimating"
                                    :previous="
                                        form.main_image
                                            ? (existingMainImage?.bytes ?? null)
                                            : null
                                    "
                                />

                                <p
                                    v-if="form.errors.main_image"
                                    class="form-error"
                                >
                                    {{ form.errors.main_image }}
                                </p>

                                <p
                                    v-if="
                                        form.errors.main_focal_x ||
                                        form.errors.main_focal_y
                                    "
                                    class="form-error"
                                >
                                    {{
                                        form.errors.main_focal_x ||
                                        form.errors.main_focal_y
                                    }}
                                </p>

                                <button
                                    v-if="
                                        form.main_image || existingMainImageUrl
                                    "
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#187744] hover:underline"
                                    @click="recropMain"
                                >
                                    <Crop :size="16" />
                                    {{
                                        form.main_image
                                            ? 'Recortar de nuevo'
                                            : 'Recortar imagen actual'
                                    }}
                                </button>
                            </div>

                            <!-- Miniatura -->
                            <div
                                class="rounded-xl border border-[#DCE7DF] p-4 sm:p-5"
                            >
                                <label for="thumbnail_image" class="form-label">
                                    Miniatura
                                </label>

                                <div
                                    class="my-4 flex min-h-48 items-center justify-center rounded-xl bg-[#F7FAF8] p-4"
                                >
                                    <div
                                        v-if="thumbnailPreviewUrl"
                                        class="text-center"
                                    >
                                        <div
                                            class="mx-auto h-32 w-32 overflow-hidden rounded-full border-4 border-white shadow-sm"
                                        >
                                            <img
                                                :src="thumbnailPreviewUrl"
                                                alt="Miniatura"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>
                                        <p class="mt-2 text-xs text-[#718477]">
                                            {{
                                                thumbnailObjectUrl
                                                    ? 'Nueva miniatura'
                                                    : 'Miniatura actual'
                                            }}
                                        </p>
                                    </div>

                                    <div v-else class="text-center">
                                        <ImageIcon
                                            class="mx-auto h-7 w-7 text-[#84958A]"
                                        />
                                        <p class="mt-2 text-sm text-[#718477]">
                                            Sin miniatura
                                        </p>
                                    </div>
                                </div>

                                <input
                                    id="thumbnail_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="form-input text-sm"
                                    @change="pickFor('thumbnail')($event)"
                                />

                                <p class="form-help">
                                    Deja vacío para conservar la miniatura
                                    actual.
                                </p>

                                <SizeSavings
                                    class="mt-2"
                                    :original="
                                        form.thumbnail_image
                                            ? cropSource(form.thumbnail_image)
                                                  .size
                                            : (existingThumbnailImage?.original_bytes ??
                                              null)
                                    "
                                    :optimized="
                                        form.thumbnail_image
                                            ? thumbnailBytes
                                            : (existingThumbnailImage?.bytes ??
                                              null)
                                    "
                                    :estimating="thumbnailEstimating"
                                    :previous="
                                        form.thumbnail_image
                                            ? (existingThumbnailImage?.bytes ??
                                              null)
                                            : null
                                    "
                                />

                                <p
                                    v-if="form.errors.thumbnail_image"
                                    class="form-error"
                                >
                                    {{ form.errors.thumbnail_image }}
                                </p>

                                <p
                                    class="mt-2 flex items-center gap-1 text-xs font-semibold text-[#187744]"
                                >
                                    <Lock :size="12" />
                                    Se recorta a proporción 1:1
                                </p>

                                <button
                                    v-if="
                                        form.thumbnail_image ||
                                        existingThumbnailUrl
                                    "
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-[#187744] hover:underline"
                                    @click="
                                        openCrop(
                                            'thumbnail',
                                            form.thumbnail_image ??
                                                existingThumbnailUrl ??
                                                '',
                                        )
                                    "
                                >
                                    <Crop :size="16" />
                                    {{
                                        form.thumbnail_image
                                            ? 'Recortar de nuevo'
                                            : 'Recortar miniatura actual'
                                    }}
                                </button>
                            </div>

                            <!-- Galería -->
                            <div
                                class="rounded-xl border border-[#DCE7DF] p-4 sm:col-span-2 sm:p-5"
                            >
                                <SpeciesGalleryField
                                    v-model:files="form.gallery_images"
                                    v-model:layout="form.gallery"
                                    v-model:deleted-ids="
                                        form.deleted_gallery_ids
                                    "
                                    :existing="savedGallery"
                                    :errors="form.errors"
                                    @use-as-main="openCrop('main', $event)"
                                    @use-as-thumbnail="
                                        openCrop('thumbnail', $event)
                                    "
                                />

                                <ImageCropDialog
                                    v-model:open="cropOpen"
                                    v-bind="cropDialog"
                                    @cropped="onCropped"
                                    @skip="onSkip"
                                />
                            </div>
                        </div>
                    </AdminCard>

                    <!-- UBICACIÓN -->
                    <AdminCard
                        v-else-if="activeTab === 'location'"
                        title="Ubicación"
                        description="Actualiza la ubicación física de la especie."
                        :icon="MapPin"
                    >
                        <div class="flex flex-col gap-5">
                            <div class="form-field">
                                <label for="zone_id" class="form-label">
                                    Zona del zoológico
                                </label>

                                <select
                                    id="zone_id"
                                    v-model="form.zone_id"
                                    class="form-select"
                                    :class="{
                                        'form-select-error':
                                            form.errors.zone_id,
                                    }"
                                >
                                    <option value="">Sin zona asignada</option>
                                    <option
                                        v-for="zone in zones"
                                        :key="zone.id"
                                        :value="zone.id"
                                    >
                                        {{ zone.name }}
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.zone_id"
                                    class="form-error"
                                >
                                    {{ form.errors.zone_id }}
                                </p>

                                <div
                                    v-if="
                                        selectedZone && !selectedZone.map_image
                                    "
                                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3"
                                >
                                    <p class="text-xs text-amber-800">
                                        Esta zona no tiene un plano configurado.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="selectedZone"
                                class="flex flex-col gap-4"
                            >
                                <div
                                    class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                                >
                                    <h3
                                        class="text-sm font-semibold text-[#244334]"
                                    >
                                        Ubicación en el mapa
                                    </h3>
                                    <p class="mt-1 text-xs text-[#718477]">
                                        Haz clic en el mapa o arrastra el
                                        marcador para actualizar las
                                        coordenadas.
                                    </p>
                                </div>

                                <MapMarkerMap
                                    :geometry="selectedZone.geometry"
                                    :map-image="selectedZone.map_image"
                                    :map-image-bounds="
                                        selectedZone.map_image_bounds
                                    "
                                    :marker-image="thumbnailPreviewUrl"
                                    v-model:latitude="form.latitude"
                                    v-model:longitude="form.longitude"
                                />

                                <div
                                    class="flex items-center gap-3 rounded-xl border border-[#DCE7DF] p-4"
                                >
                                    <div
                                        class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#DCE7DF] bg-[#F7FAF8]"
                                    >
                                        <img
                                            v-if="thumbnailPreviewUrl"
                                            :src="thumbnailPreviewUrl"
                                            alt="Miniatura del marcador"
                                            class="h-full w-full object-cover"
                                        />
                                        <Leaf
                                            v-else
                                            class="h-5 w-5 text-[#84958A]"
                                        />
                                    </div>

                                    <div>
                                        <p
                                            class="text-sm font-medium text-[#244334]"
                                        >
                                            Marcador de la especie
                                        </p>
                                        <p class="mt-1 text-xs text-[#718477]">
                                            {{
                                                thumbnailPreviewUrl
                                                    ? 'Se utiliza la miniatura como icono del marcador.'
                                                    : 'No hay una miniatura disponible.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-xl border border-dashed border-[#DCE7DF] p-8 text-center"
                            >
                                <MapPin
                                    class="mx-auto h-6 w-6 text-[#84958A]"
                                />
                                <p
                                    class="mt-3 text-sm font-medium text-[#244334]"
                                >
                                    Sin zona seleccionada
                                </p>
                                <p class="mt-1 text-xs text-[#718477]">
                                    Selecciona una zona para mostrar su mapa.
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="location_name" class="form-label">
                                    Nombre de la ubicación
                                </label>
                                <input
                                    id="location_name"
                                    v-model="form.location_name"
                                    type="text"
                                    placeholder="Ej. Área de felinos"
                                    class="form-input"
                                    :class="{
                                        'form-input-error':
                                            form.errors.location_name,
                                    }"
                                />
                                <p
                                    v-if="form.errors.location_name"
                                    class="form-error"
                                >
                                    {{ form.errors.location_name }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="form-field">
                                    <label for="latitude" class="form-label">
                                        Latitud
                                    </label>
                                    <input
                                        id="latitude"
                                        :value="
                                            form.latitude !== null
                                                ? form.latitude.toFixed(7)
                                                : ''
                                        "
                                        type="text"
                                        readonly
                                        placeholder="Selecciona un punto en el mapa"
                                        class="form-input bg-[#F2F7F4]"
                                    />
                                    <p
                                        v-if="form.errors.latitude"
                                        class="form-error"
                                    >
                                        {{ form.errors.latitude }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="longitude" class="form-label">
                                        Longitud
                                    </label>
                                    <input
                                        id="longitude"
                                        :value="
                                            form.longitude !== null
                                                ? form.longitude.toFixed(7)
                                                : ''
                                        "
                                        type="text"
                                        readonly
                                        placeholder="Selecciona un punto en el mapa"
                                        class="form-input bg-[#F2F7F4]"
                                    />
                                    <p
                                        v-if="form.errors.longitude"
                                        class="form-error"
                                    >
                                        {{ form.errors.longitude }}
                                    </p>
                                </div>
                            </div>

                            <div class="form-field">
                                <label
                                    for="location_description"
                                    class="form-label"
                                >
                                    Descripción de la ubicación
                                </label>
                                <textarea
                                    id="location_description"
                                    v-model="form.location_description"
                                    rows="4"
                                    placeholder="Describe la ubicación dentro del zoológico..."
                                    class="form-textarea"
                                    :class="{
                                        'form-textarea-error':
                                            form.errors.location_description,
                                    }"
                                />
                                <p
                                    v-if="form.errors.location_description"
                                    class="form-error"
                                >
                                    {{ form.errors.location_description }}
                                </p>
                            </div>

                            <div
                                class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                            >
                                <Info
                                    class="mt-0.5 h-5 w-5 shrink-0 text-[#187744]"
                                />
                                <p class="text-sm text-[#718477]">
                                    Al cambiar la zona se reinician las
                                    coordenadas para que puedas seleccionar una
                                    nueva ubicación en el mapa.
                                </p>
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <!-- ACCIONES -->
                <div
                    class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <div>
                        <AdminButton
                            v-if="activeTab !== 'information'"
                            type="button"
                            variant="secondary"
                            :icon="ArrowLeft"
                            @click="previousTab"
                        >
                            Anterior
                        </AdminButton>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <CancelButton
                            v-if="activeTab === 'information'"
                            :href="admin.species.index().url"
                            :icon="ArrowLeft"
                            label="Cancelar"
                        />

                        <AdminButton
                            v-if="activeTab !== 'location'"
                            type="button"
                            variant="primary"
                            :icon="ArrowRight"
                            @click="nextTab"
                        >
                            Siguiente
                        </AdminButton>

                        <AdminButton
                            v-else
                            type="submit"
                            variant="primary"
                            :icon="Save"
                            :loading="form.processing || compressing"
                            :loading-text="
                                compressing
                                    ? 'Comprimiendo imágenes...'
                                    : 'Guardando...'
                            "
                        >
                            Actualizar especie
                        </AdminButton>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
