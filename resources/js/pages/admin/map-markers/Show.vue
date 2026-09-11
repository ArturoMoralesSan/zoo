<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import admin from '@/routes/admin';
import MapMarkerMap from '@/components/admin/MapMarkerMap.vue';

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

interface ZooZone {
    id: number;
    name: string;
    description?: string | null;
    type?: string | null;
    geometry?: GeoJsonGeometry | null;
    map_image?: string | null;
    map_image_bounds?: MapImageBounds | null;
}

interface MapMarker {
    id: number;
    name: string;
    description?: string | null;
    type?: string | null;
    latitude: number;
    longitude: number;
    icon?: string | null;
    color?: string | null;
    zone_id?: number | null;
    zone?: ZooZone | null;
    is_active: boolean;
    created_at?: string;
    updated_at?: string;
}

const props = defineProps<{
    marker: MapMarker;
}>();
</script>

<template>
    <Head
        :title="`Detalles - ${marker.name}`"
    />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- ===================================================== -->
        <!-- ENCABEZADO -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Detalles del marker
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Información completa de
                        {{ marker.name }}.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <Link
                        :href="admin.mapMarkers.index().url"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Volver
                    </Link>

                    <Link
                        :href="
                            admin.mapMarkers.edit(
                                marker.id,
                            ).url
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                    >
                        Editar
                    </Link>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- INFORMACIÓN GENERAL -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-lg font-semibold">
                    Información general
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Datos principales del marker.
                </p>
            </div>

            <div
                class="mt-6 grid gap-5 sm:grid-cols-2"
            >
                <!-- Nombre -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Nombre
                    </p>

                    <p class="mt-1 text-sm font-medium">
                        {{ marker.name }}
                    </p>
                </div>

                <!-- Tipo -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Tipo
                    </p>

                    <p class="mt-1 text-sm">
                        {{
                            marker.type ||
                            'Sin especificar'
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

                    <p class="mt-1 text-sm">
                        {{ marker.id }}
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
                            v-if="marker.is_active"
                            class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            Activo
                        </span>

                        <span
                            v-else
                            class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                        >
                            Inactivo
                        </span>
                    </div>
                </div>

                <!-- Icono -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Icono
                    </p>

                    <div
                        class="mt-2 flex items-center gap-3"
                    >
                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-full border"
                            :style="
                                marker.color
                                    ? {
                                          backgroundColor:
                                              marker.color,
                                      }
                                    : undefined
                            "
                        >
                            📍
                        </span>

                        <span class="text-sm">
                            {{
                                marker.icon ||
                                'Predeterminado'
                            }}
                        </span>
                    </div>
                </div>

                <!-- Color -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Color
                    </p>

                    <div
                        v-if="marker.color"
                        class="mt-2 flex items-center gap-2"
                    >
                        <span
                            class="h-6 w-6 rounded-full border border-sidebar-border"
                            :style="{
                                backgroundColor:
                                    marker.color,
                            }"
                        />

                        <span class="font-mono text-sm">
                            {{ marker.color }}
                        </span>
                    </div>

                    <p
                        v-else
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Sin especificar
                    </p>
                </div>

                <!-- Descripción -->

                <div
                    v-if="marker.description"
                    class="border-t border-sidebar-border/70 pt-5 dark:border-sidebar-border sm:col-span-2"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Descripción
                    </p>

                    <p
                        class="mt-2 whitespace-pre-line text-sm leading-6"
                    >
                        {{ marker.description }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- UBICACIÓN -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-lg font-semibold">
                    Ubicación
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Ubicación del marker dentro del
                    zoológico.
                </p>
            </div>

            <div
                class="mt-6 grid gap-5 sm:grid-cols-2"
            >
                <!-- Zona -->

                <div
                    class="rounded-lg border border-sidebar-border bg-accent/30 p-4 sm:col-span-2"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Zona del zoológico
                    </p>

                    <p
                        v-if="marker.zone"
                        class="mt-1 text-base font-semibold"
                    >
                        {{ marker.zone.name }}
                    </p>

                    <p
                        v-else
                        class="mt-1 text-sm italic text-muted-foreground"
                    >
                        Sin zona asignada
                    </p>

                    <p
                        v-if="marker.zone?.type"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Tipo:
                        {{ marker.zone.type }}
                    </p>
                </div>

                <!-- Latitud -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Latitud
                    </p>

                    <p class="mt-1 font-mono text-sm">
                        {{
                            Number(
                                marker.latitude,
                            ).toFixed(7)
                        }}
                    </p>
                </div>

                <!-- Longitud -->

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Longitud
                    </p>

                    <p class="mt-1 font-mono text-sm">
                        {{
                            Number(
                                marker.longitude,
                            ).toFixed(7)
                        }}
                    </p>
                </div>

                <!-- Google Maps -->

                <div class="sm:col-span-2">
                    <a
                        :href="`https://www.google.com/maps?q=${marker.latitude},${marker.longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Ver en Google Maps
                    </a>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- MAPA -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h2 class="text-lg font-semibold">
                    Mapa
                </h2>

                <p class="mt-1 text-sm text-muted-foreground">
                    Ubicación visual del marker dentro
                    de su zona.
                </p>
            </div>

            <div
                class="mt-6 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <MapMarkerMap
                    :geometry="
                        marker.zone?.geometry ??
                        null
                    "
                    :map-image="
                        marker.zone?.map_image ??
                        null
                    "
                    :map-image-bounds="
                        marker.zone?.map_image_bounds ??
                        null
                    "
                    :latitude="
                        Number(marker.latitude)
                    "
                    :longitude="
                        Number(marker.longitude)
                    "
                    :readonly="true"
                />
            </div>

            <div
                v-if="
                    marker.zone?.map_image &&
                    marker.zone?.map_image_bounds
                "
                class="mt-4 rounded-lg border border-sidebar-border bg-muted/30 p-4"
            >
                <p class="text-sm font-medium">
                    Plano de la zona
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    El plano se muestra como referencia visual y el marker
                    aparece sobre su ubicación registrada.
                </p>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- ACCIONES -->
        <!-- ===================================================== -->

        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-between"
        >
            <Link
                :href="admin.mapMarkers.index().url"
                class="text-sm text-muted-foreground transition hover:text-foreground"
            >
                ← Volver al listado
            </Link>

            <Link
                :href="
                    admin.mapMarkers.edit(
                        marker.id,
                    ).url
                "
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Editar marker
            </Link>
        </div>
    </div>
</template>
