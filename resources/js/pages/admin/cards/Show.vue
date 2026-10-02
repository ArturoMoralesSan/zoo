<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface Species {
    id: number;
    common_name: string;
    scientific_name: string;
}

interface Card {
    id: number;
    species_id: number;
    name: string;
    rarity: string;
    edition: string | null;
    description: string | null;
    card_image: string | null;
    model_name: string | null;
    model_file: string | null;
    model_url: string | null;
    model_format: string | null;
    model_description: string | null;
    is_active: boolean;
    sort_order: number;
    species: Species;
}

const props = defineProps<{
    card: Card;
}>();

const cardImageUrl = (path: string | null): string | null => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const modelFileUrl = (path: string | null): string | null => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const getRarityLabel = (value: string): string => {
    const labels: Record<string, string> = {
        comun: 'Común',
        rara: 'Rara',
        epica: 'Épica',
        edicion_especial: 'Edición especial',
    };

    return labels[value] ?? value;
};

const getRarityClasses = (value: string): string => {
    const classes: Record<string, string> = {
        comun: 'border-sidebar-border text-muted-foreground',
        rara: 'border-blue-500/30 text-blue-500',
        epica: 'border-purple-500/30 text-purple-500',
        edicion_especial:
            'border-yellow-500/30 text-yellow-600',
    };

    return (
        classes[value] ??
        'border-sidebar-border text-muted-foreground'
    );
};
</script>

<template>
    <Head :title="`Tarjeta: ${card.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ card.name }}
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Información de la tarjeta coleccionable.
                    </p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <Link
                        :href="admin.cards.index().url"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Volver
                    </Link>

                    <Link
                        :href="admin.cards.edit(card.id).url"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Editar tarjeta
                    </Link>
                </div>
            </div>
        </div>

        <!-- Información principal -->
        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Imagen -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex min-h-[400px] items-center justify-center overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                >
                    <img
                        v-if="card.card_image"
                        :src="
                            cardImageUrl(card.card_image) ?? ''
                        "
                        :alt="card.name"
                        class="max-h-[600px] w-full object-contain"
                    />

                    <div
                        v-else
                        class="flex flex-col items-center justify-center text-center text-muted-foreground"
                    >
                        <span class="text-sm">
                            Sin imagen
                        </span>

                        <span class="mt-1 text-xs">
                            Esta tarjeta no tiene una imagen asociada.
                        </span>
                    </div>
                </div>

                <div
                    v-if="card.card_image"
                    class="mt-3 break-all text-xs text-muted-foreground"
                >
                    {{ card.card_image }}
                </div>
            </div>

            <!-- Datos -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border lg:col-span-2"
            >
                <div
                    class="flex flex-col gap-6"
                >
                    <!-- Título -->
                    <div>
                        <div
                            class="flex flex-wrap items-center gap-2"
                        >
                            <h2 class="text-xl font-semibold">
                                {{ card.name }}
                            </h2>

                            <span
                                class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="
                                    getRarityClasses(
                                        card.rarity,
                                    )
                                "
                            >
                                {{
                                    getRarityLabel(
                                        card.rarity,
                                    )
                                }}
                            </span>

                            <span
                                v-if="card.is_active"
                                class="rounded-full border border-green-500/30 px-2.5 py-1 text-xs font-medium text-green-500"
                            >
                                Activa
                            </span>

                            <span
                                v-else
                                class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium text-muted-foreground"
                            >
                                Inactiva
                            </span>
                        </div>

                        <p
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            ID de tarjeta: {{ card.id }}
                        </p>
                    </div>

                    <!-- Especie -->
                    <div
                        class="rounded-lg border border-sidebar-border p-5"
                    >
                        <h3 class="text-sm font-semibold">
                            Especie
                        </h3>

                        <div class="mt-3">
                            <p class="font-medium">
                                {{ card.species.common_name }}
                            </p>

                            <p
                                class="mt-1 text-sm italic text-muted-foreground"
                            >
                                {{ card.species.scientific_name }}
                            </p>
                        </div>
                    </div>

                    <!-- Edición -->
                    <div
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div
                            class="rounded-lg border border-sidebar-border p-5"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Edición
                            </p>

                            <p
                                class="mt-1 font-medium"
                            >
                                {{ card.edition || 'General' }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border p-5"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Orden
                            </p>

                            <p
                                class="mt-1 font-medium"
                            >
                                {{ card.sort_order }}
                            </p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div>
                        <h3 class="text-sm font-semibold">
                            Descripción
                        </h3>

                        <p
                            v-if="card.description"
                            class="mt-2 whitespace-pre-line text-sm leading-6 text-muted-foreground"
                        >
                            {{ card.description }}
                        </p>

                        <p
                            v-else
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            Sin descripción.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modelo 3D -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Modelo 3D
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Información del modelo 3D asociado a esta tarjeta.
                    </p>
                </div>

                <span
                    v-if="
                        card.model_file ||
                        card.model_url
                    "
                    class="rounded-full border border-green-500/30 px-2.5 py-1 text-xs font-medium text-green-500"
                >
                    Disponible
                </span>

                <span
                    v-else
                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium text-muted-foreground"
                >
                    Sin modelo
                </span>
            </div>

            <!-- Hay modelo -->
            <div
                v-if="
                    card.model_file ||
                    card.model_url
                "
                class="mt-6 grid gap-4 lg:grid-cols-2"
            >
                <!-- Información -->
                <div
                    class="rounded-lg border border-sidebar-border p-5"
                >
                    <div class="space-y-5">
                        <div v-if="card.model_name">
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Nombre
                            </p>

                            <p class="mt-1 font-medium">
                                {{ card.model_name }}
                            </p>
                        </div>

                        <div v-if="card.model_format">
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Formato
                            </p>

                            <p
                                class="mt-1 font-medium uppercase"
                            >
                                {{ card.model_format }}
                            </p>
                        </div>

                        <div v-if="card.model_file">
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Archivo
                            </p>

                            <a
                                :href="
                                    modelFileUrl(
                                        card.model_file,
                                    ) ?? ''
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 block break-all text-sm text-primary hover:underline"
                            >
                                {{ card.model_file }}
                            </a>
                        </div>

                        <div v-if="card.model_url">
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                URL externa
                            </p>

                            <a
                                :href="card.model_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 block break-all text-sm text-primary hover:underline"
                            >
                                {{ card.model_url }}
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Descripción -->
                <div
                    class="rounded-lg border border-sidebar-border p-5"
                >
                    <h3 class="text-sm font-semibold">
                        Descripción del modelo
                    </h3>

                    <p
                        v-if="card.model_description"
                        class="mt-3 whitespace-pre-line text-sm leading-6 text-muted-foreground"
                    >
                        {{ card.model_description }}
                    </p>

                    <p
                        v-else
                        class="mt-3 text-sm text-muted-foreground"
                    >
                        Sin descripción del modelo.
                    </p>
                </div>
            </div>

            <!-- Sin modelo -->
            <div
                v-else
                class="mt-6 rounded-lg border border-dashed border-sidebar-border p-10 text-center"
            >
                <p class="font-medium">
                    No hay un modelo 3D asociado.
                </p>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Puedes agregar un archivo GLB/GLTF o una URL
                    desde la edición de la tarjeta.
                </p>

                <Link
                    :href="admin.cards.edit(card.id).url"
                    class="mt-4 inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Agregar modelo 3D
                </Link>
            </div>
        </div>
    </div>
</template>