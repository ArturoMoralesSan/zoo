<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import admin from '@/routes/admin';
import ZooZoneMap from '@/components/ZooZoneMap.vue';

interface PolygonGeometry {
    type: 'Polygon';
    coordinates: number[][][];
}

interface ZooZone {
    id: number;
    name: string;
    description: string | null;
    type: string | null;
    geometry: PolygonGeometry | null;
    is_active: boolean;
    species_locations_count: number;
    map_markers_count: number;
}

const props = defineProps<{
    zone: ZooZone;
}>();
</script>

<template>
    <Head
        :title="`Detalles - ${zone.name}`"
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
                    <h1 class="text-2xl font-semibold">
                        Detalles de la zona
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Información completa de {{ zone.name }}.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <Link
                        :href="
                            admin.zooZones.index().url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Volver
                    </Link>

                    <Link
                        :href="
                            admin.zooZones.edit(
                                zone.id,
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
                <div class="lg:col-span-2">
                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Información general
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Datos principales de la zona.
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{ zone.name }}
                            </p>
                        </div>

                        <!-- Tipo -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Tipo
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    zone.type ||
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

                            <p
                                class="mt-1 text-sm"
                            >
                                {{ zone.id }}
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
                                    v-if="zone.is_active"
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

                        <!-- Ubicaciones -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Ubicaciones de especies
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    zone.species_locations_count
                                }}
                            </p>
                        </div>

                        <!-- Marcadores -->
                        <div>
                            <p
                                class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                            >
                                Marcadores del mapa
                            </p>

                            <p
                                class="mt-1 text-sm"
                            >
                                {{
                                    zone.map_markers_count
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- DESCRIPCIÓN -->
                    <div
                        v-if="zone.description"
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
                            {{ zone.description }}
                        </p>
                    </div>
                </div>

                <!-- RESUMEN -->
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Resumen
                    </p>

                    <div
                        class="mt-2 space-y-3"
                    >
                        <div
                            class="rounded-lg border border-sidebar-border p-4"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Polígono
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{
                                    zone.geometry
                                        ? 'Definido'
                                        : 'Sin definir'
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border p-4"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Ubicaciones
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{
                                    zone.species_locations_count
                                }}
                                ubicación(es)
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-sidebar-border p-4"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Marcadores
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{
                                    zone.map_markers_count
                                }}
                                marcador(es)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAPA -->
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
                        Ubicación de la zona
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Área geográfica registrada para esta zona.
                    </p>
                </div>

                <span
                    class="w-fit rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                >
                    {{
                        zone.geometry
                            ? 'Polígono definido'
                            : 'Sin polígono'
                    }}
                </span>
            </div>

            <div
                v-if="zone.geometry"
                class="mt-6"
            >
                <ZooZoneMap
                    :model-value="zone.geometry"
                    :readonly="true"
                    height="500px"
                />
            </div>

            <div
                v-else
                class="mt-6 rounded-lg border border-dashed border-sidebar-border p-10 text-center"
            >
                <p
                    class="text-sm text-muted-foreground"
                >
                    Esta zona no tiene un polígono definido.
                </p>

                <Link
                    :href="
                        admin.zooZones.edit(
                            zone.id,
                        ).url
                    "
                    class="mt-4 inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Definir ubicación
                </Link>
            </div>
        </div>

        <!-- INFORMACIÓN RELACIONADA -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h2
                    class="text-lg font-semibold"
                >
                    Información relacionada
                </h2>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Registros asociados actualmente a esta zona.
                </p>
            </div>

            <div
                class="mt-6 grid gap-4 sm:grid-cols-2"
            >
                <!-- Ubicaciones -->
                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Ubicaciones de especies
                    </p>

                    <p
                        class="mt-2 text-2xl font-semibold"
                    >
                        {{
                            zone.species_locations_count
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Ubicaciones asociadas a esta zona.
                    </p>
                </div>

                <!-- Marcadores -->
                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Marcadores del mapa
                    </p>

                    <p
                        class="mt-2 text-2xl font-semibold"
                    >
                        {{
                            zone.map_markers_count
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Marcadores asociados a esta zona.
                    </p>
                </div>
            </div>
        </div>

        <!-- ACCIONES -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-between"
        >
            <Link
                :href="
                    admin.zooZones.index().url
                "
                class="text-sm text-muted-foreground transition hover:text-foreground"
            >
                ← Volver al listado
            </Link>

            <Link
                :href="
                    admin.zooZones.edit(
                        zone.id,
                    ).url
                "
                class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                Editar zona
            </Link>
        </div>
    </div>
</template>