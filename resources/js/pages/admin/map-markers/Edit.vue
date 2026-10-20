<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    CircleDot,
    Info,
    MapPin,
    Palette,
    Save,
    Tag,
    X,
} from 'lucide-vue-next';
import { computed } from 'vue';

import MapMarkerMap from '@/components/admin/MapMarkerMap.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
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
    () => props.zones.find((zone) => zone.id === form.zone_id) ?? null,
);

const selectedIcon = computed<MapIcon | null>(
    () => props.mapIcons.find((mapIcon) => mapIcon.value === form.icon) ?? null,
);

const iconUrl = computed(() => {
    if (!form.icon) {
        return null;
    }

    return `/storage/markers/${form.icon}`;
});

const submit = (): void => {
    form.put(admin.mapMarkers.update(props.marker.id).url);
};
</script>

<template>
    <Head title="Editar marker" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar marker"
            :description="`Modifica la información y ubicación de ${props.marker.name}.`"
            section="Mapa del zoológico"
            :icon="MapPin"
            :action-icon="ArrowLeft"
            action-label="Volver a markers"
            :action-href="admin.mapMarkers.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Modifica la información básica de este punto del mapa."
                :icon="Tag"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="name" class="form-label">
                            Nombre <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Entrada principal"
                            :class="[
                                'form-input',
                                { 'form-input-error': form.errors.name },
                            ]"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="type" class="form-label">
                            Tipo
                        </label>

                        <input
                            id="type"
                            v-model="form.type"
                            type="text"
                            placeholder="Ej. Entrada, servicio, restaurante"
                            :class="[
                                'form-input',
                                { 'form-input-error': form.errors.type },
                            ]"
                        />

                        <p v-if="form.errors.type" class="form-error">
                            {{ form.errors.type }}
                        </p>

                        <p class="form-help">
                            Categoría o tipo de lugar que representa este marker.
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="zone_id" class="form-label">
                            Zona <span class="form-required">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="zone_id"
                                v-model="form.zone_id"
                                required
                                :class="[
                                    'form-select appearance-none',
                                    { 'form-select-error': form.errors.zone_id },
                                ]"
                            >
                                <option :value="null">
                                    Selecciona una zona
                                </option>

                                <option
                                    v-for="zone in props.zones"
                                    :key="zone.id"
                                    :value="zone.id"
                                >
                                    {{ zone.name }}
                                </option>
                            </select>

                            <ChevronDown
                                :size="16"
                                class="form-select-icon"
                            />
                        </div>

                        <p v-if="form.errors.zone_id" class="form-error">
                            {{ form.errors.zone_id }}
                        </p>

                        <p
                            v-if="form.zone_id && !selectedZone?.geometry"
                            class="form-help text-amber-700"
                        >
                            Esta zona no tiene un área definida en el mapa.
                        </p>

                        <p
                            v-if="form.zone_id && !selectedZone?.map_image"
                            class="form-help text-amber-700"
                        >
                            Esta zona no tiene un plano configurado.
                        </p>
                    </div>

                    <div class="form-field form-field-full">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe este punto del zoológico..."
                            :class="[
                                'form-textarea',
                                {
                                    'form-textarea-error':
                                        form.errors.description,
                                },
                            ]"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Ubicación -->
            <AdminCard
                title="Ubicación"
                description="Ajusta la posición actual del marker sobre el mapa."
                :icon="MapPin"
            >
                <div class="space-y-5">
                    <div class="form-info">
                        <Info class="form-info-icon" />

                        <p class="form-info-text">
                            Selecciona un punto dentro de la zona directamente
                            sobre el mapa. Puedes arrastrar el marker para
                            ajustar su ubicación. El plano sirve como
                            referencia visual.
                        </p>
                    </div>

                    <div
                        class="overflow-hidden rounded-xl border border-[#DCE7DF] bg-white"
                    >
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
                    </div>

                    <div
                        v-if="
                            form.zone_id &&
                            selectedZone?.map_image &&
                            selectedZone?.map_image_bounds
                        "
                        class="form-info"
                    >
                        <Info class="form-info-icon" />

                        <p class="form-info-text">
                            <span class="font-semibold">
                                Plano de la zona.
                            </span>
                            Puedes colocar el marker en cualquier punto
                            permitido de la zona, incluso en áreas donde no
                            exista una imagen de referencia.
                        </p>
                    </div>

                    <div
                        v-if="!form.zone_id"
                        class="rounded-xl border border-dashed border-[#C8DCCF] bg-[#F7FAF8] px-5 py-8 text-center"
                    >
                        <div
                            class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-[#E7F2EA] text-[#187744]"
                        >
                            <MapPin class="h-5 w-5" />
                        </div>

                        <p class="mt-3 text-sm font-semibold text-[#244334]">
                            Selecciona una zona
                        </p>

                        <p class="mt-1 text-sm text-[#718477]">
                            Al seleccionar una zona se mostrará su plano y su
                            área en el mapa.
                        </p>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="latitude" class="form-label">
                                Latitud
                            </label>

                            <input
                                id="latitude"
                                v-model.number="form.latitude"
                                type="number"
                                step="any"
                                readonly
                                placeholder="Selecciona un punto en el mapa"
                                :class="[
                                    'form-input bg-[#F7FAF8]',
                                    {
                                        'form-input-error':
                                            form.errors.latitude,
                                    },
                                ]"
                            />

                            <p class="form-help">
                                Se actualiza automáticamente al mover el
                                marker.
                            </p>

                            <p
                                v-if="form.errors.latitude"
                                class="form-error"
                            >
                                {{ form.errors.latitude }}
                            </p>
                        </div>

                        <div class="form-field">
                            <label for="longitude" class="form-label">
                                Longitud
                            </label>

                            <input
                                id="longitude"
                                v-model.number="form.longitude"
                                type="number"
                                step="any"
                                readonly
                                placeholder="Selecciona un punto en el mapa"
                                :class="[
                                    'form-input bg-[#F7FAF8]',
                                    {
                                        'form-input-error':
                                            form.errors.longitude,
                                    },
                                ]"
                            />

                            <p class="form-help">
                                Se actualiza automáticamente al mover el
                                marker.
                            </p>

                            <p
                                v-if="form.errors.longitude"
                                class="form-error"
                            >
                                {{ form.errors.longitude }}
                            </p>
                        </div>
                    </div>
                </div>
            </AdminCard>

            <!-- Apariencia -->
            <AdminCard
                title="Apariencia"
                description="Personaliza el icono y el color del marker."
                :icon="Palette"
            >
                <div class="form-grid">
                    <div class="form-field">
                        <label for="icon" class="form-label">
                            Icono
                        </label>

                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#DCE7DF]"
                                :style="{
                                    backgroundColor: form.color || '#22c55e',
                                }"
                            >
                                <img
                                    v-if="iconUrl"
                                    :src="iconUrl"
                                    :alt="
                                        selectedIcon?.label ??
                                        'Icono del marker'
                                    "
                                    class="h-8 w-8 object-contain"
                                />

                                <MapPin v-else class="h-5 w-5" />
                            </div>

                            <div class="form-select-wrapper min-w-0 flex-1">
                                <select
                                    id="icon"
                                    v-model="form.icon"
                                    :class="[
                                        'form-select appearance-none',
                                        {
                                            'form-select-error':
                                                form.errors.icon,
                                        },
                                    ]"
                                >
                                    <option value="">
                                        Selecciona un icono
                                    </option>

                                    <option
                                        v-for="mapIcon in props.mapIcons"
                                        :key="mapIcon.value"
                                        :value="mapIcon.value"
                                    >
                                        {{ mapIcon.label }}
                                    </option>
                                </select>

                                <ChevronDown
                                    :size="16"
                                    class="form-select-icon"
                                />
                            </div>
                        </div>

                        <p class="form-help">
                            {{ selectedIcon?.label ?? 'Icono del marker' }}
                        </p>

                        <p v-if="form.errors.icon" class="form-error">
                            {{ form.errors.icon }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="color" class="form-label">
                            Color
                        </label>

                        <div class="flex items-center gap-3">
                            <input
                                id="color"
                                v-model="form.color"
                                type="text"
                                placeholder="Ej. #22c55e"
                                :class="[
                                    'form-input',
                                    {
                                        'form-input-error':
                                            form.errors.color,
                                    },
                                ]"
                            />

                            <input
                                v-model="form.color"
                                type="color"
                                class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-[#DCE7DF] bg-white p-1"
                                title="Seleccionar color"
                                aria-label="Seleccionar color del marker"
                            />
                        </div>

                        <div
                            class="mt-3 flex items-center gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-3"
                        >
                            <span
                                class="h-9 w-9 shrink-0 rounded-full border border-[#DCE7DF]"
                                :style="{
                                    backgroundColor: form.color || '#22c55e',
                                }"
                            />

                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#244334]">
                                    Color seleccionado
                                </p>

                                <p class="text-xs text-[#718477]">
                                    {{ form.color || '#22c55e' }}
                                </p>
                            </div>
                        </div>

                        <p class="form-help">
                            Escribe un código hexadecimal o utiliza el
                            selector de color.
                        </p>

                        <p v-if="form.errors.color" class="form-error">
                            {{ form.errors.color }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Estado -->
            <AdminCard
                title="Estado"
                description="Define si el marker estará disponible en el mapa público."
                :icon="CircleDot"
            >
                <div class="form-field">
                    <label for="is_active" class="form-checkbox-row">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="form-checkbox"
                        />

                        <span>
                            <span class="form-checkbox-title block">
                                Marker activo
                            </span>

                            <span class="form-checkbox-description block">
                                Los markers inactivos no estarán disponibles
                                para los usuarios del mapa público.
                            </span>
                        </span>
                    </label>

                    <p v-if="form.errors.is_active" class="form-error">
                        {{ form.errors.is_active }}
                    </p>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-[#718477]">
                    Verifica la información y la ubicación antes de guardar
                    los cambios.
                </p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <CancelButton
                        :href="admin.mapMarkers.index().url"
                        :icon="X"
                        label="Cancelar"
                    />

                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="Save"
                        :loading="form.processing"
                        loading-text="Actualizando..."
                        :disabled="
                            form.processing ||
                            !form.name ||
                            !form.zone_id ||
                            form.latitude === null ||
                            form.longitude === null
                        "
                    >
                        Actualizar marker
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>