<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Image as ImageIcon,
    Info,
    Map,
    MapPinned,
    Save,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import ZooZoneMap from '@/components/admin/ZooZoneMap.vue';

interface PolygonGeometry {
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
    description: string | null;
    type: string | null;
    geometry: PolygonGeometry | null;
    map_image: string | null;
    map_image_bounds: MapImageBounds | null;
    is_active: boolean;
}

const props = defineProps<{
    zone: ZooZone;
}>();

const normalizeBounds = (
    bounds: MapImageBounds | null,
): MapImageBounds | null => {
    if (!bounds) {
        return null;
    }

    return {
        north: Number(bounds.north),
        south: Number(bounds.south),
        east: Number(bounds.east),
        west: Number(bounds.west),
    };
};

const form = useForm<{
    name: string;
    description: string;
    type: string;
    geometry: PolygonGeometry | null;
    map_image: File | null;
    map_image_bounds: MapImageBounds | null;
    is_active: boolean;
}>({
    name: props.zone.name ?? '',
    description: props.zone.description ?? '',
    type: props.zone.type ?? '',
    geometry: props.zone.geometry ?? null,
    map_image: null,
    map_image_bounds: normalizeBounds(
        props.zone.map_image_bounds,
    ),
    is_active: Boolean(props.zone.is_active),
});

const existingImageUrl = computed(() => {
    if (!props.zone.map_image) {
        return null;
    }

    return `/storage/${props.zone.map_image}`;
});

const mapImageForMap = computed<File | string | null>(() => {
    if (form.map_image) {
        return form.map_image;
    }

    return props.zone.map_image;
});

const imagePreview = ref<string | null>(null);

const handleImageChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    if (!file) {
        return;
    }

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = null;
    }

    form.map_image = file;
    form.map_image_bounds = null;
    imagePreview.value = URL.createObjectURL(file);
};

const removeNewImage = (): void => {
    form.map_image = null;
    form.map_image_bounds = normalizeBounds(
        props.zone.map_image_bounds,
    );

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = null;
    }
};

const updateMapImageBounds = (
    bounds: MapImageBounds | null,
): void => {
    form.map_image_bounds = normalizeBounds(bounds);
};

const hasImage = computed(() => {
    return Boolean(mapImageForMap.value);
});

const canSubmit = computed(() => {
    if (form.processing || !form.name.trim()) {
        return false;
    }

    if (!form.geometry) {
        return false;
    }

    if (form.map_image && !form.map_image_bounds) {
        return false;
    }

    return true;
});

const submit = (): void => {
    form.put(`/admin/zoo-zones/${props.zone.id}`, {
        forceFormData: true,
    });
};

onBeforeUnmount(() => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
    }
});
</script>

<template>
    <Head title="Editar zona" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar zona"
            description="Actualiza la información y configuración del plano de la zona."
            section="Zoológico"
            :icon="MapPinned"
            :action-icon="ArrowLeft"
            action-label="Volver a zonas"
            action-href="/admin/zoo-zones"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <!-- Información general -->
            <AdminCard
                title="Información de la zona"
                description="Modifica los datos generales de la zona."
                :icon="Info"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre de la zona
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Zona principal"
                            autocomplete="off"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="type" class="form-label">
                            Tipo de zona
                        </label>

                        <input
                            id="type"
                            v-model="form.type"
                            type="text"
                            placeholder="Ej. Zoológico"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.type,
                            }"
                        />

                        <p v-if="form.errors.type" class="form-error">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <div class="form-field md:col-span-2">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe esta zona..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.description,
                            }"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <ActiveSwitch
                            v-model="form.is_active"
                            label="Zona activa"
                            description="La zona estará disponible para utilizarse en el mapa."
                            :error="form.errors.is_active"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Área interactiva -->
            <AdminCard
                title="Área de la zona"
                description="Modifica el área real de la zona directamente sobre el mapa."
                :icon="Map"
            >
                <div class="overflow-hidden rounded-xl border border-[#E1EBE4]">
                    <ZooZoneMap
                        v-model="form.geometry"
                        :map-image="mapImageForMap"
                        :map-image-bounds="form.map_image_bounds"
                        height="600px"
                        @update:map-image-bounds="updateMapImageBounds"
                    />
                </div>

                <p v-if="form.errors.geometry" class="form-error">
                    {{ form.errors.geometry }}
                </p>
            </AdminCard>

            <!-- Plano del zoológico -->
            <AdminCard
                title="Plano del zoológico"
                description="Consulta el plano actual o selecciona uno nuevo para reemplazarlo."
                :icon="ImageIcon"
            >
                <div class="space-y-4">
                    <!-- Plano existente -->
                    <div
                        v-if="existingImageUrl && !form.map_image"
                        class="space-y-4"
                    >
                        <div
                            class="flex flex-col gap-4 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                                >
                                    <ImageIcon class="h-5 w-5" />
                                </div>

                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-[#244334]">
                                        Plano actual
                                    </p>

                                    <p class="mt-1 truncate text-xs text-[#718477]">
                                        {{ props.zone.map_image }}
                                    </p>
                                </div>
                            </div>

                            <label
                                for="map_image"
                                class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-[#187744] px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-[#14663A]"
                            >
                                <ImageIcon class="h-4 w-4" />
                                Reemplazar imagen

                                <input
                                    id="map_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    @change="handleImageChange"
                                />
                            </label>
                        </div>

                        <div class="form-preview">
                            <div class="flex items-center gap-3 px-4 py-3">
                                <ImageIcon class="h-4 w-4 shrink-0 text-[#187744]" />

                                <div>
                                    <p class="form-preview-name">
                                        Vista previa del plano actual
                                    </p>

                                    <p class="form-upload-description">
                                        Imagen actualmente asociada a la zona.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex max-h-[400px] items-center justify-center overflow-auto border-t border-[#E1EBE4] p-4"
                            >
                                <img
                                    :src="existingImageUrl"
                                    alt="Plano actual del zoológico"
                                    class="max-h-[350px] max-w-full rounded-lg object-contain"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- No existe un plano -->
                    <label
                        v-else-if="!existingImageUrl && !form.map_image"
                        for="map_image"
                        class="form-upload"
                    >
                        <span class="form-upload-icon">
                            <ImageIcon class="h-6 w-6" />
                        </span>

                        <span class="form-upload-title">
                            Selecciona el plano del zoológico
                        </span>

                        <span class="form-upload-description">
                            JPG, JPEG, PNG o WEBP. Máximo 10 MB.
                        </span>

                        <span class="form-button form-button-primary">
                            <ImageIcon class="h-4 w-4" />
                            Seleccionar imagen
                        </span>

                        <input
                            id="map_image"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="form-upload-input"
                            @change="handleImageChange"
                        />
                    </label>

                    <!-- Nueva imagen seleccionada -->
                    <div v-else class="space-y-4">
                        <div class="form-upload-selected">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="form-upload-icon h-10 w-10 shrink-0">
                                    <ImageIcon class="h-5 w-5" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="form-upload-selected-name">
                                        Nueva imagen seleccionada
                                    </p>

                                    <p class="form-upload-selected-size">
                                        {{ form.map_image?.name }}
                                    </p>

                                    <p class="form-upload-selected-size">
                                        {{
                                            (
                                                (form.map_image?.size ?? 0) /
                                                1024 /
                                                1024
                                            ).toFixed(2)
                                        }}
                                        MB
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="form-remove-button"
                                    aria-label="Cancelar cambio de imagen"
                                    title="Cancelar cambio de imagen"
                                    @click="removeNewImage"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div v-if="imagePreview" class="form-preview">
                            <div class="flex items-center gap-3 px-4 py-3">
                                <ImageIcon class="h-4 w-4 shrink-0 text-[#187744]" />

                                <div class="min-w-0">
                                    <p class="form-preview-name">
                                        Vista previa del nuevo plano
                                    </p>

                                    <p class="form-upload-description">
                                        El plano aparecerá sobre el mapa para que puedas ajustar su posición.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex max-h-[300px] items-center justify-center overflow-auto border-t border-[#E1EBE4] p-4"
                            >
                                <img
                                    :src="imagePreview"
                                    alt="Vista previa del nuevo plano"
                                    class="max-h-[250px] max-w-full rounded-lg object-contain"
                                />
                            </div>
                        </div>

                        <div class="form-info">
                            <Check class="form-info-icon h-5 w-5" />

                            <p class="form-info-text">
                                Usa el mapa para mover el plano y ajustar su tamaño. Las coordenadas geográficas se generan automáticamente.
                            </p>
                        </div>
                    </div>

                    <!-- Coordenadas del plano -->
                    <div
                        v-if="form.map_image_bounds"
                        class="grid grid-cols-1 gap-4 rounded-xl border border-[#E1EBE4] bg-[#FCFEFC] p-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p class="form-help">Norte</p>
                            <p class="mt-1 break-all text-sm font-medium text-[#244334]">
                                {{ form.map_image_bounds.north.toFixed(7) }}
                            </p>
                        </div>

                        <div>
                            <p class="form-help">Sur</p>
                            <p class="mt-1 break-all text-sm font-medium text-[#244334]">
                                {{ form.map_image_bounds.south.toFixed(7) }}
                            </p>
                        </div>

                        <div>
                            <p class="form-help">Este</p>
                            <p class="mt-1 break-all text-sm font-medium text-[#244334]">
                                {{ form.map_image_bounds.east.toFixed(7) }}
                            </p>
                        </div>

                        <div>
                            <p class="form-help">Oeste</p>
                            <p class="mt-1 break-all text-sm font-medium text-[#244334]">
                                {{ form.map_image_bounds.west.toFixed(7) }}
                            </p>
                        </div>
                    </div>

                    <p v-if="form.errors.map_image" class="form-error">
                        {{ form.errors.map_image }}
                    </p>

                    <p
                        v-if="form.errors.map_image_bounds"
                        class="form-error"
                    >
                        {{ form.errors.map_image_bounds }}
                    </p>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    href="/admin/zoo-zones"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Guardando..."
                    :disabled="!canSubmit"
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>