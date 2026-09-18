<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import {
    computed,
    onBeforeUnmount,
    ref,
    watch,
} from 'vue';

import Swal from 'sweetalert2';

import MapMarkerMap from '@/components/admin/MapMarkerMap.vue';

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
    type: 'main' | 'thumbnail' | 'card' | 'gallery' | string;
    path: string;
    alt_text: string | null;
    is_active: boolean;
    sort_order: number;
}

interface Species {
    id: number;
    species_category_id: number | null;

    common_name: string;
    scientific_name: string | null;
    description: string | null;

    habitat: string | null;
    origin: string | null;
    diet: string | null;
    conservation_status: string | null;

    is_active: boolean;

    tags: SpeciesTag[];
    locations: SpeciesLocation[];

    /**
     * ----------------------------------------------------------------------
     * Imágenes existentes
     * ----------------------------------------------------------------------
     *
     * Las imágenes NO están directamente en:
     *
     * species.thumbnail_image
     *
     * sino en:
     *
     * species.images
     */
    images: SpeciesImage[];

    model_name: string | null;
    model_url: string | null;
    model_format: string | null;
    model_description: string | null;
}

const props = defineProps<{
    species: Species;
    categories: Category[];
    tags: Tag[];
    zones: Zone[];
}>();

/**
 * --------------------------------------------------------------------------
 * Tabs
 * --------------------------------------------------------------------------
 */

const activeTab = ref('information');

const tabs = [
    {
        id: 'information',
        name: 'Información',
    },
    {
        id: 'tags',
        name: 'Etiquetas',
    },
    {
        id: 'images',
        name: 'Imágenes',
    },
    {
        id: 'model',
        name: 'Modelo 3D',
    },
    {
        id: 'location',
        name: 'Ubicación',
    },
];

/**
 * --------------------------------------------------------------------------
 * Ubicación existente
 * --------------------------------------------------------------------------
 */

const currentLocation =
    props.species.locations?.[0] ?? null;

const initialZoneId =
    currentLocation?.zone_id !== null &&
    currentLocation?.zone_id !== undefined
        ? String(currentLocation.zone_id)
        : '';

const initialLatitude =
    currentLocation?.latitude !== null &&
    currentLocation?.latitude !== undefined
        ? Number(currentLocation.latitude)
        : null;

const initialLongitude =
    currentLocation?.longitude !== null &&
    currentLocation?.longitude !== undefined
        ? Number(currentLocation.longitude)
        : null;

/**
 * --------------------------------------------------------------------------
 * Imágenes existentes
 * --------------------------------------------------------------------------
 */

const existingImages = computed<SpeciesImage[]>(() => {
    return props.species.images ?? [];
});

/**
 * Convierte una ruta almacenada en Laravel
 * en una URL utilizable por el navegador.
 */
const imageUrl = (
    path: string | null | undefined,
): string | null => {
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

/**
 * Imagen principal existente.
 */
const existingMainImage = computed<SpeciesImage | null>(() => {
    return (
        existingImages.value.find(
            (image) =>
                image.type === 'main' &&
                image.is_active,
        ) ?? null
    );
});

/**
 * Miniatura existente.
 */
const existingThumbnailImage =
    computed<SpeciesImage | null>(() => {
        return (
            existingImages.value.find(
                (image) =>
                    image.type === 'thumbnail' &&
                    image.is_active,
            ) ?? null
        );
    });

/**
 * Imagen de tarjeta existente.
 */
const existingCardImage = computed<SpeciesImage | null>(() => {
    return (
        existingImages.value.find(
            (image) =>
                image.type === 'card' &&
                image.is_active,
        ) ?? null
    );
});

/**
 * Galería existente.
 */
const existingGalleryImages =
    computed<SpeciesImage[]>(() => {
        return existingImages.value
            .filter(
                (image) =>
                    image.type === 'gallery' &&
                    image.is_active,
            )
            .sort(
                (a, b) =>
                    a.sort_order - b.sort_order,
            );
    });

/**
 * URLs de las imágenes existentes.
 */
const existingMainImageUrl = computed<string | null>(() => {
    return imageUrl(
        existingMainImage.value?.path,
    );
});

const existingThumbnailUrl =
    computed<string | null>(() => {
        return imageUrl(
            existingThumbnailImage.value?.path,
        );
    });

const existingCardImageUrl = computed<string | null>(() => {
    return imageUrl(
        existingCardImage.value?.path,
    );
});

/**
 * --------------------------------------------------------------------------
 * Formulario
 * --------------------------------------------------------------------------
 */

const form = useForm({
    species_category_id:
        props.species.species_category_id !== null
            ? String(
                  props.species.species_category_id,
              )
            : '',

    common_name:
        props.species.common_name ?? '',

    scientific_name:
        props.species.scientific_name ?? '',

    description:
        props.species.description ?? '',

    habitat:
        props.species.habitat ?? '',

    origin:
        props.species.origin ?? '',

    diet:
        props.species.diet ?? '',

    conservation_status:
        props.species.conservation_status ?? '',

    is_active:
        props.species.is_active,

    tags:
        props.species.tags?.map(
            (tag) => tag.id,
        ) ?? [],

    /**
     * Estos campos representan únicamente NUEVOS archivos.
     */
    main_image: null as File | null,

    thumbnail_image:
        null as File | null,

    card_image: null as File | null,

    gallery_images: [] as File[],

    model_name:
        props.species.model_name ?? '',

    model_file:
        null as File | null,

    model_url:
        props.species.model_url ?? '',

    model_format:
        props.species.model_format ?? '',

    model_description:
        props.species.model_description ?? '',

    zone_id: initialZoneId,

    location_name:
        currentLocation?.name ?? '',

    latitude: initialLatitude,

    longitude: initialLongitude,

    location_description:
        currentLocation?.description ?? '',
});

/**
 * --------------------------------------------------------------------------
 * Formulario independiente para eliminar imágenes
 * --------------------------------------------------------------------------
 */

const deleteImageForm = useForm({});

/**
 * Imagen que actualmente se está eliminando.
 *
 * Se utiliza para no permitir múltiples eliminaciones
 * simultáneas.
 */
const deletingImageId = ref<number | null>(null);

/**
 * --------------------------------------------------------------------------
 * Previews de imágenes
 * --------------------------------------------------------------------------
 */

const thumbnailPreviewUrl =
    ref<string | null>(
        existingThumbnailUrl.value,
    );

const thumbnailObjectUrl =
    ref<string | null>(null);

const mainPreviewUrl =
    ref<string | null>(
        existingMainImageUrl.value,
    );

const mainObjectUrl =
    ref<string | null>(null);

const cardPreviewUrl =
    ref<string | null>(
        existingCardImageUrl.value,
    );

const cardObjectUrl =
    ref<string | null>(null);

const galleryPreviewUrls =
    ref<string[]>([]);

const galleryObjectUrls =
    ref<string[]>([]);

/**
 * --------------------------------------------------------------------------
 * Zona seleccionada
 * --------------------------------------------------------------------------
 */

const selectedZone = computed(() => {
    return (
        props.zones.find(
            (zone) =>
                zone.id ===
                Number(form.zone_id),
        ) ?? null
    );
});

/**
 * --------------------------------------------------------------------------
 * Cambio de zona
 * --------------------------------------------------------------------------
 */

watch(
    () => form.zone_id,
    (newZone, oldZone) => {
        if (newZone !== oldZone) {
            form.latitude = null;
            form.longitude = null;
        }
    },
);

/**
 * --------------------------------------------------------------------------
 * Eliminar imagen existente de galería
 * --------------------------------------------------------------------------
 */

const deleteGalleryImage = async (
    image: SpeciesImage,
) => {
    if (
        deleteImageForm.processing ||
        deletingImageId.value !== null
    ) {
        return;
    }

    const result = await Swal.fire({
        title: '¿Eliminar imagen?',
        html: `
            <p>
                La imagen se eliminará de la galería.
            </p>
            <p class="mt-2 text-sm text-gray-500">
                Esta acción no se puede deshacer.
            </p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    deletingImageId.value = image.id;

    deleteImageForm.delete(
        admin.species.images.destroy({
            species: props.species.id,
            image: image.id,
        }).url,
        {
            preserveScroll: true,

            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Imagen eliminada',
                    text: 'La imagen fue eliminada correctamente de la galería.',
                    confirmButtonText: 'Aceptar',
                    timer: 1800,
                    timerProgressBar: true,
                });
            },

            onError: () => {
                Swal.fire({
                    icon: 'error',
                    title: 'No fue posible eliminar',
                    text: 'Ocurrió un error al eliminar la imagen. Inténtalo nuevamente.',
                    confirmButtonText: 'Aceptar',
                });
            },

            onFinish: () => {
                deletingImageId.value = null;
            },
        },
    );
};

/**
 * --------------------------------------------------------------------------
 * Imágenes
 * --------------------------------------------------------------------------
 */

/**
 * Imagen principal.
 */
const setMainImage = (event: Event) => {
    const target =
        event.target as HTMLInputElement;

    const file =
        target.files?.[0] ?? null;

    if (mainObjectUrl.value) {
        URL.revokeObjectURL(
            mainObjectUrl.value,
        );

        mainObjectUrl.value = null;
    }

    form.main_image = file;

    if (file) {
        const objectUrl =
            URL.createObjectURL(file);

        mainObjectUrl.value =
            objectUrl;

        mainPreviewUrl.value =
            objectUrl;

        return;
    }

    mainPreviewUrl.value =
        existingMainImageUrl.value;
};

/**
 * Miniatura.
 */
const setThumbnailImage = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(
            thumbnailObjectUrl.value,
        );

        thumbnailObjectUrl.value = null;
    }

    const file =
        target.files?.[0] ?? null;

    form.thumbnail_image = file;

    if (file) {
        const objectUrl =
            URL.createObjectURL(file);

        thumbnailObjectUrl.value =
            objectUrl;

        thumbnailPreviewUrl.value =
            objectUrl;

        return;
    }

    thumbnailPreviewUrl.value =
        existingThumbnailUrl.value;
};

/**
 * Imagen de tarjeta.
 */
const setCardImage = (event: Event) => {
    const target =
        event.target as HTMLInputElement;

    const file =
        target.files?.[0] ?? null;

    if (cardObjectUrl.value) {
        URL.revokeObjectURL(
            cardObjectUrl.value,
        );

        cardObjectUrl.value = null;
    }

    form.card_image = file;

    if (file) {
        const objectUrl =
            URL.createObjectURL(file);

        cardObjectUrl.value =
            objectUrl;

        cardPreviewUrl.value =
            objectUrl;

        return;
    }

    cardPreviewUrl.value =
        existingCardImageUrl.value;
};

/**
 * Galería.
 *
 * Las imágenes nuevas se agregan a la galería
 * existente. No reemplazan las existentes.
 */
const setGalleryImages = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    galleryObjectUrls.value.forEach(
        (url) => {
            URL.revokeObjectURL(url);
        },
    );

    galleryObjectUrls.value = [];
    galleryPreviewUrls.value = [];

    const files = target.files
        ? Array.from(target.files)
        : [];

    form.gallery_images = files;

    files.forEach((file) => {
        const objectUrl =
            URL.createObjectURL(file);

        galleryObjectUrls.value.push(
            objectUrl,
        );

        galleryPreviewUrls.value.push(
            objectUrl,
        );
    });
};

/**
 * --------------------------------------------------------------------------
 * Modelo 3D
 * --------------------------------------------------------------------------
 */

const setModelFile = (event: Event) => {
    const target =
        event.target as HTMLInputElement;

    const file =
        target.files?.[0] ?? null;

    form.model_file = file;

    if (file) {
        const extension = file.name
            .split('.')
            .pop()
            ?.toLowerCase();

        if (
            extension === 'glb' ||
            extension === 'gltf' ||
            extension === 'usdz'
        ) {
            form.model_format =
                extension;
        }

        if (!form.model_name) {
            form.model_name =
                file.name.replace(
                    /\.[^/.]+$/,
                    '',
                );
        }
    }
};

/**
 * --------------------------------------------------------------------------
 * Navegación
 * --------------------------------------------------------------------------
 */

const nextTab = () => {
    const currentIndex =
        tabs.findIndex(
            (tab) =>
                tab.id ===
                activeTab.value,
        );

    if (
        currentIndex <
        tabs.length - 1
    ) {
        activeTab.value =
            tabs[
                currentIndex + 1
            ].id;
    }
};

const previousTab = () => {
    const currentIndex =
        tabs.findIndex(
            (tab) =>
                tab.id ===
                activeTab.value,
        );

    if (currentIndex > 0) {
        activeTab.value =
            tabs[
                currentIndex - 1
            ].id;
    }
};

const goToTab = (
    tabId: string,
) => {
    activeTab.value = tabId;
};

/**
 * --------------------------------------------------------------------------
 * Submit
 * --------------------------------------------------------------------------
 */

const submit = () => {
    form.put(
        admin.species.update(
            props.species.id,
        ).url,
        {
            forceFormData: true,
        },
    );
};

/**
 * --------------------------------------------------------------------------
 * Limpieza
 * --------------------------------------------------------------------------
 */

onBeforeUnmount(() => {
    if (thumbnailObjectUrl.value) {
        URL.revokeObjectURL(
            thumbnailObjectUrl.value,
        );
    }

    if (mainObjectUrl.value) {
        URL.revokeObjectURL(
            mainObjectUrl.value,
        );
    }

    if (cardObjectUrl.value) {
        URL.revokeObjectURL(
            cardObjectUrl.value,
        );
    }

    galleryObjectUrls.value.forEach(
        (url) => {
            URL.revokeObjectURL(url);
        },
    );
});
</script>

<template>
    <Head
        :title="`Editar ${species.common_name}`"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- HEADER -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-xl font-semibold">
                        Editar especie
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Actualiza la información de
                        <strong>
                            {{ species.common_name }}
                        </strong>
                    </p>
                </div>

                <Link
                    :href="
                        admin.species.index().url
                    "
                    class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <!-- FORM -->

        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <form
                class="flex flex-col"
                @submit.prevent="submit"
            >
                <!-- TABS -->

                <div
                    class="border-b border-sidebar-border px-6 pt-6"
                >
                    <div
                        class="flex gap-2 overflow-x-auto"
                    >
                        <button
                            v-for="(
                                tab, index
                            ) in tabs"
                            :key="tab.id"
                            type="button"
                            class="whitespace-nowrap rounded-t-lg px-4 py-3 text-sm font-medium transition"
                            :class="
                                activeTab ===
                                tab.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            "
                            @click="
                                goToTab(
                                    tab.id,
                                )
                            "
                        >
                            <span
                                class="mr-1.5 opacity-70"
                            >
                                {{ index + 1 }}.
                            </span>

                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <!-- CONTENT -->

                <div class="p-6">
                    <!-- ================================================= -->
                    <!-- INFORMACIÓN -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab ===
                            'information'
                        "
                        class="space-y-6"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Información de la especie
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Información principal de la especie.
                            </p>
                        </div>

                        <!-- CATEGORÍA -->

                        <div>
                            <label
                                for="species_category_id"
                                class="mb-2 block text-sm font-medium"
                            >
                                Categoría
                            </label>

                            <select
                                id="species_category_id"
                                v-model="
                                    form.species_category_id
                                "
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">
                                    Selecciona una categoría
                                </option>

                                <option
                                    v-for="category in props.categories"
                                    :key="
                                        category.id
                                    "
                                    :value="
                                        category.id
                                    "
                                >
                                    {{ category.name }}
                                </option>
                            </select>

                            <p
                                v-if="
                                    form.errors
                                        .species_category_id
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors
                                        .species_category_id
                                }}
                            </p>
                        </div>

                        <!-- NOMBRES -->

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <div>
                                <label
                                    for="common_name"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Nombre común
                                </label>

                                <input
                                    id="common_name"
                                    v-model="
                                        form.common_name
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="
                                        form.errors
                                            .common_name
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors
                                            .common_name
                                    }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="scientific_name"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Nombre científico
                                </label>

                                <input
                                    id="scientific_name"
                                    v-model="
                                        form.scientific_name
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm italic outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="
                                        form.errors
                                            .scientific_name
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors
                                            .scientific_name
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- DESCRIPCIÓN -->

                        <div>
                            <label
                                for="description"
                                class="mb-2 block text-sm font-medium"
                            >
                                Descripción
                            </label>

                            <textarea
                                id="description"
                                v-model="
                                    form.description
                                "
                                rows="5"
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <!-- DATOS -->

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <div>
                                <label
                                    for="habitat"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Hábitat
                                </label>

                                <input
                                    id="habitat"
                                    v-model="
                                        form.habitat
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <div>
                                <label
                                    for="origin"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Origen
                                </label>

                                <input
                                    id="origin"
                                    v-model="
                                        form.origin
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <div>
                                <label
                                    for="diet"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Dieta
                                </label>

                                <input
                                    id="diet"
                                    v-model="
                                        form.diet
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <div>
                                <label
                                    for="conservation_status"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Estado de conservación
                                </label>

                                <input
                                    id="conservation_status"
                                    v-model="
                                        form.conservation_status
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>

                        <!-- ACTIVO -->

                        <label
                            class="flex cursor-pointer items-center gap-3"
                        >
                            <input
                                v-model="
                                    form.is_active
                                "
                                type="checkbox"
                                class="h-4 w-4 rounded border-sidebar-border"
                            />

                            <span
                                class="text-sm font-medium"
                            >
                                Especie activa
                            </span>
                        </label>
                    </div>

                    <!-- ================================================= -->
                    <!-- ETIQUETAS -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab ===
                            'tags'
                        "
                        class="space-y-6"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Etiquetas
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Selecciona las etiquetas de la especie.
                            </p>
                        </div>

                        <div
                            v-if="
                                props.tags.length
                            "
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <label
                                v-for="tag in props.tags"
                                :key="
                                    tag.id
                                "
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border px-4 py-3 transition hover:bg-accent"
                            >
                                <input
                                    v-model="
                                        form.tags
                                    "
                                    type="checkbox"
                                    :value="
                                        tag.id
                                    "
                                    class="h-4 w-4 rounded border-sidebar-border"
                                />

                                <span
                                    class="text-sm"
                                >
                                    {{ tag.name }}
                                </span>
                            </label>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                        >
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                No hay etiquetas activas disponibles.
                            </p>
                        </div>

                        <div
                            v-if="
                                form.tags.length
                            "
                            class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                        >
                            <p class="text-sm">
                                <strong>
                                    {{ form.tags.length }}
                                </strong>

                                {{
                                    form.tags.length ===
                                    1
                                        ? 'etiqueta seleccionada'
                                        : 'etiquetas seleccionadas'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- IMÁGENES -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab ===
                            'images'
                        "
                        class="space-y-6"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Imágenes
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Actualiza las imágenes de la especie.
                                Las imágenes actuales se conservan si no
                                seleccionas nuevas.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <!-- IMAGEN PRINCIPAL -->

                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="main_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Imagen principal
                                </label>

                                <div
                                    v-if="
                                        mainPreviewUrl
                                    "
                                    class="mb-4 overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                                >
                                    <img
                                        :src="
                                            mainPreviewUrl
                                        "
                                        alt="Imagen principal"
                                        class="h-48 w-full object-cover"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="mb-4 flex h-48 items-center justify-center rounded-lg border border-dashed border-sidebar-border bg-muted/30"
                                >
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        No hay imagen principal.
                                    </p>
                                </div>

                                <input
                                    id="main_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                    @change="
                                        setMainImage
                                    "
                                />

                                <p
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    {{
                                        mainObjectUrl
                                            ? 'Nueva imagen seleccionada.'
                                            : 'Imagen actual. Deja vacío para conservarla.'
                                    }}
                                </p>
                            </div>

                            <!-- MINIATURA -->

                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="thumbnail_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Miniatura
                                </label>

                                <div
                                    class="flex min-h-48 items-center justify-center rounded-lg border border-sidebar-border bg-muted/30"
                                >
                                    <div
                                        v-if="
                                            thumbnailPreviewUrl
                                        "
                                        class="flex flex-col items-center gap-3"
                                    >
                                        <div
                                            class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-full border-4 border-background shadow"
                                        >
                                            <img
                                                :src="
                                                    thumbnailPreviewUrl
                                                "
                                                alt="Miniatura de la especie"
                                                class="h-full w-full object-cover"
                                            />
                                        </div>

                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                thumbnailObjectUrl
                                                    ? 'Nueva miniatura seleccionada'
                                                    : 'Miniatura actual'
                                            }}
                                        </p>
                                    </div>

                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        Esta especie no tiene miniatura.
                                    </p>
                                </div>

                                <input
                                    id="thumbnail_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="mt-4 block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                    @change="
                                        setThumbnailImage
                                    "
                                />

                                <p
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Deja vacío para conservar la miniatura
                                    actual.
                                </p>

                                <div
                                    v-if="
                                        thumbnailPreviewUrl
                                    "
                                    class="mt-4 rounded-lg border border-sidebar-border bg-accent/30 p-3"
                                >
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Esta imagen también se utilizará como
                                        icono del marker en el mapa.
                                    </p>
                                </div>
                            </div>

                            <!-- TARJETA -->

                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="card_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Imagen para tarjeta
                                </label>

                                <div
                                    v-if="
                                        cardPreviewUrl
                                    "
                                    class="mb-4 overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                                >
                                    <img
                                        :src="
                                            cardPreviewUrl
                                        "
                                        alt="Imagen de tarjeta"
                                        class="h-48 w-full object-cover"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="mb-4 flex h-48 items-center justify-center rounded-lg border border-dashed border-sidebar-border bg-muted/30"
                                >
                                    <p
                                        class="text-sm text-muted-foreground"
                                    >
                                        No hay imagen de tarjeta.
                                    </p>
                                </div>

                                <input
                                    id="card_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                    @change="
                                        setCardImage
                                    "
                                />

                                <p
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    {{
                                        cardObjectUrl
                                            ? 'Nueva imagen seleccionada.'
                                            : 'Imagen actual. Deja vacío para conservarla.'
                                    }}
                                </p>
                            </div>

                            <!-- GALERÍA -->

                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="gallery_images"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Galería
                                </label>

                                <!-- GALERÍA EXISTENTE -->

                                <div
                                    v-if="
                                        existingGalleryImages.length
                                    "
                                    class="mb-5"
                                >
                                    <p
                                        class="mb-3 text-xs font-medium text-muted-foreground"
                                    >
                                        Galería actual
                                    </p>

                                    <div
                                        class="grid grid-cols-3 gap-2"
                                    >
                                        <div
                                            v-for="image in existingGalleryImages"
                                            :key="
                                                image.id
                                            "
                                            class="group relative aspect-square overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                                        >
                                            <img
                                                :src="
                                                    imageUrl(
                                                        image.path,
                                                    ) ??
                                                    ''
                                                "
                                                :alt="
                                                    image.alt_text ??
                                                    species.common_name
                                                "
                                                class="h-full w-full object-cover"
                                            />

                                            <!-- BOTÓN ELIMINAR -->

                                            <button
                                                type="button"
                                                :disabled="
                                                    deleteImageForm.processing
                                                "
                                                title="Eliminar imagen"
                                                class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-red-600 text-white opacity-0 shadow-md transition hover:bg-red-700 group-hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                @click="
                                                    deleteGalleryImage(
                                                        image,
                                                    )
                                                "
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    class="h-4 w-4"
                                                >
                                                    <path
                                                        d="M3 6h18"
                                                    />
                                                    <path
                                                        d="M8 6V4h8v2"
                                                    />
                                                    <path
                                                        d="M19 6l-1 14H6L5 6"
                                                    />
                                                    <path
                                                        d="M10 11v5"
                                                    />
                                                    <path
                                                        d="M14 11v5"
                                                    />
                                                </svg>
                                            </button>

                                            <!-- INDICADOR DE ELIMINACIÓN -->

                                            <div
                                                v-if="
                                                    deletingImageId ===
                                                    image.id
                                                "
                                                class="absolute inset-0 flex items-center justify-center bg-black/50"
                                            >
                                                <div
                                                    class="h-6 w-6 animate-spin rounded-full border-2 border-white border-t-transparent"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="mb-5 rounded-lg border border-dashed border-sidebar-border p-4 text-center"
                                >
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        No hay imágenes en la galería actual.
                                    </p>
                                </div>

                                <!-- NUEVAS IMÁGENES -->

                                <p
                                    class="mb-3 text-xs font-medium text-muted-foreground"
                                >
                                    Nuevas imágenes
                                </p>

                                <div
                                    v-if="
                                        galleryPreviewUrls.length
                                    "
                                    class="mb-4 grid grid-cols-3 gap-2"
                                >
                                    <div
                                        v-for="(
                                            url, index
                                        ) in galleryPreviewUrls"
                                        :key="
                                            `${url}-${index}`
                                        "
                                        class="aspect-square overflow-hidden rounded-lg border border-primary bg-muted"
                                    >
                                        <img
                                            :src="
                                                url
                                            "
                                            alt="Nueva imagen de galería"
                                            class="h-full w-full object-cover"
                                        />
                                    </div>
                                </div>

                                <input
                                    id="gallery_images"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    multiple
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                    @change="
                                        setGalleryImages
                                    "
                                />

                                <p
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Las imágenes seleccionadas se agregarán a
                                    las existentes. Las imágenes actuales no
                                    se eliminan.
                                </p>

                                <p
                                    v-if="
                                        form.gallery_images
                                            .length
                                    "
                                    class="mt-2 text-xs"
                                >
                                    {{
                                        form
                                            .gallery_images
                                            .length
                                    }}
                                    {{
                                        form.gallery_images
                                            .length === 1
                                            ? 'imagen nueva seleccionada.'
                                            : 'imágenes nuevas seleccionadas.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- MODELO 3D -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab ===
                            'model'
                        "
                        class="space-y-6"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Modelo 3D
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Actualiza el modelo 3D de la especie.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <div>
                                <label
                                    for="model_name"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Nombre del modelo
                                </label>

                                <input
                                    id="model_name"
                                    v-model="
                                        form.model_name
                                    "
                                    type="text"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <div>
                                <label
                                    for="model_format"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Formato
                                </label>

                                <select
                                    id="model_format"
                                    v-model="
                                        form.model_format
                                    "
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">
                                        Selecciona un formato
                                    </option>

                                    <option value="glb">
                                        GLB
                                    </option>

                                    <option value="gltf">
                                        GLTF
                                    </option>

                                    <option value="usdz">
                                        USDZ
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    for="model_file"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Nuevo archivo 3D
                                </label>

                                <input
                                    id="model_file"
                                    type="file"
                                    accept=".glb,.gltf,.usdz"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                    @change="
                                        setModelFile
                                    "
                                />

                                <p
                                    class="mt-2 text-xs text-muted-foreground"
                                >
                                    Deja vacío para conservar el archivo
                                    actual.
                                </p>
                            </div>

                            <div>
                                <label
                                    for="model_url"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    URL del modelo
                                </label>

                                <input
                                    id="model_url"
                                    v-model="
                                        form.model_url
                                    "
                                    type="url"
                                    placeholder="https://..."
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                for="model_description"
                                class="mb-2 block text-sm font-medium"
                            >
                                Descripción
                            </label>

                            <textarea
                                id="model_description"
                                v-model="
                                    form.model_description
                                "
                                rows="4"
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- UBICACIÓN -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab ===
                            'location'
                        "
                        class="space-y-6"
                    >
                        <div>
                            <h2
                                class="text-lg font-semibold"
                            >
                                Ubicación
                            </h2>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Actualiza la ubicación física de la especie
                                dentro del zoológico.
                            </p>
                        </div>

                        <div>
                            <label
                                for="zone_id"
                                class="mb-2 block text-sm font-medium"
                            >
                                Zona del zoológico
                            </label>

                            <select
                                id="zone_id"
                                v-model="
                                    form.zone_id
                                "
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">
                                    Sin zona asignada
                                </option>

                                <option
                                    v-for="zone in props.zones"
                                    :key="
                                        zone.id
                                    "
                                    :value="
                                        zone.id
                                    "
                                >
                                    {{ zone.name }}
                                </option>
                            </select>

                            <p
                                v-if="
                                    form.errors
                                        .zone_id
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors
                                        .zone_id
                                }}
                            </p>

                            <p
                                v-if="
                                    selectedZone &&
                                    !selectedZone.map_image
                                "
                                class="mt-2 text-xs text-amber-600"
                            >
                                Esta zona no tiene un plano configurado.
                                Podrás colocar la especie dentro del área
                                delimitada por la zona.
                            </p>
                        </div>

                        <div
                            v-if="selectedZone"
                            class="space-y-3"
                        >
                            <div>
                                <h3
                                    class="text-sm font-semibold"
                                >
                                    Ubicación en el mapa
                                </h3>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    El plano de la zona se muestra como
                                    referencia. Puedes hacer clic o arrastrar
                                    el marker a cualquier punto dentro de la
                                    zona.
                                </p>
                            </div>

                            <MapMarkerMap
                                :geometry="
                                    selectedZone.geometry
                                "
                                :map-image="
                                    selectedZone.map_image
                                "
                                :map-image-bounds="
                                    selectedZone.map_image_bounds
                                "
                                :marker-image="
                                    thumbnailPreviewUrl
                                "
                                v-model:latitude="
                                    form.latitude
                                "
                                v-model:longitude="
                                    form.longitude
                                "
                            />

                            <div
                                v-if="
                                    thumbnailPreviewUrl
                                "
                                class="flex items-center gap-3 rounded-lg border border-sidebar-border bg-accent/30 p-4"
                            >
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border border-sidebar-border bg-background"
                                >
                                    <img
                                        :src="
                                            thumbnailPreviewUrl
                                        "
                                        alt="Miniatura de la especie"
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <div>
                                    <p
                                        class="text-sm font-medium"
                                    >
                                        Marker de la especie
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            thumbnailObjectUrl
                                                ? 'Se está utilizando la nueva miniatura seleccionada.'
                                                : 'Se está utilizando la miniatura actual de la especie.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                            >
                                <p
                                    class="text-sm text-muted-foreground"
                                >
                                    Esta especie no tiene miniatura.
                                    Puedes seleccionarla en la pestaña
                                    Imágenes.
                                </p>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                            >
                                <p
                                    class="text-sm text-muted-foreground"
                                >
                                    La ubicación debe permanecer dentro de la
                                    zona seleccionada. El plano es solamente
                                    una referencia visual y no limita dónde
                                    puedes colocar la especie.
                                </p>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                        >
                            <p
                                class="text-sm text-muted-foreground"
                            >
                                Esta especie no tiene una zona asignada.
                            </p>

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Selecciona una zona para mostrar su mapa.
                            </p>
                        </div>

                        <div>
                            <label
                                for="location_name"
                                class="mb-2 block text-sm font-medium"
                            >
                                Nombre de la ubicación
                            </label>

                            <input
                                id="location_name"
                                v-model="
                                    form.location_name
                                "
                                type="text"
                                placeholder="Ej. Área de felinos"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="
                                    form.errors
                                        .location_name
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors
                                        .location_name
                                }}
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <div>
                                <label
                                    for="latitude"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Latitud
                                </label>

                                <input
                                    id="latitude"
                                    :value="
                                        form.latitude !==
                                        null
                                            ? form.latitude.toFixed(
                                                  7,
                                              )
                                            : ''
                                    "
                                    type="text"
                                    readonly
                                    placeholder="Selecciona un punto en el mapa"
                                    class="w-full rounded-lg border border-sidebar-border bg-muted/30 px-4 py-2.5 text-sm outline-none"
                                />

                                <p
                                    v-if="
                                        form.errors
                                            .latitude
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors
                                            .latitude
                                    }}
                                </p>
                            </div>

                            <div>
                                <label
                                    for="longitude"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Longitud
                                </label>

                                <input
                                    id="longitude"
                                    :value="
                                        form.longitude !==
                                        null
                                            ? form.longitude.toFixed(
                                                  7,
                                              )
                                            : ''
                                    "
                                    type="text"
                                    readonly
                                    placeholder="Selecciona un punto en el mapa"
                                    class="w-full rounded-lg border border-sidebar-border bg-muted/30 px-4 py-2.5 text-sm outline-none"
                                />

                                <p
                                    v-if="
                                        form.errors
                                            .longitude
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors
                                            .longitude
                                    }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label
                                for="location_description"
                                class="mb-2 block text-sm font-medium"
                            >
                                Descripción
                            </label>

                            <textarea
                                id="location_description"
                                v-model="
                                    form.location_description
                                "
                                rows="4"
                                placeholder="Descripción de la ubicación..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="
                                    form.errors
                                        .location_description
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors
                                        .location_description
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->

                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <button
                            v-if="
                                activeTab !==
                                'information'
                            "
                            type="button"
                            class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                previousTab
                            "
                        >
                            Anterior
                        </button>
                    </div>

                    <div
                        class="flex flex-col gap-3 sm:flex-row"
                    >
                        <button
                            v-if="
                                activeTab !==
                                'location'
                            "
                            type="button"
                            class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                nextTab
                            "
                        >
                            Siguiente
                        </button>

                        <button
                            v-if="
                                activeTab ===
                                'location'
                            "
                            type="submit"
                            :disabled="
                                form.processing
                            "
                            class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Guardando...'
                                    : 'Actualizar especie'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>