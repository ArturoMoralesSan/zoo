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

const props = defineProps<{
    categories: Category[];
    tags: Tag[];
}>();

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

const form = useForm({
    // Información
    species_category_id: '',
    common_name: '',
    scientific_name: '',
    description: '',
    habitat: '',
    origin: '',
    diet: '',
    conservation_status: '',
    is_active: true,

    // Etiquetas
    tags: [] as number[],

    // Imágenes
    main_image: null as File | null,
    thumbnail_image: null as File | null,
    card_image: null as File | null,
    gallery_images: [] as File[],

    // Modelo 3D
    model_name: '',
    model_file: null as File | null,
    model_url: '',
    model_format: '',
    model_description: '',

    // Ubicación
    location_name: '',
    latitude: '',
    longitude: '',
    location_description: '',
});

const submit = () => {
    form.post(admin.species.store().url, {
        forceFormData: true,
    });
};

/*
|--------------------------------------------------------------------------
| Imágenes
|--------------------------------------------------------------------------
*/

const setMainImage = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.main_image = target.files?.[0] ?? null;
};

const setThumbnailImage = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.thumbnail_image = target.files?.[0] ?? null;
};

const setCardImage = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.card_image = target.files?.[0] ?? null;
};

const setGalleryImages = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.gallery_images = target.files
        ? Array.from(target.files)
        : [];
};

/*
|--------------------------------------------------------------------------
| Modelo 3D
|--------------------------------------------------------------------------
*/

const setModelFile = (event: Event) => {
    const target = event.target as HTMLInputElement;

    const file = target.files?.[0] ?? null;

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
            form.model_format = extension;
        }

        if (!form.model_name) {
            form.model_name = file.name.replace(
                /\.[^/.]+$/,
                '',
            );
        }
    }
};

/*
|--------------------------------------------------------------------------
| Navegación entre pestañas
|--------------------------------------------------------------------------
*/

const nextTab = () => {
    const currentIndex = tabs.findIndex(
        (tab) => tab.id === activeTab.value,
    );

    if (currentIndex < tabs.length - 1) {
        activeTab.value =
            tabs[currentIndex + 1].id;
    }
};

const previousTab = () => {
    const currentIndex = tabs.findIndex(
        (tab) => tab.id === activeTab.value,
    );

    if (currentIndex > 0) {
        activeTab.value =
            tabs[currentIndex - 1].id;
    }
};

const goToTab = (tabId: string) => {
    activeTab.value = tabId;
};
</script>

<template>
    <Head title="Nueva especie" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Header -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1 class="text-xl font-semibold">
                        Nueva especie
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Registra la información, imágenes,
                        etiquetas, modelo 3D y ubicación.
                    </p>
                </div>

                <Link
                    :href="admin.species.index().url"
                    class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Form -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <form
                class="flex flex-col"
                @submit.prevent="submit"
            >
                <!-- Tabs -->
                <div
                    class="border-b border-sidebar-border px-6 pt-6"
                >
                    <div
                        class="flex gap-2 overflow-x-auto"
                    >
                        <button
                            v-for="(tab, index) in tabs"
                            :key="tab.id"
                            type="button"
                            class="whitespace-nowrap rounded-t-lg px-4 py-3 text-sm font-medium transition"
                            :class="
                                activeTab === tab.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                            "
                            @click="goToTab(tab.id)"
                        >
                            <span class="mr-1.5 opacity-70">
                                {{ index + 1 }}.
                            </span>

                            {{ tab.name }}
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6">

                    <!-- ================================================= -->
                    <!-- INFORMACIÓN -->
                    <!-- ================================================= -->

                    <div
                        v-if="activeTab === 'information'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Información de la especie
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
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
                                v-model="form.species_category_id"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option value="">
                                    Selecciona una categoría
                                </option>

                                <option
                                    v-for="category in props.categories"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.species_category_id"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.species_category_id }}
                            </p>
                        </div>

                        <!-- Nombre -->
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
                                    v-model="form.common_name"
                                    type="text"
                                    placeholder="Ej. León"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="form.errors.common_name"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.common_name }}
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
                                    v-model="form.scientific_name"
                                    type="text"
                                    placeholder="Ej. Panthera leo"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm italic outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="form.errors.scientific_name"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.scientific_name }}
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
                                v-model="form.description"
                                rows="5"
                                placeholder="Describe la especie..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.description"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.description }}
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
                                    v-model="form.habitat"
                                    type="text"
                                    placeholder="Ej. Selva tropical"
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
                                    v-model="form.origin"
                                    type="text"
                                    placeholder="Ej. América"
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
                                    v-model="form.diet"
                                    type="text"
                                    placeholder="Ej. Carnívoro"
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
                                    v-model="form.conservation_status"
                                    type="text"
                                    placeholder="Ej. Vulnerable"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>
                        </div>

                        <!-- Activo -->
                        <div>
                            <label
                                class="flex cursor-pointer items-center gap-3"
                            >
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-sidebar-border"
                                />

                                <span class="text-sm font-medium">
                                    Especie activa
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- ETIQUETAS -->
                    <!-- ================================================= -->

                    <div
                        v-if="activeTab === 'tags'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Etiquetas
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Selecciona las etiquetas que describen
                                a esta especie.
                            </p>
                        </div>

                        <div
                            v-if="props.tags.length"
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <label
                                v-for="tag in props.tags"
                                :key="tag.id"
                                class="flex cursor-pointer items-center gap-3 rounded-lg border border-sidebar-border px-4 py-3 transition hover:bg-accent"
                            >
                                <input
                                    v-model="form.tags"
                                    type="checkbox"
                                    :value="tag.id"
                                    class="h-4 w-4 rounded border-sidebar-border"
                                />

                                <span class="text-sm">
                                    {{ tag.name }}
                                </span>
                            </label>
                        </div>

                        <div
                            v-else
                            class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                        >
                            <p class="text-sm text-muted-foreground">
                                No hay etiquetas activas disponibles.
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Puedes crear etiquetas desde el módulo
                                de Etiquetas.
                            </p>
                        </div>

                        <p
                            v-if="form.errors.tags"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.tags }}
                        </p>

                        <div
                            v-if="form.tags.length"
                            class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                        >
                            <p class="text-sm">
                                <strong>
                                    {{ form.tags.length }}
                                </strong>

                                {{
                                    form.tags.length === 1
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
                        v-if="activeTab === 'images'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Imágenes
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Agrega las imágenes utilizadas en el
                                catálogo, tarjeta y experiencia de la especie.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-6 md:grid-cols-2"
                        >
                            <!-- Principal -->
                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="main_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Imagen principal
                                </label>

                                <input
                                    id="main_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm"
                                    @change="setMainImage"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    JPG, PNG o WEBP. Máximo 5 MB.
                                </p>

                                <p
                                    v-if="form.errors.main_image"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.main_image }}
                                </p>
                            </div>

                            <!-- Miniatura -->
                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="thumbnail_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Miniatura
                                </label>

                                <input
                                    id="thumbnail_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm"
                                    @change="setThumbnailImage"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    Imagen pequeña para listados.
                                </p>

                                <p
                                    v-if="form.errors.thumbnail_image"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.thumbnail_image }}
                                </p>
                            </div>

                            <!-- Tarjeta -->
                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="card_image"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Imagen para tarjeta
                                </label>

                                <input
                                    id="card_image"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm"
                                    @change="setCardImage"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    Imagen para la tarjeta coleccionable.
                                </p>

                                <p
                                    v-if="form.errors.card_image"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.card_image }}
                                </p>
                            </div>

                            <!-- Galería -->
                            <div
                                class="rounded-xl border border-sidebar-border p-5"
                            >
                                <label
                                    for="gallery_images"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Galería
                                </label>

                                <input
                                    id="gallery_images"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    multiple
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm"
                                    @change="setGalleryImages"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    Puedes seleccionar varias imágenes.
                                </p>

                                <p
                                    v-if="form.errors.gallery_images"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.gallery_images }}
                                </p>

                                <div
                                    v-if="form.gallery_images.length"
                                    class="mt-3 rounded-lg bg-accent/30 p-3"
                                >
                                    <p class="text-xs">
                                        {{
                                            form.gallery_images.length
                                        }}

                                        {{
                                            form.gallery_images.length === 1
                                                ? 'imagen seleccionada'
                                                : 'imágenes seleccionadas'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                        >
                            <p class="text-sm text-muted-foreground">
                                Puedes registrar una imagen principal,
                                una miniatura, una imagen para la tarjeta
                                y varias imágenes para la galería.
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- MODELO 3D -->
                    <!-- ================================================= -->

                    <div
                        v-if="activeTab === 'model'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Modelo 3D
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Agrega el modelo 3D que será utilizado
                                para la experiencia de realidad aumentada.
                            </p>
                        </div>

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
                                    v-model="form.model_name"
                                    type="text"
                                    placeholder="Ej. León 3D"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p
                                    v-if="form.errors.model_name"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.model_name }}
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
                                    v-model="form.model_format"
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

                                <p
                                    v-if="form.errors.model_format"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.model_format }}
                                </p>
                            </div>

                            <!-- Archivo -->
                            <div>
                                <label
                                    for="model_file"
                                    class="mb-2 block text-sm font-medium"
                                >
                                    Archivo 3D
                                </label>

                                <input
                                    id="model_file"
                                    type="file"
                                    accept=".glb,.gltf,.usdz"
                                    class="block w-full rounded-lg border border-sidebar-border bg-background text-sm file:mr-4 file:border-0 file:bg-accent file:px-4 file:py-2.5 file:text-sm"
                                    @change="setModelFile"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    GLB, GLTF o USDZ. Máximo 50 MB.
                                </p>

                                <p
                                    v-if="form.model_file"
                                    class="mt-2 text-sm"
                                >
                                    Archivo:
                                    <strong>
                                        {{ form.model_file.name }}
                                    </strong>
                                </p>

                                <p
                                    v-if="form.errors.model_file"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.model_file }}
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
                                    v-model="form.model_url"
                                    type="url"
                                    placeholder="https://..."
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p class="mt-2 text-xs text-muted-foreground">
                                    Puedes utilizar archivo, URL o ambos.
                                </p>

                                <p
                                    v-if="form.errors.model_url"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.model_url }}
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
                                v-model="form.model_description"
                                rows="4"
                                placeholder="Descripción del modelo 3D..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.model_description"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.model_description }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                        >
                            <p class="text-sm text-muted-foreground">
                                Al seleccionar un archivo, el formato se
                                detectará automáticamente a partir de su
                                extensión.
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- UBICACIÓN -->
                    <!-- ================================================= -->

                    <div
                        v-if="activeTab === 'location'"
                        class="space-y-6"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">
                                Ubicación
                            </h2>

                            <p class="mt-1 text-sm text-muted-foreground">
                                Registra la ubicación física de la especie
                                dentro del zoológico.
                            </p>
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
                                v-model="form.location_name"
                                type="text"
                                placeholder="Ej. Área de felinos"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.location_name"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.location_name }}
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
                                    v-model="form.latitude"
                                    type="number"
                                    step="0.0000001"
                                    placeholder="Ej. 24.0277000"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p class="mt-1 text-xs text-muted-foreground">
                                    Ejemplo: 24.0277000
                                </p>

                                <p
                                    v-if="form.errors.latitude"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.latitude }}
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
                                    v-model="form.longitude"
                                    type="number"
                                    step="0.0000001"
                                    placeholder="Ej. -104.6532000"
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />

                                <p class="mt-1 text-xs text-muted-foreground">
                                    Ejemplo: -104.6532000
                                </p>

                                <p
                                    v-if="form.errors.longitude"
                                    class="mt-1 text-sm text-red-500"
                                >
                                    {{ form.errors.longitude }}
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
                                v-model="form.location_description"
                                rows="4"
                                placeholder="Descripción de la ubicación..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.location_description"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.location_description }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border bg-accent/30 p-4"
                        >
                            <p class="text-sm text-muted-foreground">
                                Para registrar una ubicación se requieren
                                la latitud y longitud. Estas coordenadas
                                posteriormente podrán utilizarse para
                                mostrar la especie en el mapa del zoológico.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Anterior -->
                    <div>
                        <button
                            v-if="activeTab !== 'information'"
                            type="button"
                            class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="previousTab"
                        >
                            Anterior
                        </button>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <!-- Siguiente -->
                        <button
                            v-if="activeTab !== 'location'"
                            type="button"
                            class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="nextTab"
                        >
                            Siguiente
                        </button>

                        <!-- Crear -->
                        <button
                            v-if="activeTab === 'location'"
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                form.processing
                                    ? 'Guardando...'
                                    : 'Crear especie'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>