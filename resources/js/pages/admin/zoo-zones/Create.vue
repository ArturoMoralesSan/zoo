<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const form = useForm<{
    name: string;
    description: string;
    type: string;
    geometry: PolygonGeometry | null;
    map_image: File | null;
    map_image_bounds: MapImageBounds | null;
    is_active: boolean;
}>({
    name: '',
    description: '',
    type: '',
    geometry: null,
    map_image: null,
    map_image_bounds: null,
    is_active: true,
});

const imagePreview = ref<string | null>(null);

const handleImageChange = (event: Event) => {
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

    // Al cambiar la imagen se eliminan los bounds anteriores.
    // ZooZoneMap calculará unos nuevos automáticamente.
    form.map_image_bounds = null;

    imagePreview.value = URL.createObjectURL(file);
};

const removeImage = () => {
    form.map_image = null;
    form.map_image_bounds = null;

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value);
        imagePreview.value = null;
    }
};

const updateMapImageBounds = (bounds: MapImageBounds | null) => {
    form.map_image_bounds = bounds;
};

const canSubmit = computed(() => {
    if (form.processing) {
        return false;
    }

    if (!form.name.trim()) {
        return false;
    }

    if (!form.geometry) {
        return false;
    }

    // Si hay una imagen, debe existir su posición geográfica.
    if (form.map_image && !form.map_image_bounds) {
        return false;
    }

    return true;
});

const submit = () => {
    form.post('/admin/zoo-zones', {
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
    <Head title="Nueva zona" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <MapPinned class="h-6 w-6" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">
                            Nueva zona
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Crea una zona del zoológico y configura su plano
                            interactivo.
                        </p>
                    </div>
                </div>

                <Link
                    href="/admin/zoo-zones"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <form
            class="flex flex-col gap-6"
            @submit.prevent="submit"
        >
            <!-- Información -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="p-6">
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <Info class="h-5 w-5 text-primary" />

                            <div>
                                <h2 class="text-base font-semibold">
                                    Información de la zona
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Define los datos generales de la zona.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
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
                                placeholder="Ej. Zona principal"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.name"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

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
                                placeholder="Ej. Zoológico"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.type"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.type }}
                            </p>
                        </div>

                        <!-- Descripción -->
                        <div class="space-y-2 md:col-span-2">
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
                                placeholder="Describe esta zona..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.description"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <!-- Estado -->
                        <div class="md:col-span-2">
                            <div
                                class="flex items-center justify-between rounded-xl border border-sidebar-border bg-muted/10 p-4"
                            >
                                <label
                                    class="flex cursor-pointer items-center gap-3"
                                >
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-sidebar-border"
                                    />

                                    <span>
                                        <span
                                            class="block text-sm font-medium"
                                        >
                                            Zona activa
                                        </span>

                                        <span
                                            class="block text-xs text-muted-foreground"
                                        >
                                            La zona estará disponible para
                                            utilizarse en el mapa.
                                        </span>
                                    </span>
                                </label>

                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <Check class="h-4 w-4" />
                                </div>
                            </div>

                            <p
                                v-if="form.errors.is_active"
                                class="mt-1 text-sm text-red-500"
                            >
                                {{ form.errors.is_active }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Área -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="p-6">
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <Map class="h-5 w-5 text-primary" />

                            <div>
                                <h2 class="text-base font-semibold">
                                    Área de la zona
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Dibuja el área real de la zona directamente
                                    sobre el mapa.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <ZooZoneMap
                            v-model="form.geometry"
                            :map-image="form.map_image"
                            :map-image-bounds="form.map_image_bounds"
                            :height="'600px'"
                            @update:map-image-bounds="updateMapImageBounds"
                        />

                        <p
                            v-if="form.errors.geometry"
                            class="mt-2 text-sm text-red-500"
                        >
                            {{ form.errors.geometry }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Plano -->
            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
            >
                <div class="p-6">
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <ImageIcon class="h-5 w-5 text-primary" />

                            <div>
                                <h2 class="text-base font-semibold">
                                    Plano del zoológico
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Selecciona el plano que quieres utilizar.
                                    Después aparecerá directamente sobre el
                                    mapa para que puedas acomodarlo.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <!-- Sin imagen -->
                        <div
                            v-if="!form.map_image"
                            class="rounded-xl border-2 border-dashed border-sidebar-border p-8 text-center transition hover:border-primary/50"
                        >
                            <div class="mx-auto max-w-md">
                                <div
                                    class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                >
                                    <ImageIcon class="h-7 w-7" />
                                </div>

                                <h3 class="text-sm font-semibold">
                                    Selecciona el plano
                                </h3>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    JPG, JPEG, PNG o WEBP. Máximo 10 MB.
                                </p>

                                <label
                                    for="map_image"
                                    class="mt-5 inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                >
                                    <ImageIcon class="h-4 w-4" />
                                    Seleccionar imagen

                                    <input
                                        id="map_image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        class="hidden"
                                        @change="handleImageChange"
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- Imagen seleccionada -->
                        <div
                            v-else
                            class="space-y-4"
                        >
                            <div
                                class="flex flex-col gap-4 rounded-xl border border-sidebar-border bg-muted/10 p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <ImageIcon class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ form.map_image.name }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                (
                                                    form.map_image.size /
                                                    1024 /
                                                    1024
                                                ).toFixed(2)
                                            }}
                                            MB
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                                    @click="removeImage"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Quitar imagen
                                </button>
                            </div>

                            <!-- Preview -->
                            <div
                                v-if="imagePreview"
                                class="overflow-hidden rounded-xl border border-sidebar-border bg-muted/20"
                            >
                                <div
                                    class="flex items-center gap-3 border-b border-sidebar-border px-4 py-3"
                                >
                                    <ImageIcon
                                        class="h-4 w-4 text-primary"
                                    />

                                    <div>
                                        <p class="text-sm font-medium">
                                            Vista previa del archivo
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            El plano editable aparece
                                            directamente sobre el mapa de
                                            arriba.
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex max-h-[300px] items-center justify-center overflow-auto p-4"
                                >
                                    <img
                                        :src="imagePreview"
                                        alt="Vista previa del plano"
                                        class="max-h-[250px] max-w-full rounded-lg object-contain shadow-sm"
                                    />
                                </div>
                            </div>

                            <!-- Información -->
                            <div class="rounded-xl border border-sidebar-border bg-muted/10 p-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Check class="h-4 w-4" />
                                    </div>

                                    <div>
                                        <p class="text-sm font-medium">
                                            Plano listo para posicionar
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Usa el mapa de arriba para mover el
                                            plano y ajustar su tamaño. Las
                                            coordenadas se generan
                                            automáticamente.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Bounds generados -->
                            <div
                                v-if="form.map_image_bounds"
                                class="grid gap-3 rounded-xl border border-sidebar-border bg-background p-4 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div>
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Norte
                                    </p>

                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            Number(
                                                form.map_image_bounds.north,
                                            ).toFixed(7)
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Sur
                                    </p>

                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            Number(
                                                form.map_image_bounds.south,
                                            ).toFixed(7)
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Este
                                    </p>

                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            Number(
                                                form.map_image_bounds.east,
                                            ).toFixed(7)
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-xs text-muted-foreground"
                                    >
                                        Oeste
                                    </p>

                                    <p class="mt-1 text-sm font-medium">
                                        {{
                                            Number(
                                                form.map_image_bounds.west,
                                            ).toFixed(7)
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="form.errors.map_image"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.map_image }}
                            </p>

                            <p
                                v-if="form.errors.map_image_bounds"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.map_image_bounds }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div
                class="flex flex-col gap-3 border-t border-sidebar-border/70 px-0 pt-6 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-end"
            >
                <Link
                    href="/admin/zoo-zones"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Cancelar
                </Link>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />

                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                        />
                    </svg>

                    <Save
                        v-else
                        class="h-4 w-4"
                    />

                    {{
                        form.processing
                            ? 'Creando...'
                            : 'Crear zona'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
