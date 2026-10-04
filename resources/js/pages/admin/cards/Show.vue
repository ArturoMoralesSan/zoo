<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Box,
    CheckCircle2,
    CircleOff,
    ExternalLink,
    FileBox,
    FileImage,
    ImageOff,
    Info,
    Link as LinkIcon,
    Pencil,
    Tag,
    Upload,
} from 'lucide-vue-next';
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
        comun: 'border-sidebar-border bg-muted/40 text-muted-foreground',
        rara: 'border-blue-500/30 bg-blue-500/5 text-blue-500',
        epica: 'border-purple-500/30 bg-purple-500/5 text-purple-500',
        edicion_especial:
            'border-yellow-500/30 bg-yellow-500/5 text-yellow-600',
    };

    return (
        classes[value] ??
        'border-sidebar-border bg-muted/40 text-muted-foreground'
    );
};
</script>

<template>
    <Head :title="`Tarjeta: ${card.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Tag class="h-5 w-5" />
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold">
                                {{ card.name }}
                            </h1>

                            <span
                                class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="getRarityClasses(card.rarity)"
                            >
                                {{ getRarityLabel(card.rarity) }}
                            </span>

                            <span
                                v-if="card.is_active"
                                class="inline-flex items-center gap-1 rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs font-medium text-green-500"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Activa
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                            >
                                <CircleOff class="h-3.5 w-3.5" />
                                Inactiva
                            </span>
                        </div>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Información de la tarjeta coleccionable · ID #{{ card.id }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <Link
                        :href="admin.cards.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Volver
                    </Link>

                    <Link
                        :href="admin.cards.edit(card.id).url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        <Pencil class="h-4 w-4" />
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
                <div class="mb-4 flex items-center gap-2">
                    <FileImage class="h-5 w-5 text-primary" />

                    <div>
                        <h2 class="text-base font-semibold">
                            Imagen de la tarjeta
                        </h2>

                        <p class="text-xs text-muted-foreground">
                            Recurso visual de la tarjeta.
                        </p>
                    </div>
                </div>

                <div
                    class="flex min-h-[400px] items-center justify-center overflow-hidden rounded-lg border border-sidebar-border bg-muted/40"
                >
                    <img
                        v-if="card.card_image"
                        :src="cardImageUrl(card.card_image) ?? ''"
                        :alt="card.name"
                        class="max-h-[600px] w-full object-contain"
                    />

                    <div
                        v-else
                        class="flex flex-col items-center justify-center px-6 py-12 text-center text-muted-foreground"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-background"
                        >
                            <ImageOff class="h-7 w-7" />
                        </div>

                        <p class="mt-4 font-medium">
                            Sin imagen
                        </p>

                        <p class="mt-1 max-w-xs text-sm">
                            Esta tarjeta no tiene una imagen asociada.
                        </p>

                        <Link
                            :href="admin.cards.edit(card.id).url"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                        >
                            <Upload class="h-4 w-4" />
                            Agregar imagen
                        </Link>
                    </div>
                </div>

                <div
                    v-if="card.card_image"
                    class="mt-3 flex items-start gap-2 break-all text-xs text-muted-foreground"
                >
                    <Info class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    <span>{{ card.card_image }}</span>
                </div>
            </div>

            <!-- Datos -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border lg:col-span-2"
            >
                <div class="mb-6 flex items-center gap-2">
                    <Info class="h-5 w-5 text-primary" />

                    <div>
                        <h2 class="text-base font-semibold">
                            Información de la tarjeta
                        </h2>

                        <p class="text-xs text-muted-foreground">
                            Datos generales y configuración.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-6">
                    <!-- Especie -->
                    <div
                        class="rounded-lg border border-sidebar-border p-5"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Especie
                        </p>

                        <p class="mt-1 font-medium">
                            {{ card.species.common_name }}
                        </p>

                        <p class="mt-1 text-sm italic text-muted-foreground">
                            {{ card.species.scientific_name }}
                        </p>
                    </div>

                    <!-- Información secundaria -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-lg border border-sidebar-border p-5"
                        >
                            <p class="text-xs text-muted-foreground">
                                Edición
                            </p>

                            <p class="mt-1 font-medium">
                                {{ card.edition || 'General' }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border p-5"
                        >
                            <p class="text-xs text-muted-foreground">
                                Orden
                            </p>

                            <p class="mt-1 font-medium">
                                {{ card.sort_order }}
                            </p>
                        </div>
                    </div>

                    <!-- Rareza -->
                    <div
                        class="rounded-lg border border-sidebar-border p-5"
                    >
                        <p class="text-xs text-muted-foreground">
                            Rareza
                        </p>

                        <div class="mt-2">
                            <span
                                class="inline-flex rounded-full border px-3 py-1.5 text-xs font-medium"
                                :class="getRarityClasses(card.rarity)"
                            >
                                {{ getRarityLabel(card.rarity) }}
                            </span>
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

                        <div
                            v-else
                            class="mt-3 flex items-center gap-2 rounded-lg border border-dashed border-sidebar-border p-4 text-sm text-muted-foreground"
                        >
                            <Info class="h-4 w-4 shrink-0" />
                            <span>
                                Esta tarjeta no tiene una descripción.
                            </span>
                        </div>
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
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Box class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold">
                            Modelo 3D
                        </h2>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Información del modelo 3D asociado a esta tarjeta.
                        </p>
                    </div>
                </div>

                <span
                    v-if="card.model_file || card.model_url"
                    class="inline-flex w-fit items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/5 px-2.5 py-1 text-xs font-medium text-green-500"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    Disponible
                </span>

                <span
                    v-else
                    class="inline-flex w-fit items-center gap-1.5 rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                >
                    <CircleOff class="h-3.5 w-3.5" />
                    Sin modelo
                </span>
            </div>

            <!-- Hay modelo -->
            <div
                v-if="card.model_file || card.model_url"
                class="mt-6 grid gap-4 lg:grid-cols-2"
            >
                <!-- Información -->
                <div
                    class="rounded-lg border border-sidebar-border p-5"
                >
                    <div class="mb-5 flex items-center gap-2">
                        <FileBox class="h-4 w-4 text-primary" />

                        <h3 class="text-sm font-semibold">
                            Información del modelo
                        </h3>
                    </div>

                    <div class="space-y-5">
                        <div v-if="card.model_name">
                            <p class="text-xs text-muted-foreground">
                                Nombre
                            </p>

                            <p class="mt-1 font-medium">
                                {{ card.model_name }}
                            </p>
                        </div>

                        <div v-if="card.model_format">
                            <p class="text-xs text-muted-foreground">
                                Formato
                            </p>

                            <span
                                class="mt-1 inline-flex rounded-md border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium uppercase"
                            >
                                {{ card.model_format }}
                            </span>
                        </div>

                        <div v-if="card.model_file">
                            <p class="text-xs text-muted-foreground">
                                Archivo
                            </p>

                            <a
                                :href="
                                    modelFileUrl(card.model_file) ?? ''
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex max-w-full items-center gap-1.5 break-all text-sm text-primary hover:underline"
                            >
                                <FileBox class="h-4 w-4 shrink-0" />
                                <span>{{ card.model_file }}</span>
                                <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            </a>
                        </div>

                        <div v-if="card.model_url">
                            <p class="text-xs text-muted-foreground">
                                URL externa
                            </p>

                            <a
                                :href="card.model_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-1 inline-flex max-w-full items-center gap-1.5 break-all text-sm text-primary hover:underline"
                            >
                                <LinkIcon class="h-4 w-4 shrink-0" />
                                <span>{{ card.model_url }}</span>
                                <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Descripción -->
                <div
                    class="rounded-lg border border-sidebar-border p-5"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Info class="h-4 w-4 text-primary" />

                        <h3 class="text-sm font-semibold">
                            Descripción del modelo
                        </h3>
                    </div>

                    <p
                        v-if="card.model_description"
                        class="whitespace-pre-line text-sm leading-6 text-muted-foreground"
                    >
                        {{ card.model_description }}
                    </p>

                    <div
                        v-else
                        class="flex items-center gap-2 rounded-lg border border-dashed border-sidebar-border p-4 text-sm text-muted-foreground"
                    >
                        <Info class="h-4 w-4 shrink-0" />

                        <span>
                            Sin descripción del modelo.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sin modelo -->
            <div
                v-else
                class="mt-6 flex flex-col items-center justify-center rounded-lg border border-dashed border-sidebar-border px-6 py-12 text-center"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-muted/60 text-muted-foreground"
                >
                    <Box class="h-7 w-7" />
                </div>

                <p class="mt-4 font-medium">
                    No hay un modelo 3D asociado
                </p>

                <p class="mt-1 max-w-md text-sm text-muted-foreground">
                    Puedes agregar un archivo GLB/GLTF o una URL desde la
                    edición de la tarjeta.
                </p>

                <Link
                    :href="admin.cards.edit(card.id).url"
                    class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <Upload class="h-4 w-4" />
                    Agregar modelo 3D
                </Link>
            </div>
        </div>
    </div>
</template>