<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChevronDown,
    CircleDot,
    Info,
    MapPin,
    Palette,
    Save,
    Sparkles,
    Tag,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';

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
    geometry: GeoJsonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
}

interface MapMarker {
    id: number;
    name: string;
    type: string | null;
    description: string | null;
    zone_id: number | null;
    latitude: number;
    longitude: number;
    icon: string | null;
    color: string | null;
    is_active: boolean;
    zone?: ZooZone | null;
}

interface MapIcon {
    value: string;
    label: string;
}

const props = defineProps<{
    marker: MapMarker;
    zones: ZooZone[];
    mapIcons: MapIcon[];
}>();

const form = useForm({
    name: props.marker.name,
    type: props.marker.type ?? '',
    description: props.marker.description ?? '',
    zone_id: props.marker.zone_id,
    latitude: Number(props.marker.latitude),
    longitude: Number(props.marker.longitude),
    icon: props.marker.icon ?? 'poi.svg',
    color: props.marker.color ?? '#22c55e',
    is_active: props.marker.is_active,
});

const selectedZone = computed<ZooZone | null>(
    () =>
        props.zones.find(
            (zone) => zone.id === form.zone_id,
        ) ?? null,
);

const selectedIcon = computed<MapIcon | null>(
    () =>
        props.mapIcons.find(
            (mapIcon) => mapIcon.value === form.icon,
        ) ?? null,
);

const iconUrl = computed(() => {
    if (!form.icon) {
        return null;
    }

    return `/storage/markers/${form.icon}`;
});

const submit = (): void => {
    form.put(
        admin.mapMarkers.update(
            props.marker.id,
        ).url,
    );
};
</script>

<template>
    <Head title="Editar marker" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <MapPin class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar marker
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica la información y ubicación del marker
                            <span class="font-medium text-foreground">
                                {{ marker.name }}
                            </span>.
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

        <form
            @submit.prevent="submit"
            class="space-y-4"
        >
            <!-- Información general -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Tag class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Información general
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Modifica la información básica del marker.
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <!-- Nombre -->
                    <div class="space-y-2">
                        <label
                            for="name"
                            class="text-sm font-medium"
                        >
                            Nombre
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Entrada principal"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.name"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Tipo y Zona -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Tipo -->
                        <div class="space-y-2">
                            <label
                                for="type"
                                class="text-sm font-medium"
                            >
                                Tipo
                            </label>

                            <input
                                id="type"
                                v-model="form.type"
                                type="text"
                                placeholder="Ej. Entrada, servicio, restaurante"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.type"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.type }}
                            </p>
                        </div>

                        <!-- Zona -->
                        <div class="space-y-2">
                            <label
                                for="zone_id"
                                class="text-sm font-medium"
                            >
                                Zona
                            </label>

                            <div class="relative">
                                <select
                                    id="zone_id"
                                    v-model="form.zone_id"
                                    class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option :value="null">
                                        Selecciona una zona
                                    </option>

                                    <option
                                        v-for="zone in zones"
                                        :key="zone.id"
                                        :value="zone.id"
                                    >
                                        {{ zone.name }}
                                    </option>
                                </select>

                                <ChevronDown
                                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>

                            <p
                                v-if="form.errors.zone_id"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.zone_id }}
                            </p>

                            <p
                                v-if="
                                    form.zone_id &&
                                    !selectedZone?.geometry
                                "
                                class="text-xs text-amber-600"
                            >
                                Esta zona no tiene un área definida en el mapa.
                            </p>

                            <p
                                v-if="
                                    form.zone_id &&
                                    !selectedZone?.map_image
                                "
                                class="text-xs text-amber-600"
                            >
                                Esta zona no tiene un plano configurado.
                            </p>
                        </div>
                    </div>

                    <!-- Descripción -->
                    <div class="space-y-2">
                        <label
                            for="description"
                            class="text-sm font-medium"
                        >
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe este punto del zoológico..."
                            class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        ></textarea>

                        <p
                            v-if="form.errors.description"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Ubicación -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <MapPin class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Ubicación
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Ajusta la posición del marker sobre el mapa.
                        </p>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    <div class="rounded-lg border border-sidebar-border bg-muted/20 p-4">
                        <div class="flex items-start gap-3">
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />

                            <p class="text-sm text-muted-foreground">
                                Selecciona un punto dentro de la zona directamente
                                sobre el mapa. El plano es solo una referencia
                                visual.
                            </p>
                        </div>
                    </div>

                    <!-- Mapa -->
                    <MapMarkerMap
                        :geometry="selectedZone?.geometry ?? null"
                        :map-image="selectedZone?.map_image ?? null"
                        :map-image-bounds="
                            selectedZone?.map_image_bounds ?? null
                        "
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
                        :icon="form.icon"
                        :color="form.color"
                    />

                    <!-- Información del plano -->
                    <div
                        v-if="
                            form.zone_id &&
                            selectedZone?.map_image &&
                            selectedZone?.map_image_bounds
                        "
                        class="rounded-lg border border-sidebar-border bg-muted/30 p-4"
                    >
                        <div class="flex items-start gap-3">
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />

                            <div>
                                <p class="text-sm font-medium">
                                    Plano de la zona
                                </p>

                                <p class="mt-1 text-xs text-muted-foreground">
                                    El plano se muestra como referencia. Puedes
                                    colocar el marker en cualquier punto dentro
                                    de la zona, incluso en áreas donde no existe
                                    imagen.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje sin zona -->
                    <div
                        v-if="!form.zone_id"
                        class="rounded-lg border border-dashed border-sidebar-border bg-muted/30 p-4 text-center"
                    >
                        <div
                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <MapPin class="h-5 w-5" />
                        </div>

                        <p class="mt-3 text-sm font-medium">
                            Selecciona una zona
                        </p>

                        <p class="mt-1 text-xs text-muted-foreground">
                            Al seleccionar una zona se mostrará su plano y su
                            área en el mapa.
                        </p>
                    </div>

                    <!-- Coordenadas -->
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Latitud -->
                        <div class="space-y-2">
                            <label
                                for="latitude"
                                class="text-sm font-medium"
                            >
                                Latitud
                            </label>

                            <input
                                id="latitude"
                                v-model.number="form.latitude"
                                type="number"
                                step="any"
                                readonly
                                placeholder="Selecciona un punto en el mapa"
                                class="w-full rounded-lg border border-sidebar-border bg-muted/30 px-4 py-2.5 text-sm outline-none"
                            />

                            <p class="text-xs text-muted-foreground">
                                Se obtiene automáticamente al colocar el
                                marker.
                            </p>

                            <p
                                v-if="form.errors.latitude"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.latitude }}
                            </p>
                        </div>

                        <!-- Longitud -->
                        <div class="space-y-2">
                            <label
                                for="longitude"
                                class="text-sm font-medium"
                            >
                                Longitud
                            </label>

                            <input
                                id="longitude"
                                v-model.number="form.longitude"
                                type="number"
                                step="any"
                                readonly
                                placeholder="Selecciona un punto en el mapa"
                                class="w-full rounded-lg border border-sidebar-border bg-muted/30 px-4 py-2.5 text-sm outline-none"
                            />

                            <p class="text-xs text-muted-foreground">
                                Se obtiene automáticamente al colocar el
                                marker.
                            </p>

                            <p
                                v-if="form.errors.longitude"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.longitude }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Apariencia -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Palette class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Apariencia
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Configura el icono y color del marker.
                        </p>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <!-- Icono -->
                    <div class="space-y-2">
                        <label
                            for="icon"
                            class="text-sm font-medium"
                        >
                            Icono
                        </label>

                        <div class="flex gap-3">
                            <!-- Vista previa -->
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-sidebar-border"
                                :style="
                                    form.color
                                        ? {
                                              backgroundColor:
                                                  form.color,
                                          }
                                        : undefined
                                "
                            >
                                <img
                                    v-if="iconUrl"
                                    :src="iconUrl"
                                    :alt="
                                        selectedIcon?.label ??
                                        'Icono del marker'
                                    "
                                    class="h-7 w-7 object-contain"
                                />

                                <MapPin
                                    v-else
                                    class="h-5 w-5"
                                />
                            </div>

                            <!-- Selector -->
                            <div class="relative min-w-0 flex-1">
                                <select
                                    id="icon"
                                    v-model="form.icon"
                                    class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">
                                        Selecciona un icono
                                    </option>

                                    <option
                                        v-for="mapIcon in mapIcons"
                                        :key="mapIcon.value"
                                        :value="mapIcon.value"
                                    >
                                        {{ mapIcon.label }}
                                    </option>
                                </select>

                                <ChevronDown
                                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            {{
                                selectedIcon?.label ??
                                'Icono del marker'
                            }}
                        </p>

                        <p
                            v-if="form.errors.icon"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.icon }}
                        </p>
                    </div>

                    <!-- Color -->
                    <div class="space-y-2">
                        <label
                            for="color"
                            class="text-sm font-medium"
                        >
                            Color
                        </label>

                        <div class="flex gap-2">
                            <!-- Campo hexadecimal -->
                            <input
                                id="color"
                                v-model="form.color"
                                type="text"
                                placeholder="Ej. #22c55e"
                                class="min-w-0 flex-1 rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <!-- Color picker -->
                            <input
                                v-model="form.color"
                                type="color"
                                class="h-11 w-14 cursor-pointer rounded-lg border border-sidebar-border bg-background p-1"
                                title="Seleccionar color"
                            />
                        </div>

                        <!-- Vista previa -->
                        <div
                            class="flex items-center gap-3 rounded-lg border border-sidebar-border bg-muted/30 p-3"
                        >
                            <span
                                class="h-8 w-8 shrink-0 rounded-full border shadow-sm"
                                :style="{
                                    backgroundColor:
                                        form.color ||
                                        '#22c55e',
                                }"
                            ></span>

                            <div>
                                <p class="text-xs font-medium">
                                    Color seleccionado
                                </p>

                                <p class="text-xs text-muted-foreground">
                                    {{
                                        form.color ||
                                        '#22c55e'
                                    }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            Puedes escribir el código hexadecimal o utilizar
                            el selector de color.
                        </p>

                        <p
                            v-if="form.errors.color"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.color }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Estado -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <CircleDot class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Estado
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Define si el marker estará disponible en el mapa.
                        </p>
                    </div>
                </div>

                <div class="mt-6">
                    <label
                        for="is_active"
                        class="flex cursor-pointer items-center gap-3"
                    >
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded border transition"
                            :class="
                                form.is_active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-sidebar-border bg-background'
                            "
                        >
                            <Check
                                v-if="form.is_active"
                                class="h-3.5 w-3.5"
                            />
                        </span>

                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="sr-only"
                        />

                        <span class="text-sm font-medium">
                            Marker activo
                        </span>
                    </label>

                    <p class="mt-2 text-xs text-muted-foreground">
                        Los markers inactivos no estarán disponibles para los
                        usuarios del mapa público.
                    </p>

                    <p
                        v-if="form.errors.is_active"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>
            </div>

            <!-- Acciones -->
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

                <button
                    type="submit"
                    :disabled="
                        form.processing ||
                        !form.name ||
                        !form.zone_id ||
                        form.latitude === null ||
                        form.longitude === null
                    "
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Sparkles
                        v-if="form.processing"
                        class="h-4 w-4 animate-pulse"
                    />

                    <Save
                        v-else
                        class="h-4 w-4"
                    />

                    {{
                        form.processing
                            ? 'Actualizando...'
                            : 'Actualizar marker'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>