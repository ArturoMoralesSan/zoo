<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const props = defineProps<{
    marker: MapMarker;
    zones: ZooZone[];
}>();

const form = useForm({
    name: props.marker.name,
    type: props.marker.type ?? '',
    description: props.marker.description ?? '',
    zone_id: props.marker.zone_id,
    latitude: Number(props.marker.latitude),
    longitude: Number(props.marker.longitude),
    icon: props.marker.icon ?? '',
    color: props.marker.color ?? '',
    is_active: props.marker.is_active,
});

const selectedZone = computed<ZooZone | null>(() =>
    props.zones.find(
        (zone) => zone.id === form.zone_id,
    ) ?? null,
);

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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-2xl font-semibold">
                    Editar marker
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Modifica la información y ubicación del marker.
                </p>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                @submit.prevent="submit"
                class="space-y-6"
            >
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

                        <select
                            id="zone_id"
                            v-model="form.zone_id"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                <!-- Ubicación -->
                <div class="space-y-4">
                    <div>
                        <h2 class="text-sm font-medium">
                            Ubicación
                        </h2>

                        <p class="mt-1 text-xs text-muted-foreground">
                            Selecciona un punto dentro de la zona directamente
                            sobre el mapa. El plano es solo una referencia
                            visual.
                        </p>
                    </div>

                    <!-- Mapa -->
                    <MapMarkerMap
                        :geometry="
                            selectedZone?.geometry ?? null
                        "
                        :map-image="
                            selectedZone?.map_image ?? null
                        "
                        :map-image-bounds="
                            selectedZone?.map_image_bounds ?? null
                        "
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
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
                        <p class="text-sm font-medium">
                            Plano de la zona
                        </p>

                        <p class="mt-1 text-xs text-muted-foreground">
                            El plano se muestra como referencia. Puedes colocar
                            el marker en cualquier punto dentro de la zona,
                            incluso en áreas donde no existe imagen.
                        </p>
                    </div>

                    <!-- Mensaje sin zona -->
                    <div
                        v-if="!form.zone_id"
                        class="rounded-lg border border-dashed border-sidebar-border bg-muted/30 p-4 text-center"
                    >
                        <p class="text-sm font-medium">
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

                <!-- Icono y color -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Icono -->
                    <div class="space-y-2">
                        <label
                            for="icon"
                            class="text-sm font-medium"
                        >
                            Icono
                        </label>

                        <input
                            id="icon"
                            v-model="form.icon"
                            type="text"
                            placeholder="Ej. map-pin"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p class="text-xs text-muted-foreground">
                            Nombre del icono que utilizará el mapa.
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

                        <input
                            id="color"
                            v-model="form.color"
                            type="text"
                            placeholder="Ej. #22c55e"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p class="text-xs text-muted-foreground">
                            Color que tendrá el marker en el mapa.
                        </p>

                        <p
                            v-if="form.errors.color"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.color }}
                        </p>
                    </div>
                </div>

                <!-- Estado -->
                <div class="flex items-center gap-3">
                    <input
                        id="is_active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-sidebar-border"
                    />

                    <label
                        for="is_active"
                        class="text-sm font-medium"
                    >
                        Marker activo
                    </label>
                </div>

                <p
                    v-if="form.errors.is_active"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.is_active }}
                </p>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.mapMarkers.index().url"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
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
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Actualizando...'
                                : 'Actualizar marker'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
