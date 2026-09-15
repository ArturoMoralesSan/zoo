<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import ZooZoneMap from '@/components/admin/ZooZoneMap.vue';

interface MapImageBounds {
    north: number;
    south: number;
    east: number;
    west: number;
}

interface ZooZone {
    id: number;
    name: string;
    description: string | null;
    type: string | null;
    geometry: {
        type: string;
        coordinates: number[][][];
    } | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
    is_active: boolean;
    species_locations_count?: number;
    map_markers_count?: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    zone: ZooZone;
}>();

const imageUrl = computed<string | null>(() => {
    const image = props.zone.map_image;

    if (!image) {
        return null;
    }

    if (
        image.startsWith('http://') ||
        image.startsWith('https://') ||
        image.startsWith('blob:') ||
        image.startsWith('data:') ||
        image.startsWith('/')
    ) {
        return image;
    }

    return `/storage/${image}`;
});

const hasGeometry = computed(() => {
    return (
        props.zone.geometry !== null &&
        props.zone.geometry.type === 'Polygon' &&
        Array.isArray(props.zone.geometry.coordinates)
    );
});

const hasMapImage = computed(() => {
    return Boolean(
        props.zone.map_image &&
        props.zone.map_image_bounds
    );
});

const formattedCreatedAt = computed(() => {
    if (!props.zone.created_at) {
        return '—';
    }

    return new Date(
        props.zone.created_at
    ).toLocaleString('es-MX');
});

const formattedUpdatedAt = computed(() => {
    if (!props.zone.updated_at) {
        return '—';
    }

    return new Date(
        props.zone.updated_at
    ).toLocaleString('es-MX');
});

const editUrl = computed(() => {
    return `/admin/zoo-zones/${props.zone.id}/edit`;
});
</script>

<template>
    <Head :title="`Zona: ${zone.name}`" />

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
                        {{ zone.name }}
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Consulta la información de esta zona.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/admin/zoo-zones"
                        class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Volver
                    </Link>

                    <Link
                        :href="editUrl"
                        class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Editar zona
                    </Link>
                </div>
            </div>
        </div>

        <!-- Información general -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2 class="text-lg font-semibold">
                    Información general
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Datos principales de la zona.
                </p>
            </div>

            <div
                class="grid gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <div>
                    <p class="text-sm text-muted-foreground">
                        Nombre
                    </p>

                    <p class="mt-1 font-medium">
                        {{ zone.name }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-muted-foreground">
                        Tipo
                    </p>

                    <p class="mt-1 font-medium">
                        {{ zone.type || '—' }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-muted-foreground">
                        Estado
                    </p>

                    <p
                        class="mt-1 font-medium"
                        :class="
                            zone.is_active
                                ? 'text-green-600'
                                : 'text-red-500'
                        "
                    >
                        {{ zone.is_active ? 'Activa' : 'Inactiva' }}
                    </p>
                </div>

                <div class="md:col-span-2 lg:col-span-3">
                    <p class="text-sm text-muted-foreground">
                        Descripción
                    </p>

                    <p class="mt-1 whitespace-pre-line">
                        {{ zone.description || 'Sin descripción.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Resumen -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2 class="text-lg font-semibold">
                    Resumen
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Elementos relacionados con esta zona.
                </p>
            </div>

            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Especies ubicadas
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ zone.species_locations_count ?? 0 }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Marcadores
                    </p>

                    <p class="mt-2 text-2xl font-semibold">
                        {{ zone.map_markers_count ?? 0 }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Polígono
                    </p>

                    <p class="mt-2 font-semibold">
                        {{
                            hasGeometry
                                ? 'Configurado'
                                : 'Sin polígono'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="text-sm text-muted-foreground">
                        Plano
                    </p>

                    <p class="mt-2 font-semibold">
                        {{
                            hasMapImage
                                ? 'Configurado'
                                : 'Sin plano'
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Mapa -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2 class="text-lg font-semibold">
                    Mapa de la zona
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Vista geográfica de la zona.
                </p>
            </div>

            <div
                v-if="hasGeometry || hasMapImage"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <ZooZoneMap
                    :model-value="zone.geometry"
                    :readonly="true"
                    :map-image="zone.map_image"
                    :map-image-bounds="zone.map_image_bounds"
                    height="600px"
                />
            </div>

            <div
                v-else
                class="flex min-h-[300px] items-center justify-center rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="text-center">
                    <p class="font-medium">
                        No hay información geográfica configurada.
                    </p>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Edita la zona para agregar información geográfica.
                    </p>
                </div>
            </div>
        </div>

        <!-- Plano -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2 class="text-lg font-semibold">
                    Plano del zoológico
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Plano asociado a esta zona.
                </p>
            </div>

            <div
                v-if="imageUrl"
                class="space-y-6"
            >
                <div
                    class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-muted/20 dark:border-sidebar-border"
                >
                    <img
                        :src="imageUrl"
                        alt="Plano del zoológico"
                        class="max-h-[700px] w-full object-contain"
                    />
                </div>

                <div
                    class="grid gap-6 md:grid-cols-2"
                >
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Archivo
                        </p>

                        <p class="mt-1 break-all font-medium">
                            {{ zone.map_image }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm text-muted-foreground">
                            Estado
                        </p>

                        <p class="mt-1 font-medium text-green-600">
                            Plano configurado
                        </p>
                    </div>
                </div>

                <div
                    v-if="zone.map_image_bounds"
                    class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <p class="mb-4 text-sm font-medium">
                        Coordenadas del plano
                    </p>

                    <div
                        class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Norte
                            </p>

                            <p class="mt-1 font-mono text-sm">
                                {{
                                    Number(
                                        zone.map_image_bounds.north
                                    ).toFixed(7)
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-muted-foreground">
                                Sur
                            </p>

                            <p class="mt-1 font-mono text-sm">
                                {{
                                    Number(
                                        zone.map_image_bounds.south
                                    ).toFixed(7)
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-muted-foreground">
                                Este
                            </p>

                            <p class="mt-1 font-mono text-sm">
                                {{
                                    Number(
                                        zone.map_image_bounds.east
                                    ).toFixed(7)
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-muted-foreground">
                                Oeste
                            </p>

                            <p class="mt-1 font-mono text-sm">
                                {{
                                    Number(
                                        zone.map_image_bounds.west
                                    ).toFixed(7)
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex min-h-[180px] items-center justify-center rounded-xl border border-dashed border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="text-center">
                    <p class="font-medium">
                        Esta zona no tiene un plano configurado.
                    </p>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Puedes agregarlo desde Editar zona.
                    </p>
                </div>
            </div>
        </div>

        <!-- Información técnica -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h2 class="text-lg font-semibold">
                    Información técnica
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Información de registro de la zona.
                </p>
            </div>

            <div
                class="grid gap-6 md:grid-cols-2"
            >
                <div>
                    <p class="text-sm text-muted-foreground">
                        ID
                    </p>

                    <p class="mt-1 font-mono text-sm">
                        {{ zone.id }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-muted-foreground">
                        Tipo de geometría
                    </p>

                    <p class="mt-1 font-medium">
                        {{ zone.geometry?.type || '—' }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-muted-foreground">
                        Creada
                    </p>

                    <p class="mt-1 font-medium">
                        {{ formattedCreatedAt }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-muted-foreground">
                        Última actualización
                    </p>

                    <p class="mt-1 font-medium">
                        {{ formattedUpdatedAt }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
        >
            <Link
                href="/admin/zoo-zones"
                class="rounded-lg border border-sidebar-border px-4 py-2.5 text-center text-sm font-medium transition hover:bg-accent"
            >
                Volver
            </Link>

            <Link
                :href="editUrl"
                class="rounded-lg bg-primary px-4 py-2.5 text-center text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Editar zona
            </Link>
        </div>
    </div>
</template>