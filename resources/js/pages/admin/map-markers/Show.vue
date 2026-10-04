<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Edit,
    FileText,
    Info,
    LocateFixed,
    Map,
    MapPin,
    MapPinned,
    Tag,
    X,
} from 'lucide-vue-next';

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

const iconUrl = (icon: string | null | undefined): string | null => {
    if (!icon) {
        return null;
    }

    return `/storage/markers/${icon}`;
};
</script>

<template>
    <Head :title="`Detalles - ${marker.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- ===================================================== -->
        <!-- ENCABEZADO -->
        <!-- ===================================================== -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <MapPin class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Detalles del marker
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Información completa de {{ marker.name }}.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.mapMarkers.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- INFORMACIÓN GENERAL -->
        <!-- ===================================================== -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Info class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Información general
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Datos principales del marker.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <!-- Nombre -->
                <div>
                    <div class="flex items-center gap-2">
                        <Tag class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Nombre
                        </p>
                    </div>

                    <p class="mt-2 text-sm font-medium">
                        {{ marker.name }}
                    </p>
                </div>

                <!-- Tipo -->
                <div>
                    <div class="flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Tipo
                        </p>
                    </div>

                    <p class="mt-2 text-sm">
                        {{ marker.type || 'Sin especificar' }}
                    </p>
                </div>

                <!-- ID -->
                <div>
                    <div class="flex items-center gap-2">
                        <Tag class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            ID
                        </p>
                    </div>

                    <p class="mt-2 font-mono text-sm">
                        {{ marker.id }}
                    </p>
                </div>

                <!-- Estado -->
                <div>
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Estado
                        </p>
                    </div>

                    <div class="mt-2">
                        <span
                            v-if="marker.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            <CheckCircle2 class="h-3.5 w-3.5" />
                            Activo
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                        >
                            <X class="h-3.5 w-3.5" />
                            Inactivo
                        </span>
                    </div>
                </div>

                <!-- Icono -->
                <div>
                    <div class="flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Icono
                        </p>
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="flex h-12 w-12 items-center justify-center rounded-full border border-sidebar-border"
                            :style="
                                marker.color
                                    ? {
                                          backgroundColor: marker.color,
                                      }
                                    : undefined
                            "
                        >
                            <img
                                v-if="iconUrl(marker.icon)"
                                :src="iconUrl(marker.icon)!"
                                :alt="marker.name"
                                class="h-7 w-7 object-contain"
                            />

                            <span
                                v-else
                                class="text-xl"
                            >
                                📍
                            </span>
                        </span>

                        <div>
                            <p class="text-sm font-medium">
                                {{ marker.icon || 'Predeterminado' }}
                            </p>

                            <p
                                v-if="marker.icon"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                /storage/markers/{{ marker.icon }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Color -->
                <div>
                    <div class="flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Color
                        </p>
                    </div>

                    <div
                        v-if="marker.color"
                        class="mt-3 flex items-center gap-2"
                    >
                        <span
                            class="h-7 w-7 rounded-full border border-sidebar-border"
                            :style="{
                                backgroundColor: marker.color,
                            }"
                        />

                        <span class="font-mono text-sm">
                            {{ marker.color }}
                        </span>
                    </div>

                    <p
                        v-else
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        Sin especificar
                    </p>
                </div>

                <!-- Descripción -->
                <div
                    v-if="marker.description"
                    class="border-t border-sidebar-border/70 pt-5 dark:border-sidebar-border sm:col-span-2"
                >
                    <div class="flex items-center gap-2">
                        <FileText class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Descripción
                        </p>
                    </div>

                    <p class="mt-2 whitespace-pre-line text-sm leading-6">
                        {{ marker.description }}
                    </p>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- UBICACIÓN -->
        <!-- ===================================================== -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <MapPinned class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Ubicación
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Ubicación del marker dentro del zoológico.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-5 sm:grid-cols-2">
                <!-- Zona -->
                <div
                    class="rounded-lg border border-sidebar-border bg-accent/30 p-4 sm:col-span-2"
                >
                    <div class="flex items-center gap-2">
                        <Map class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Zona del zoológico
                        </p>
                    </div>

                    <p
                        v-if="marker.zone"
                        class="mt-2 text-base font-semibold"
                    >
                        {{ marker.zone.name }}
                    </p>

                    <p
                        v-else
                        class="mt-2 text-sm italic text-muted-foreground"
                    >
                        Sin zona asignada
                    </p>

                    <p
                        v-if="marker.zone?.type"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Tipo: {{ marker.zone.type }}
                    </p>
                </div>

                <!-- Latitud -->
                <div>
                    <div class="flex items-center gap-2">
                        <LocateFixed class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Latitud
                        </p>
                    </div>

                    <p class="mt-2 font-mono text-sm">
                        {{ Number(marker.latitude).toFixed(7) }}
                    </p>
                </div>

                <!-- Longitud -->
                <div>
                    <div class="flex items-center gap-2">
                        <LocateFixed class="h-4 w-4 text-muted-foreground" />

                        <p
                            class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Longitud
                        </p>
                    </div>

                    <p class="mt-2 font-mono text-sm">
                        {{ Number(marker.longitude).toFixed(7) }}
                    </p>
                </div>

                <!-- Google Maps -->
                <div class="sm:col-span-2">
                    <a
                        :href="`https://www.google.com/maps?q=${marker.latitude},${marker.longitude}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <MapPin class="h-4 w-4" />
                        Ver en Google Maps
                    </a>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- MAPA -->
        <!-- ===================================================== -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Map class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Mapa
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Ubicación visual del marker dentro de su zona.
                    </p>
                </div>
            </div>

            <div
                class="mt-6 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <MapMarkerMap
                    :geometry="marker.zone?.geometry ?? null"
                    :map-image="marker.zone?.map_image ?? null"
                    :map-image-bounds="
                        marker.zone?.map_image_bounds ?? null
                    "
                    :latitude="Number(marker.latitude)"
                    :longitude="Number(marker.longitude)"
                    :icon="marker.icon ?? 'poi.svg'"
                    :color="marker.color ?? '#22c55e'"
                    :readonly="true"
                />
            </div>

            <div
                v-if="
                    marker.zone?.map_image &&
                    marker.zone?.map_image_bounds
                "
                class="mt-4 flex items-start gap-3 rounded-lg border border-sidebar-border bg-muted/30 p-4"
            >
                <Info
                    class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                />

                <div>
                    <p class="text-sm font-medium">
                        Plano de la zona
                    </p>

                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        El plano se muestra como referencia visual y el
                        marker aparece sobre su ubicación registrada.
                    </p>
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- ACCIONES -->
        <!-- ===================================================== -->
        <div
            class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
        >
            <Link
                :href="admin.mapMarkers.index().url"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
            >
                <X class="h-4 w-4" />
                Cancelar
            </Link>

            <Link
                :href="admin.mapMarkers.edit(marker.id).url"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                <Edit class="h-4 w-4" />
                Editar marker
            </Link>
        </div>
    </div>
</template>