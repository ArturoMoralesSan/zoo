<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Image as ImageIcon,
    Info,
    Leaf,
    MapPin,
    Save,
    Tags,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import MapMarkerMap from '@/components/admin/MapMarkerMap.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

const props = defineProps<{
    categories: Category[];
    tags: Tag[];
    zones: Zone[];
}>();

const form = useForm({
    species_category_id: '',
    common_name: '',
    scientific_name: '',
    description: '',
    habitat: '',
    origin: '',
    diet: '',
    conservation_status: '',
    is_active: true,
    tags: [] as number[],
    main_image: null as File | null,
    thumbnail_image: null as File | null,
    gallery_images: [] as File[],
    zone_id: '',
    location_name: '',
    latitude: null as number | null,
    longitude: null as number | null,
    location_description: '',
});

const activeTab = ref('information');

const tabs = [
    { id: 'information', name: 'Información', icon: Info },
    { id: 'tags', name: 'Etiquetas', icon: Tags },
    { id: 'images', name: 'Imágenes', icon: ImageIcon },
    { id: 'location', name: 'Ubicación', icon: MapPin },
];

const selectedZone = computed<Zone | null>(() => {
    return (
        props.zones.find(
            (zone) => zone.id === Number(form.zone_id),
        ) ?? null
    );
});

const thumbnailPreviewUrl = ref<string | null>(null);
const thumbnailObjectUrl = ref<string | null>(null);

const mainPreviewUrl = ref<string | null>(null);
const mainObjectUrl = ref<string | null>(null);

const galleryPreviewUrls = ref<string[]>([]);
const galleryObjectUrls = ref<string[]>([]);

watch(
    () => form.zone_id,
    () => {
        form.latitude = null;
        form.longitude = null;
    },
);

const setMainImage = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (mainObjectUrl.value) {
        URL.revokeObjectURL(mainObjectUrl.value);
        mainObjectUrl.value = null;
    }

    form.main_image = file;

    if (file) {
        const objectUrl = URL.createObjectURL(file);
        mainObjectUrl.value = objectUrl;
        mainPreviewUrl.value = objectUrl;
    } else {
        mainPreviewUrl.value = null;
    }
};

const setThumbnailImage = (event: Event) => {
    const target = event.target as HTMLInputElement;

    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(thumbnailObjectUrl.value);
        thumbnailObjectUrl.value = null;
    }

    const file = target.files?.[0] ?? null;
    form.thumbnail_image = file;

    if (file) {
        const objectUrl = URL.createObjectURL(file);
        thumbnailObjectUrl.value = objectUrl;
        thumbnailPreviewUrl.value = objectUrl;
    } else {
        thumbnailPreviewUrl.value = null;
    }
};

const setGalleryImages = (event: Event) => {
    const target = event.target as HTMLInputElement;

    galleryObjectUrls.value.forEach((url) => URL.revokeObjectURL(url));

    galleryObjectUrls.value = [];
    galleryPreviewUrls.value = [];

    const files = target.files ? Array.from(target.files) : [];
    form.gallery_images = files;

    files.forEach((file) => {
        const objectUrl = URL.createObjectURL(file);
        galleryObjectUrls.value.push(objectUrl);
        galleryPreviewUrls.value.push(objectUrl);
    });
};

const nextTab = () => {
    const currentIndex = tabs.findIndex(
        (tab) => tab.id === activeTab.value,
    );

    if (currentIndex < tabs.length - 1) {
        activeTab.value = tabs[currentIndex + 1].id;
    }
};

const previousTab = () => {
    const currentIndex = tabs.findIndex(
        (tab) => tab.id === activeTab.value,
    );

    if (currentIndex > 0) {
        activeTab.value = tabs[currentIndex - 1].id;
    }
};

const goToTab = (tabId: string) => {
    activeTab.value = tabId;
};

const submit = () => {
    form.post(admin.species.store().url, {
        forceFormData: true,
    });
};

onBeforeUnmount(() => {
    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(thumbnailObjectUrl.value);
    }

    if (mainObjectUrl.value) {
        URL.revokeObjectURL(mainObjectUrl.value);
    }

    galleryObjectUrls.value.forEach((url) => URL.revokeObjectURL(url));
});
</script>

<template>
    <Head title="Nueva especie" />

    <div class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5">
        <PageHeader
            title="Nueva especie"
            description="Registra una nueva especie en el zoológico."
            section="Catálogo"
            :icon="Leaf"
            :action-icon="ArrowLeft"
            action-label="Volver a especies"
            :action-href="admin.species.index().url"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <!-- Navegación de pestañas -->
            <div class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white">
                <div class="border-b border-[#DCE7DF] px-4 pt-4 sm:px-6 sm:pt-6">
                    <div class="flex gap-2 overflow-x-auto pb-0.5">
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
                            <span class="text-xs opacity-70">{{ index + 1 }}.</span>
                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <!-- INFORMACIÓN -->
                    <AdminCard
                        v-if="activeTab === 'information'"
                        title="Información de la especie"
                        description="Información principal y características de la especie."
                        :icon="Info"
                    >
                        <div class="flex flex-col gap-5">
                            <div class="form-field">
                                <label for="species_category_id" class="form-label">
                                    Categoría <span class="form-required">*</span>
                                </label>

                                <select
                                    id="species_category_id"
                                    v-model="form.species_category_id"
                                    class="form-select"
                                    :class="{
                                        'form-select-error': form.errors.species_category_id,
                                    }"
                                >
                                    <option value="">Selecciona una categoría</option>
                                    <option
                                        v-for="category in props.categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>

                                <p v-if="form.errors.species_category_id" class="form-error">
                                    {{ form.errors.species_category_id }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="form-field">
                                    <label for="common_name" class="form-label">
                                        Nombre común <span class="form-required">*</span>
                                    </label>

                                    <input
                                        id="common_name"
                                        v-model="form.common_name"
                                        type="text"
                                        placeholder="Ej. Jaguar"
                                        class="form-input"
                                        :class="{
                                            'form-input-error': form.errors.common_name,
                                        }"
                                    />

                                    <p v-if="form.errors.common_name" class="form-error">
                                        {{ form.errors.common_name }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="scientific_name" class="form-label">
                                        Nombre científico
                                    </label>

                                    <input
                                        id="scientific_name"
                                        v-model="form.scientific_name"
                                        type="text"
                                        placeholder="Ej. Panthera onca"
                                        class="form-input italic"
                                        :class="{
                                            'form-input-error': form.errors.scientific_name,
                                        }"
                                    />

                                    <p v-if="form.errors.scientific_name" class="form-error">
                                        {{ form.errors.scientific_name }}
                                    </p>
                                </div>
                            </div>

                            <div class="form-field">
                                <label for="description" class="form-label">
                                    Descripción
                                </label>

                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    placeholder="Describe las características principales de la especie..."
                                    class="form-textarea"
                                    :class="{
                                        'form-textarea-error': form.errors.description,
                                    }"
                                />

                                <p v-if="form.errors.description" class="form-error">
                                    {{ form.errors.description }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="form-field">
                                    <label for="habitat" class="form-label">Hábitat</label>

                                    <input
                                        id="habitat"
                                        v-model="form.habitat"
                                        type="text"
                                        placeholder="Ej. Selva tropical"
                                        class="form-input"
                                        :class="{
                                            'form-input-error': form.errors.habitat,
                                        }"
                                    />

                                    <p v-if="form.errors.habitat" class="form-error">
                                        {{ form.errors.habitat }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="origin" class="form-label">Origen</label>

                                    <input
                                        id="origin"
                                        v-model="form.origin"
                                        type="text"
                                        placeholder="Ej. América"
                                        class="form-input"
                                        :class="{
                                            'form-input-error': form.errors.origin,
                                        }"
                                    />

                                    <p v-if="form.errors.origin" class="form-error">
                                        {{ form.errors.origin }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="diet" class="form-label">Dieta</label>

                                    <input
                                        id="diet"
                                        v-model="form.diet"
                                        type="text"
                                        placeholder="Ej. Carnívora"
                                        class="form-input"
                                        :class="{
                                            'form-input-error': form.errors.diet,
                                        }"
                                    />

                                    <p v-if="form.errors.diet" class="form-error">
                                        {{ form.errors.diet }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="conservation_status" class="form-label">
                                        Estado de conservación
                                    </label>

                                    <input
                                        id="conservation_status"
                                        v-model="form.conservation_status"
                                        type="text"
                                        placeholder="Ej. Vulnerable"
                                        class="form-input"
                                        :class="{
                                            'form-input-error': form.errors.conservation_status,
                                        }"
                                    />

                                    <p v-if="form.errors.conservation_status" class="form-error">
                                        {{ form.errors.conservation_status }}
                                    </p>
                                </div>
                            </div>

                            <div class="border-t border-[#E5ECE7] pt-5">
                                <ActiveSwitch
                                    v-model="form.is_active"
                                    label="Especie activa"
                                    description="Determina si la especie estará disponible en el sistema."
                                    :error="form.errors.is_active"
                                />
                            </div>
                        </div>
                    </AdminCard>

                    <!-- ETIQUETAS -->
                    <AdminCard
                        v-else-if="activeTab === 'tags'"
                        title="Etiquetas de la especie"
                        description="Selecciona las etiquetas que describen a la especie."
                        :icon="Tags"
                    >
                        <div
                            v-if="props.tags.length"
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <label
                                v-for="tag in props.tags"
                                :key="tag.id"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#DCE7DF] bg-white px-4 py-3 transition hover:border-[#187744]/40 hover:bg-[#F2F7F4]"
                                :class="{
                                    'border-[#187744]/40 bg-[#EAF4ED]':
                                        form.tags.includes(tag.id),
                                }"
                            >
                                <input
                                    v-model="form.tags"
                                    type="checkbox"
                                    :value="tag.id"
                                    class="form-checkbox"
                                />

                                <span class="text-sm font-medium text-[#244334]">
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
                                No hay etiquetas activas disponibles.
                            </p>
                        </div>

                        <div
                            v-if="form.tags.length"
                            class="mt-5 flex items-center gap-3 rounded-xl border border-[#DCE7DF] bg-[#F2F7F4] p-4"
                        >
                            <Tags class="h-5 w-5 shrink-0 text-[#187744]" />

                            <p class="text-sm text-[#244334]">
                                <strong>{{ form.tags.length }}</strong>
                                {{
                                    form.tags.length === 1
                                        ? 'etiqueta seleccionada'
                                        : 'etiquetas seleccionadas'
                                }}
                            </p>
                        </div>

                        <p v-if="form.errors.tags" class="form-error mt-3">
                            {{ form.errors.tags }}
                        </p>
                    </AdminCard>

                    <!-- IMÁGENES -->
                    <AdminCard
                        v-else-if="activeTab === 'images'"
                        title="Imágenes de la especie"
                        description="Agrega la imagen principal, la miniatura y las fotografías de la galería."
                        :icon="ImageIcon"
                    >
                        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <!-- Imagen principal -->
                            <div class="rounded-xl border border-[#DCE7DF] bg-white p-4 sm:p-5">
                                <label for="main_image" class="form-label">
                                    Imagen principal
                                </label>

                                <div
                                    v-if="mainPreviewUrl"
                                    class="my-4 overflow-hidden rounded-xl border border-[#DCE7DF]"
                                >
                                    <img
                                        :src="mainPreviewUrl"
                                        alt="Vista previa de la imagen principal"
                                        class="h-48 w-full object-cover"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="my-4 flex h-48 flex-col items-center justify-center rounded-xl border border-dashed border-[#DCE7DF] bg-[#F7FAF8]"
                                >
                                    <ImageIcon class="h-7 w-7 text-[#84958A]" />

                                    <p class="mt-2 text-sm text-[#718477]">
                                        Selecciona una imagen
                                    </p>
                                </div>

                                <input
                                    id="main_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="form-input text-sm file:mr-4 file:rounded-md file:border-0 file:bg-[#EAF4ED] file:px-3 file:py-2 file:font-medium file:text-[#187744]"
                                    @change="setMainImage"
                                />

                                <p class="form-help">
                                    Formatos: JPG, JPEG, PNG o WEBP.
                                </p>

                                <p v-if="form.errors.main_image" class="form-error">
                                    {{ form.errors.main_image }}
                                </p>
                            </div>

                            <!-- Miniatura -->
                            <div class="rounded-xl border border-[#DCE7DF] bg-white p-4 sm:p-5">
                                <label for="thumbnail_image" class="form-label">
                                    Miniatura
                                </label>

                                <div class="my-4 flex min-h-48 items-center justify-center rounded-xl bg-[#F7FAF8] p-4">
                                    <div v-if="thumbnailPreviewUrl" class="text-center">
                                        <div class="mx-auto h-32 w-32 overflow-hidden rounded-full border-4 border-white shadow-sm">
                                            <img
                                                :src="thumbnailPreviewUrl"
                                                alt="Vista previa de la miniatura"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>

                                        <p class="mt-2 text-xs text-[#718477]">
                                            Miniatura seleccionada
                                        </p>
                                    </div>

                                    <div v-else class="text-center">
                                        <ImageIcon class="mx-auto h-7 w-7 text-[#84958A]" />

                                        <p class="mt-2 text-sm text-[#718477]">
                                            Selecciona una miniatura
                                        </p>
                                    </div>
                                </div>

                                <input
                                    id="thumbnail_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="form-input text-sm file:mr-4 file:rounded-md file:border-0 file:bg-[#EAF4ED] file:px-3 file:py-2 file:font-medium file:text-[#187744]"
                                    @change="setThumbnailImage"
                                />

                                <p class="form-help">
                                    También se utilizará como icono del marker en el mapa.
                                </p>

                                <p v-if="form.errors.thumbnail_image" class="form-error">
                                    {{ form.errors.thumbnail_image }}
                                </p>
                            </div>

                            <!-- Galería -->
                            <div class="rounded-xl border border-[#DCE7DF] bg-white p-4 sm:col-span-2 sm:p-5">
                                <label for="gallery_images" class="form-label">
                                    Galería de imágenes
                                </label>

                                <div
                                    v-if="galleryPreviewUrls.length"
                                    class="my-4 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                                >
                                    <div
                                        v-for="(url, index) in galleryPreviewUrls"
                                        :key="`${url}-${index}`"
                                        class="aspect-square overflow-hidden rounded-lg border border-[#DCE7DF]"
                                    >
                                        <img
                                            :src="url"
                                            alt="Vista previa de imagen de galería"
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="my-4 rounded-xl border border-dashed border-[#DCE7DF] p-6 text-center"
                                >
                                    <ImageIcon class="mx-auto h-6 w-6 text-[#84958A]" />

                                    <p class="mt-2 text-sm text-[#718477]">
                                        Selecciona varias imágenes para la galería.
                                    </p>
                                </div>

                                <input
                                    id="gallery_images"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    multiple
                                    class="form-input text-sm file:mr-4 file:rounded-md file:border-0 file:bg-[#EAF4ED] file:px-3 file:py-2 file:font-medium file:text-[#187744]"
                                    @change="setGalleryImages"
                                />

                                <p class="form-help">
                                    Puedes seleccionar varias imágenes a la vez.
                                </p>

                                <p
                                    v-if="form.gallery_images.length"
                                    class="mt-2 text-sm font-medium text-[#187744]"
                                >
                                    {{ form.gallery_images.length }}
                                    {{
                                        form.gallery_images.length === 1
                                            ? 'imagen seleccionada'
                                            : 'imágenes seleccionadas'
                                    }}.
                                </p>

                                <p v-if="form.errors.gallery_images" class="form-error">
                                    {{ form.errors.gallery_images }}
                                </p>
                            </div>
                        </div>
                    </AdminCard>

                    <!-- UBICACIÓN -->
                    <AdminCard
                        v-else-if="activeTab === 'location'"
                        title="Ubicación de la especie"
                        description="Define dónde se encuentra la especie dentro del zoológico."
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
                                        'form-select-error': form.errors.zone_id,
                                    }"
                                >
                                    <option value="">Sin zona asignada</option>

                                    <option
                                        v-for="zone in props.zones"
                                        :key="zone.id"
                                        :value="zone.id"
                                    >
                                        {{ zone.name }}
                                    </option>
                                </select>

                                <p v-if="form.errors.zone_id" class="form-error">
                                    {{ form.errors.zone_id }}
                                </p>

                                <div
                                    v-if="selectedZone && !selectedZone.map_image"
                                    class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3"
                                >
                                    <p class="text-xs text-amber-800">
                                        Esta zona no tiene un plano configurado. Podrás colocar la especie usando el área delimitada de la zona.
                                    </p>
                                </div>
                            </div>

                            <div v-if="selectedZone" class="flex flex-col gap-4">
                                <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                                    <div class="flex items-center gap-2">
                                        <MapPin class="h-4 w-4 text-[#187744]" />

                                        <h3 class="text-sm font-semibold text-[#244334]">
                                            Ubicación en el mapa
                                        </h3>
                                    </div>

                                    <p class="mt-1 text-xs text-[#718477]">
                                        Haz clic en el mapa o arrastra el marcador para seleccionar las coordenadas.
                                    </p>
                                </div>

                                <MapMarkerMap
                                    :geometry="selectedZone.geometry"
                                    :map-image="selectedZone.map_image"
                                    :map-image-bounds="selectedZone.map_image_bounds"
                                    :marker-image="thumbnailPreviewUrl"
                                    v-model:latitude="form.latitude"
                                    v-model:longitude="form.longitude"
                                />

                                <div class="flex items-center gap-3 rounded-xl border border-[#DCE7DF] bg-white p-4">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-[#DCE7DF] bg-[#F7FAF8]">
                                        <img
                                            v-if="thumbnailPreviewUrl"
                                            :src="thumbnailPreviewUrl"
                                            alt="Miniatura del marcador"
                                            class="h-full w-full object-cover"
                                        />

                                        <Leaf v-else class="h-5 w-5 text-[#84958A]" />
                                    </div>

                                    <div>
                                        <p class="text-sm font-medium text-[#244334]">
                                            Marcador de la especie
                                        </p>

                                        <p class="mt-1 text-xs text-[#718477]">
                                            Se utilizará la miniatura seleccionada como icono del marcador.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-xl border border-dashed border-[#DCE7DF] p-8 text-center"
                            >
                                <MapPin class="mx-auto h-6 w-6 text-[#84958A]" />

                                <p class="mt-3 text-sm font-medium text-[#244334]">
                                    No hay una zona seleccionada
                                </p>

                                <p class="mt-1 text-xs text-[#718477]">
                                    Selecciona una zona para mostrar su mapa.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="form-field md:col-span-2">
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
                                            'form-input-error': form.errors.location_name,
                                        }"
                                    />

                                    <p v-if="form.errors.location_name" class="form-error">
                                        {{ form.errors.location_name }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="latitude" class="form-label">
                                        Latitud
                                    </label>

                                    <input
                                        id="latitude"
                                        :value="form.latitude !== null ? form.latitude.toFixed(7) : ''"
                                        type="text"
                                        readonly
                                        placeholder="Selecciona un punto en el mapa"
                                        class="form-input bg-[#F2F7F4]"
                                    />

                                    <p v-if="form.errors.latitude" class="form-error">
                                        {{ form.errors.latitude }}
                                    </p>
                                </div>

                                <div class="form-field">
                                    <label for="longitude" class="form-label">
                                        Longitud
                                    </label>

                                    <input
                                        id="longitude"
                                        :value="form.longitude !== null ? form.longitude.toFixed(7) : ''"
                                        type="text"
                                        readonly
                                        placeholder="Selecciona un punto en el mapa"
                                        class="form-input bg-[#F2F7F4]"
                                    />

                                    <p v-if="form.errors.longitude" class="form-error">
                                        {{ form.errors.longitude }}
                                    </p>
                                </div>

                                <div class="form-field md:col-span-2">
                                    <label for="location_description" class="form-label">
                                        Descripción de la ubicación
                                    </label>

                                    <textarea
                                        id="location_description"
                                        v-model="form.location_description"
                                        rows="4"
                                        placeholder="Describe la ubicación dentro del zoológico..."
                                        class="form-textarea"
                                        :class="{
                                            'form-textarea-error': form.errors.location_description,
                                        }"
                                    />

                                    <p v-if="form.errors.location_description" class="form-error">
                                        {{ form.errors.location_description }}
                                    </p>
                                </div>
                            </div>

                            <div class="form-info">
                                <Info class="form-info-icon" />

                                <p class="form-info-text">
                                    Las coordenadas se actualizan al seleccionar un punto en el mapa. El mapa funciona como referencia visual para ubicar la especie.
                                </p>
                            </div>
                        </div>
                    </AdminCard>
                </div>

                <!-- Acciones de navegación y envío -->
                <div class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
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
                            :loading="form.processing"
                            loading-text="Guardando..."
                        >
                            Crear especie
                        </AdminButton>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
