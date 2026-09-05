<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import admin from '@/routes/admin';

interface Category {
    id: number;
    name: string;
}

interface Tag {
    id: number;
    name: string;
}

interface SpeciesImage {
    id: number;
    type: string;
    path: string;
    alt_text: string | null;
    is_active: boolean;
    sort_order: number;
}

interface SpeciesModel {
    id: number;
    name: string;
    path: string | null;
    url: string | null;
    format: string | null;
    description: string | null;
    is_active: boolean;
}

interface SpeciesLocation {
    id: number;
    name: string;
    latitude: number | string;
    longitude: number | string;
    description: string | null;
    is_active: boolean;
}

interface Species {
    id: number;
    species_category_id: number;
    common_name: string;
    scientific_name: string;
    description: string | null;
    habitat: string | null;
    origin: string | null;
    diet: string | null;
    conservation_status: string | null;
    is_active: boolean;

    tags: Tag[];
    images: SpeciesImage[];
    models: SpeciesModel[];
    locations: SpeciesLocation[];
}

const props = defineProps<{
    species: Species;
    categories: Category[];
    tags: Tag[];
}>();

/*
|--------------------------------------------------------------------------
| Tabs
|--------------------------------------------------------------------------
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

/*
|--------------------------------------------------------------------------
| Formulario
|--------------------------------------------------------------------------
|
| Importante:
| Usamos POST + _method=put porque estamos enviando archivos.
|
*/

const form = useForm({
    _method: 'put',

    // Información
    species_category_id:
        props.species.species_category_id,

    common_name:
        props.species.common_name,

    scientific_name:
        props.species.scientific_name,

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

    // Etiquetas
    tags: props.species.tags.map(
        (tag) => tag.id,
    ),

    // Imágenes
    main_image: null as File | null,

    thumbnail_image:
        null as File | null,

    card_image:
        null as File | null,

    gallery_images:
        [] as File[],

    // Modelo 3D
    model_name:
        props.species.models[0]?.name ?? '',

    model_file:
        null as File | null,

    model_url:
        props.species.models[0]?.url ?? '',

    model_format:
        props.species.models[0]?.format ?? '',

    model_description:
        props.species.models[0]?.description ?? '',

    // Ubicación
    location_name:
        props.species.locations[0]?.name ?? '',

    latitude:
        props.species.locations[0]?.latitude ?? '',

    longitude:
        props.species.locations[0]?.longitude ?? '',

    location_description:
        props.species.locations[0]?.description ?? '',
});

/*
|--------------------------------------------------------------------------
| Información actual
|--------------------------------------------------------------------------
*/

const mainImage =
    props.species.images.find(
        (image) =>
            image.type === 'main',
    );

const thumbnailImage =
    props.species.images.find(
        (image) =>
            image.type === 'thumbnail',
    );

const cardImage =
    props.species.images.find(
        (image) =>
            image.type === 'card',
    );

const galleryImages =
    props.species.images.filter(
        (image) =>
            image.type === 'gallery',
    );

const currentModel =
    props.species.models[0] ?? null;

const currentLocation =
    props.species.locations[0] ?? null;

/*
|--------------------------------------------------------------------------
| Guardar cambios
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.post(
        admin.species.update(
            props.species.id,
        ).url,
        {
            forceFormData: true,

            onSuccess: () => {
                // Laravel/Inertia se encarga
                // de regresar a la página correspondiente.
            },
        },
    );
};

/*
|--------------------------------------------------------------------------
| Imágenes
|--------------------------------------------------------------------------
*/

const setMainImage = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    form.main_image =
        target.files?.[0] ?? null;
};

const setThumbnailImage = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    form.thumbnail_image =
        target.files?.[0] ?? null;
};

const setCardImage = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    form.card_image =
        target.files?.[0] ?? null;
};

const setGalleryImages = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    form.gallery_images =
        target.files
            ? Array.from(target.files)
            : [];
};

/*
|--------------------------------------------------------------------------
| Modelo 3D
|--------------------------------------------------------------------------
*/

const setModelFile = (
    event: Event,
) => {
    const target =
        event.target as HTMLInputElement;

    const file =
        target.files?.[0] ?? null;

    form.model_file = file;

    /*
    |--------------------------------------------------------------------------
    | Detectar formato automáticamente
    |--------------------------------------------------------------------------
    */

    if (file) {
        const extension =
            file.name
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
    }
};

/*
|--------------------------------------------------------------------------
| Navegación entre pestañas
|--------------------------------------------------------------------------
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

/*
|--------------------------------------------------------------------------
| URL de imágenes
|--------------------------------------------------------------------------
*/

const imageUrl = (
    path: string,
) => {
    return `/storage/${path}`;
};
</script>

<template>
    <Head title="Editar especie" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- ===================================================== -->
        <!-- HEADER -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-xl font-semibold"
                    >
                        Editar especie
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modifica la información de la especie.
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

        <!-- ===================================================== -->
        <!-- FORMULARIO -->
        <!-- ===================================================== -->

        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <form
                class="flex flex-col"
                @submit.prevent="submit"
            >
                <!-- ================================================= -->
                <!-- TABS -->
                <!-- ================================================= -->

                <div
                    class="border-b border-sidebar-border px-6 pt-6"
                >
                    <div
                        class="flex gap-2 overflow-x-auto"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="whitespace-nowrap rounded-t-lg px-4 py-3 text-sm font-medium transition"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            "
                            @click="
                                activeTab = tab.id
                            "
                        >
                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- CONTENIDO -->
                <!-- ================================================= -->

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

                        <!-- Categoría -->

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
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{
                                        category.name
                                    }}
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

                        <!-- Nombres -->

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

                        <!-- Descripción -->

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

                            <p
                                v-if="
                                    form.errors
                                        .description
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors
                                        .description
                                }}
                            </p>
                        </div>

                        <!-- Datos -->

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

                        <!-- Estado -->

                        <div>
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
                    </div>

                    <!-- ================================================= -->
                    <!-- ETIQUETAS -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab === 'tags'
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
                                Selecciona las etiquetas que describen a esta especie.
                            </p>
                        </div>

                        <div
                            v-if="tags.length"
                            class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <label
                                v-for="tag in tags"
                                :key="tag.id"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border px-4 py-3 transition hover:bg-accent"
                            >
                                <input
                                    v-model="
                                        form.tags
                                    "
                                    type="checkbox"
                                    :value="tag.id"
                                    class="h-4 w-4 rounded border-sidebar-border"
                                />

                                <span
                                    class="text-sm"
                                >
                                    {{
                                        tag.name
                                    }}
                                </span>
                            </label>
                        </div>

                        <p
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            No hay etiquetas activas disponibles.
                        </p>

                        <p
                            v-if="
                                form.errors.tags
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                form.errors.tags
                            }}
                        </p>
                    </div>

                    <!-- ================================================= -->
                    <!-- IMÁGENES -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab === 'images'
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
                                Administra las imágenes de la especie.
                            </p>
                        </div>

                        <!-- ============================================= -->
                        <!-- IMAGEN PRINCIPAL -->
                        <!-- ============================================= -->

                        <div
                            class="rounded-xl border border-sidebar-border p-5"
                        >
                            <div
                                class="flex flex-col gap-5 md:flex-row"
                            >
                                <div
                                    v-if="
                                        mainImage
                                    "
                                    class="h-40 w-40 shrink-0 overflow-hidden rounded-lg border border-sidebar-border"
                                >
                                    <img
                                        :src="
                                            imageUrl(
                                                mainImage.path,
                                            )
                                        "
                                        :alt="
                                            mainImage.alt_text ??
                                            species.common_name
                                        "
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <div
                                    class="flex-1"
                                >
                                    <h3
                                        class="font-medium"
                                    >
                                        Imagen principal
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            mainImage
                                                ? 'Imagen actual. Selecciona otra para reemplazarla.'
                                                : 'No hay una imagen principal.'
                                        }}
                                    </p>

                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="mt-4 block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                        @change="
                                            setMainImage
                                        "
                                    />

                                    <p
                                        v-if="
                                            form.errors.main_image
                                        "
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{
                                            form.errors.main_image
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================= -->
                        <!-- THUMBNAIL -->
                        <!-- ============================================= -->

                        <div
                            class="rounded-xl border border-sidebar-border p-5"
                        >
                            <div
                                class="flex flex-col gap-5 md:flex-row"
                            >
                                <div
                                    v-if="
                                        thumbnailImage
                                    "
                                    class="h-32 w-32 shrink-0 overflow-hidden rounded-lg border border-sidebar-border"
                                >
                                    <img
                                        :src="
                                            imageUrl(
                                                thumbnailImage.path,
                                            )
                                        "
                                        :alt="
                                            thumbnailImage.alt_text ??
                                            species.common_name
                                        "
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <div
                                    class="flex-1"
                                >
                                    <h3
                                        class="font-medium"
                                    >
                                        Miniatura
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            thumbnailImage
                                                ? 'Miniatura actual. Selecciona otra para reemplazarla.'
                                                : 'No hay miniatura.'
                                        }}
                                    </p>

                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="mt-4 block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                        @change="
                                            setThumbnailImage
                                        "
                                    />

                                    <p
                                        v-if="
                                            form.errors.thumbnail_image
                                        "
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{
                                            form.errors.thumbnail_image
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================= -->
                        <!-- TARJETA -->
                        <!-- ============================================= -->

                        <div
                            class="rounded-xl border border-sidebar-border p-5"
                        >
                            <div
                                class="flex flex-col gap-5 md:flex-row"
                            >
                                <div
                                    v-if="
                                        cardImage
                                    "
                                    class="h-40 w-28 shrink-0 overflow-hidden rounded-lg border border-sidebar-border"
                                >
                                    <img
                                        :src="
                                            imageUrl(
                                                cardImage.path,
                                            )
                                        "
                                        :alt="
                                            cardImage.alt_text ??
                                            species.common_name
                                        "
                                        class="h-full w-full object-cover"
                                    />
                                </div>

                                <div
                                    class="flex-1"
                                >
                                    <h3
                                        class="font-medium"
                                    >
                                        Imagen para tarjeta
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            cardImage
                                                ? 'Imagen actual. Selecciona otra para reemplazarla.'
                                                : 'No hay imagen de tarjeta.'
                                        }}
                                    </p>

                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="mt-4 block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                        @change="
                                            setCardImage
                                        "
                                    />

                                    <p
                                        v-if="
                                            form.errors.card_image
                                        "
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{
                                            form.errors.card_image
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================= -->
                        <!-- GALERÍA -->
                        <!-- ============================================= -->

                        <div
                            class="rounded-xl border border-sidebar-border p-5"
                        >
                            <h3
                                class="font-medium"
                            >
                                Galería
                            </h3>

                            <div
                                v-if="
                                    galleryImages.length
                                "
                                class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
                            >
                                <div
                                    v-for="image in galleryImages"
                                    :key="image.id"
                                    class="aspect-square overflow-hidden rounded-lg border border-sidebar-border"
                                >
                                    <img
                                        :src="
                                            imageUrl(
                                                image.path,
                                            )
                                        "
                                        :alt="
                                            image.alt_text ??
                                            species.common_name
                                        "
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            </div>

                            <p
                                v-else
                                class="mt-2 text-sm text-muted-foreground"
                            >
                                No hay imágenes en la galería.
                            </p>

                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                class="mt-4 block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5"
                                @change="
                                    setGalleryImages
                                "
                            />

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Las nuevas imágenes se agregarán a la galería existente.
                            </p>

                            <p
                                v-if="
                                    form.errors.gallery_images
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors.gallery_images
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- MODELO 3D -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab === 'model'
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
                                Administra el modelo 3D de la especie.
                            </p>
                        </div>

                        <!-- Modelo actual -->

                        <div
                            v-if="
                                currentModel
                            "
                            class="rounded-xl border border-sidebar-border bg-accent/30 p-5"
                        >
                            <p
                                class="text-sm font-medium"
                            >
                                Modelo actual
                            </p>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    currentModel.name
                                }}
                            </p>

                            <div
                                class="mt-3 grid gap-2 text-sm"
                            >
                                <p
                                    v-if="
                                        currentModel.format
                                    "
                                >
                                    <strong>
                                        Formato:
                                    </strong>

                                    {{
                                        currentModel.format.toUpperCase()
                                    }}
                                </p>

                                <p
                                    v-if="
                                        currentModel.url
                                    "
                                >
                                    <strong>
                                        URL:
                                    </strong>

                                    {{
                                        currentModel.url
                                    }}
                                </p>

                                <p
                                    v-if="
                                        currentModel.path
                                    "
                                >
                                    <strong>
                                        Archivo:
                                    </strong>

                                    {{
                                        currentModel.path
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Campos -->

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <!-- Nombre -->

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
                                    placeholder="Ej. León 3D"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="
                                        form.errors.model_name
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.model_name
                                    }}
                                </p>
                            </div>

                            <!-- Formato -->

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

                                    <option
                                        value="glb"
                                    >
                                        GLB
                                    </option>

                                    <option
                                        value="gltf"
                                    >
                                        GLTF
                                    </option>

                                    <option
                                        value="usdz"
                                    >
                                        USDZ
                                    </option>
                                </select>

                                <p
                                    v-if="
                                        form.errors.model_format
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.model_format
                                    }}
                                </p>
                            </div>

                            <!-- Archivo -->

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
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Déjalo vacío para conservar el archivo actual.
                                </p>

                                <p
                                    v-if="
                                        form.errors.model_file
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.model_file
                                    }}
                                </p>
                            </div>

                            <!-- URL -->

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

                                <p
                                    v-if="
                                        form.errors.model_url
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.model_url
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Descripción -->

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

                            <p
                                v-if="
                                    form.errors.model_description
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors.model_description
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- UBICACIÓN -->
                    <!-- ================================================= -->

                    <div
                        v-if="
                            activeTab === 'location'
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
                                Administra la ubicación de la especie dentro del zoológico.
                            </p>
                        </div>

                        <!-- Ubicación actual -->

                        <div
                            v-if="
                                currentLocation
                            "
                            class="rounded-xl border border-sidebar-border bg-accent/30 p-5"
                        >
                            <p
                                class="text-sm font-medium"
                            >
                                Ubicación actual
                            </p>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    currentLocation.name
                                }}
                            </p>

                            <div
                                class="mt-3 grid gap-2 text-sm md:grid-cols-2"
                            >
                                <p>
                                    <strong>
                                        Latitud:
                                    </strong>

                                    {{
                                        currentLocation.latitude
                                    }}
                                </p>

                                <p>
                                    <strong>
                                        Longitud:
                                    </strong>

                                    {{
                                        currentLocation.longitude
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Nombre -->

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
                                    form.errors.location_name
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors.location_name
                                }}
                            </p>
                        </div>

                        <!-- Coordenadas -->

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
                                    v-model="
                                        form.latitude
                                    "
                                    type="number"
                                    step="0.0000001"
                                    placeholder="Ej. 24.0277"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="
                                        form.errors.latitude
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.latitude
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
                                    v-model="
                                        form.longitude
                                    "
                                    type="number"
                                    step="0.0000001"
                                    placeholder="Ej. -104.6532"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="
                                        form.errors.longitude
                                    "
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{
                                        form.errors.longitude
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Descripción -->

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
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="
                                    form.errors.location_description
                                "
                                class="mt-1 text-sm text-red-500"
                            >
                                {{
                                    form.errors.location_description
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ================================================= -->
                <!-- FOOTER -->
                <!-- ================================================= -->

                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Anterior -->

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

                    <!-- Siguiente / Guardar -->

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
                                    : 'Guardar cambios'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>