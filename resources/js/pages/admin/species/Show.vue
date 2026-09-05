<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface SpeciesCategory {
    id: number;
    name: string;
}

interface SpeciesImage {
    id: number;
    type: 'main' | 'thumbnail' | 'card' | 'gallery';
    path: string;
    alt_text?: string | null;
    is_active: boolean;
    sort_order: number;
}

interface SpeciesModel {
    id: number;
    name: string;
    path?: string | null;
    url?: string | null;
    format?: string | null;
    description?: string | null;
    is_active: boolean;
}

interface SpeciesLocation {
    id: number;
    name: string;
    latitude: number;
    longitude: number;
    description?: string | null;
    is_active: boolean;
}

interface SpeciesTag {
    id: number;
    name: string;
}

interface Species {
    id: number;
    species_category_id: number;
    common_name: string;
    scientific_name?: string | null;
    slug: string;
    description?: string | null;
    habitat?: string | null;
    origin?: string | null;
    diet?: string | null;
    conservation_status?: string | null;
    is_active: boolean;

    category?: SpeciesCategory | null;
    images: SpeciesImage[];
    models: SpeciesModel[];
    locations: SpeciesLocation[];
    tags: SpeciesTag[];
}

const props = defineProps<{
    species: Species;
}>();

const mainImage =
    props.species.images.find(
        (image) =>
            image.type === 'main' &&
            image.is_active,
    ) ?? null;

const thumbnailImage =
    props.species.images.find(
        (image) =>
            image.type === 'thumbnail' &&
            image.is_active,
    ) ?? null;

const cardImage =
    props.species.images.find(
        (image) =>
            image.type === 'card' &&
            image.is_active,
    ) ?? null;

const galleryImages =
    props.species.images.filter(
        (image) =>
            image.type === 'gallery' &&
            image.is_active,
    );

const currentModel =
    props.species.models[0] ?? null;

const currentLocation =
    props.species.locations[0] ?? null;

const imageUrl = (path: string) => {
    return `/storage/${path}`;
};

const modelUrl = (
    model: SpeciesModel,
) => {
    if (model.url) {
        return model.url;
    }

    if (model.path) {
        return `/storage/${model.path}`;
    }

    return null;
};
</script>

<template>
    <Head
        :title="`Detalles - ${species.common_name}`"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- ENCABEZADO -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Detalles de la especie
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Información completa de
                        {{ species.common_name }}.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <Link
                        :href="
                            admin.species.index().url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Volver
                    </Link>

                    <Link
                        :href="
                            admin.species.edit(
                                species.id,
                            ).url
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Editar
                    </Link>
                </div>
            </div>
        </div>

        <!-- INFORMACIÓN GENERAL -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="grid gap-6 lg:grid-cols-3"
            >
                <!-- DATOS -->
                <div
                    class="lg:col-span-2"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Información general
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Datos principales de la
                            especie.
                        </p>
                    </div>

                    <div
                        class="mt-6 grid gap-5 sm:grid-cols-2"
                    >
                        <!-- Nombre común -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Nombre común
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{
                                    species.common_name
                                }}
                            </p>
                        </div>

                        <!-- Nombre científico -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Nombre científico
                            </p>

                            <p
                                class="mt-1 text-sm italic"
                            >
                                {{
                                    species.scientific_name ||
                                    'Sin especificar'
                                }}
                            </p>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Categoría
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    species.category
                                        ?.name ||
                                    'Sin categoría'
                                }}
                            </p>
                        </div>

                        <!-- ID -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                ID
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{ species.id }}
                            </p>
                        </div>

                        <!-- Slug -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Slug
                            </p>

                            <p
                                class="mt-1 break-all text-sm"
                            >
                                {{ species.slug }}
                            </p>
                        </div>

                        <!-- Hábitat -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Hábitat
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    species.habitat ||
                                    'Sin especificar'
                                }}
                            </p>
                        </div>

                        <!-- Origen -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Origen
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    species.origin ||
                                    'Sin especificar'
                                }}
                            </p>
                        </div>

                        <!-- Dieta -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Alimentación
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    species.diet ||
                                    'Sin especificar'
                                }}
                            </p>
                        </div>

                        <!-- Conservación -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Estado de conservación
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    species.conservation_status ||
                                    'Sin especificar'
                                }}
                            </p>
                        </div>

                        <!-- Estado -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Estado
                            </p>

                            <div
                                class="mt-1"
                            >
                                <span
                                    v-if="
                                        species.is_active
                                    "
                                    class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    Inactiva
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- DESCRIPCIÓN -->
                    <div
                        v-if="species.description"
                        class="mt-6 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Descripción
                        </p>

                        <p
                            class="mt-2 whitespace-pre-line text-sm leading-6"
                        >
                            {{ species.description }}
                        </p>
                    </div>
                </div>

                <!-- IMAGEN PRINCIPAL -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Imagen principal
                    </p>

                    <div
                        class="mt-2 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <img
                            v-if="mainImage"
                            :src="
                                imageUrl(
                                    mainImage.path,
                                )
                            "
                            :alt="
                                mainImage.alt_text ||
                                species.common_name
                            "
                            class="aspect-square w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex aspect-square items-center justify-center bg-muted text-sm text-muted-foreground"
                        >
                            Sin imagen principal
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ETIQUETAS -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
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
                        Etiquetas asociadas a esta
                        especie.
                    </p>
                </div>

                <span
                    class="w-fit rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                >
                    {{ species.tags.length }}
                    etiquetas
                </span>
            </div>

            <div
                v-if="species.tags.length"
                class="mt-5 flex flex-wrap gap-2"
            >
                <span
                    v-for="tag in species.tags"
                    :key="tag.id"
                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                >
                    {{ tag.name }}
                </span>
            </div>

            <p
                v-else
                class="mt-5 text-sm italic text-muted-foreground"
            >
                No hay etiquetas asociadas.
            </p>
        </div>

        <!-- IMÁGENES -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
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
                    Recursos gráficos registrados
                    para esta especie.
                </p>
            </div>

            <div
                class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
            >
                <!-- PRINCIPAL -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Principal
                    </p>

                    <div
                        class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <img
                            v-if="mainImage"
                            :src="
                                imageUrl(
                                    mainImage.path,
                                )
                            "
                            :alt="
                                mainImage.alt_text ||
                                species.common_name
                            "
                            class="aspect-square w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex aspect-square items-center justify-center bg-muted text-xs text-muted-foreground"
                        >
                            Sin imagen
                        </div>
                    </div>
                </div>

                <!-- MINIATURA -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Miniatura
                    </p>

                    <div
                        class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <img
                            v-if="thumbnailImage"
                            :src="
                                imageUrl(
                                    thumbnailImage.path,
                                )
                            "
                            :alt="
                                thumbnailImage.alt_text ||
                                species.common_name
                            "
                            class="aspect-square w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex aspect-square items-center justify-center bg-muted text-xs text-muted-foreground"
                        >
                            Sin imagen
                        </div>
                    </div>
                </div>

                <!-- TARJETA -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Tarjeta
                    </p>

                    <div
                        class="mt-2 overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <img
                            v-if="cardImage"
                            :src="
                                imageUrl(
                                    cardImage.path,
                                )
                            "
                            :alt="
                                cardImage.alt_text ||
                                species.common_name
                            "
                            class="aspect-square w-full object-cover"
                        />

                        <div
                            v-else
                            class="flex aspect-square items-center justify-center bg-muted text-xs text-muted-foreground"
                        >
                            Sin imagen
                        </div>
                    </div>
                </div>

                <!-- GALERÍA -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Galería
                    </p>

                    <div
                        v-if="galleryImages.length"
                        class="mt-2 grid grid-cols-2 gap-2"
                    >
                        <div
                            v-for="image in galleryImages.slice(
                                0,
                                4,
                            )"
                            :key="image.id"
                            class="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <img
                                :src="
                                    imageUrl(
                                        image.path,
                                    )
                                "
                                :alt="
                                    image.alt_text ||
                                    species.common_name
                                "
                                class="aspect-square w-full object-cover"
                            />
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-2 flex aspect-square items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
                    >
                        Sin imágenes
                    </div>
                </div>
            </div>
        </div>

        <!-- MODELO 3D -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
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
                        Modelo 3D asociado a la
                        especie.
                    </p>
                </div>

                <span
                    v-if="currentModel"
                    :class="
                        currentModel.is_active
                            ? 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400'
                            : 'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400'
                    "
                    class="w-fit rounded-full border px-2.5 py-1 text-xs font-medium"
                >
                    {{
                        currentModel.is_active
                            ? 'Activo'
                            : 'Inactivo'
                    }}
                </span>
            </div>

            <div
                v-if="currentModel"
                class="mt-6 grid gap-5 sm:grid-cols-2"
            >
                <!-- Nombre -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Nombre
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{ currentModel.name }}
                    </p>
                </div>

                <!-- Formato -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Formato
                    </p>

                    <p
                        class="mt-1 text-sm"
                    >
                        {{
                            currentModel.format ||
                            'Sin especificar'
                        }}
                    </p>
                </div>

                <!-- Descripción -->
                <div
                    v-if="currentModel.description"
                    class="sm:col-span-2"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Descripción
                    </p>

                    <p
                        class="mt-1 whitespace-pre-line text-sm leading-6"
                    >
                        {{
                            currentModel.description
                        }}
                    </p>
                </div>

                <!-- RECURSO -->
                <div
                    class="sm:col-span-2"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Recurso
                    </p>

                    <div class="mt-2">
                        <a
                            v-if="
                                modelUrl(
                                    currentModel,
                                )
                            "
                            :href="
                                modelUrl(
                                    currentModel,
                                ) || '#'
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                        >
                            Abrir modelo 3D
                        </a>

                        <span
                            v-else
                            class="text-sm italic text-muted-foreground"
                        >
                            Sin archivo o URL.
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-6 rounded-lg border border-dashed border-sidebar-border p-6 text-center text-sm text-muted-foreground"
            >
                No hay un modelo 3D registrado para
                esta especie.
            </div>
        </div>

        <!-- UBICACIÓN -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
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
                    Ubicación registrada dentro del
                    zoológico.
                </p>
            </div>

            <div
                v-if="currentLocation"
                class="mt-6 grid gap-5 sm:grid-cols-2"
            >
                <!-- Nombre -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Nombre
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{ currentLocation.name }}
                    </p>
                </div>

                <!-- Estado -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Estado
                    </p>

                    <div class="mt-1">
                        <span
                            v-if="
                                currentLocation.is_active
                            "
                            class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            Activa
                        </span>

                        <span
                            v-else
                            class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                        >
                            Inactiva
                        </span>
                    </div>
                </div>

                <!-- Latitud -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Latitud
                    </p>

                    <p
                        class="mt-1 text-sm"
                    >
                        {{ currentLocation.latitude }}
                    </p>
                </div>

                <!-- Longitud -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Longitud
                    </p>

                    <p
                        class="mt-1 text-sm"
                    >
                        {{ currentLocation.longitude }}
                    </p>
                </div>

                <!-- Descripción -->
                <div
                    v-if="currentLocation.description"
                    class="sm:col-span-2"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Descripción
                    </p>

                    <p
                        class="mt-1 whitespace-pre-line text-sm leading-6"
                    >
                        {{
                            currentLocation.description
                        }}
                    </p>
                </div>

                <!-- MAPA -->
                <div
                    class="sm:col-span-2"
                >
                    <a
                        :href="`https://www.google.com/maps?q=${currentLocation.latitude},${currentLocation.longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Ver en Google Maps
                    </a>
                </div>
            </div>

            <div
                v-else
                class="mt-6 rounded-lg border border-dashed border-sidebar-border p-6 text-center text-sm text-muted-foreground"
            >
                No hay una ubicación registrada
                para esta especie.
            </div>
        </div>

        <!-- ACCIONES -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-between"
        >
            <Link
                :href="
                    admin.species.index().url
                "
                class="text-sm text-muted-foreground transition hover:text-foreground"
            >
                ← Volver al listado
            </Link>

            <Link
                :href="
                    admin.species.edit(
                        species.id,
                    ).url
                "
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Editar especie
            </Link>
        </div>
    </div>
</template>