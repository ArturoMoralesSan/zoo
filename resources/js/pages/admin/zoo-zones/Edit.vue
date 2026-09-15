<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    ref,
} from 'vue';


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

/*
|--------------------------------------------------------------------------
| Normalizar bounds
|--------------------------------------------------------------------------
|
| Laravel puede enviar los valores del JSON como string.
| Los convertimos a number para evitar errores con toFixed().
|
*/

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
    is_active: Boolean(
        props.zone.is_active,
    ),
});

/*
|--------------------------------------------------------------------------
| Imagen existente
|--------------------------------------------------------------------------
*/

const existingImageUrl = computed(() => {
    if (!props.zone.map_image) {
        return null;
    }

    return `/storage/${props.zone.map_image}`;
});

/*
|--------------------------------------------------------------------------
| Imagen que debe utilizar el mapa
|--------------------------------------------------------------------------
|
| IMPORTANTE:
|
| - Si el usuario seleccionó una imagen nueva,
|   utilizamos el File.
|
| - Si no seleccionó una imagen nueva,
|   utilizamos la imagen existente de la BD.
|
| Esto permite que ZooZoneMap funcione tanto
| en Crear como en Editar.
|
*/

const mapImageForMap = computed<
    File | string | null
>(() => {
    if (form.map_image) {
        return form.map_image;
    }

    return props.zone.map_image;
});

/*
|--------------------------------------------------------------------------
| Imagen nueva
|--------------------------------------------------------------------------
*/

const imagePreview = ref<string | null>(
    null,
);

const handleImageChange = (
    event: Event,
) => {
    const input =
        event.target as HTMLInputElement;

    const file =
        input.files?.[0] ?? null;

    if (!file) {
        return;
    }

    /*
     * Liberar preview anterior.
     */
    if (imagePreview.value) {
        URL.revokeObjectURL(
            imagePreview.value,
        );

        imagePreview.value = null;
    }

    form.map_image = file;

    /*
     * Al seleccionar una nueva imagen,
     * se eliminan los bounds anteriores.
     *
     * ZooZoneMap calculará unos nuevos
     * automáticamente.
     */
    form.map_image_bounds = null;

    imagePreview.value =
        URL.createObjectURL(
            file,
        );
};

const removeNewImage = () => {
    form.map_image = null;

    form.map_image_bounds =
        normalizeBounds(
            props.zone.map_image_bounds,
        );

    if (imagePreview.value) {
        URL.revokeObjectURL(
            imagePreview.value,
        );

        imagePreview.value = null;
    }
};

const updateMapImageBounds = (
    bounds: MapImageBounds | null,
) => {
    form.map_image_bounds =
        normalizeBounds(bounds);
};

/*
|--------------------------------------------------------------------------
| Imagen que se está mostrando
|--------------------------------------------------------------------------
*/

const hasImage = computed(() => {
    return Boolean(
        mapImageForMap.value,
    );
});

/*
|--------------------------------------------------------------------------
| Puede guardar
|--------------------------------------------------------------------------
*/

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

    /*
     * Si hay una imagen nueva,
     * debe tener bounds.
     */
    if (
        form.map_image &&
        !form.map_image_bounds
    ) {
        return false;
    }

    return true;
});

/*
|--------------------------------------------------------------------------
| Actualizar
|--------------------------------------------------------------------------
*/

const submit = () => {
    form.put(
        `/admin/zoo-zones/${props.zone.id}`,
        {
            forceFormData: true,
        },
    );
};

onBeforeUnmount(() => {
    if (imagePreview.value) {
        URL.revokeObjectURL(
            imagePreview.value,
        );
    }
});
</script>

<template>
    <Head title="Editar zona" />

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
                    <h1
                        class="text-2xl font-semibold tracking-tight"
                    >
                        Editar zona
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Actualiza la información y configuración
                        del plano de la zona.
                    </p>
                </div>

                <Link
                    href="/admin/zoo-zones"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <form
            class="space-y-6"
            @submit.prevent="submit"
        >
            <!-- Información -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Información de la zona
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modifica los datos generales de la zona.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
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
                    <div
                        class="space-y-2 md:col-span-2"
                    >
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
                            class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.description"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Estado -->
                    <div
                        class="md:col-span-2"
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

                        <p
                            v-if="form.errors.is_active"
                            class="mt-1 text-sm text-red-500"
                        >
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Área -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Área de la zona
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modifica el área real de la zona directamente
                        sobre el mapa.
                    </p>
                </div>

                <ZooZoneMap
                    v-model="form.geometry"
                    :map-image="mapImageForMap"
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

            <!-- Plano -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Plano del zoológico
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Consulta el plano actual o selecciona uno
                        nuevo para reemplazarlo.
                    </p>
                </div>

                <!-- Imagen existente y sin imagen nueva -->
                <div
                    v-if="
                        existingImageUrl &&
                        !form.map_image
                    "
                    class="space-y-4"
                >
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium"
                            >
                                Plano actual
                            </p>

                            <p
                                class="mt-1 truncate text-xs text-muted-foreground"
                            >
                                {{ props.zone.map_image }}
                            </p>
                        </div>

                        <label
                            for="map_image"
                            class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        >
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

                    <div
                        class="overflow-hidden rounded-xl border border-sidebar-border bg-muted/20"
                    >
                        <div
                            class="border-b border-sidebar-border px-4 py-3"
                        >
                            <p
                                class="text-sm font-medium"
                            >
                                Plano actual
                            </p>

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Este es el archivo actualmente asociado
                                a la zona.
                            </p>
                        </div>

                        <div
                            class="flex max-h-[400px] items-center justify-center overflow-auto p-4"
                        >
                            <img
                                :src="existingImageUrl"
                                alt="Plano actual del zoológico"
                                class="max-h-[350px] max-w-full rounded-lg object-contain shadow-sm"
                            />
                        </div>
                    </div>

                    <!-- Bounds actuales -->
                    <div
                        v-if="form.map_image_bounds"
                        class="grid gap-3 rounded-lg border border-sidebar-border bg-background p-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Norte
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
                                {{
                                    Number(
                                        form.map_image_bounds.west,
                                    ).toFixed(7)
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-lg bg-muted/30 p-4"
                    >
                        <div
                            class="flex items-start gap-3"
                        >
                            <div
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-sm font-medium"
                                >
                                    Plano configurado
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    El plano actual conserva su
                                    posición geográfica guardada.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No existe imagen -->
                <div
                    v-else-if="
                        !existingImageUrl &&
                        !form.map_image
                    "
                    class="rounded-xl border-2 border-dashed border-sidebar-border p-8 text-center transition hover:border-primary/50"
                >
                    <div
                        class="mx-auto max-w-md"
                    >
                        <div
                            class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-muted"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-7 w-7 text-muted-foreground"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M3 16.5l4.5-4.5a2.121 2.121 0 013 0L15 16.5m-1.5-1.5l1.5-1.5a2.121 2.121 0 013 0L21 16.5M5.25 19.5h13.5A2.25 2.25 0 0021 17.25v-10.5A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5a2.25 2.25 0 002.25 2.25z"
                                />
                            </svg>
                        </div>

                        <h3
                            class="text-sm font-semibold"
                        >
                            Selecciona el plano
                        </h3>

                        <p
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            JPG, JPEG, PNG o WEBP. Máximo 10 MB.
                        </p>

                        <label
                            for="map_image"
                            class="mt-5 inline-flex cursor-pointer rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        >
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

                <!-- Imagen nueva -->
                <div
                    v-else
                    class="space-y-4"
                >
                    <div
                        class="flex flex-col gap-4 rounded-xl border border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium"
                            >
                                Nueva imagen seleccionada
                            </p>

                            <p
                                class="mt-1 truncate text-sm"
                            >
                                {{ form.map_image?.name }}
                            </p>

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
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
                            class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                            @click="removeNewImage"
                        >
                            Cancelar cambio
                        </button>
                    </div>

                    <!-- Preview -->
                    <div
                        v-if="imagePreview"
                        class="overflow-hidden rounded-xl border border-sidebar-border bg-muted/20"
                    >
                        <div
                            class="border-b border-sidebar-border px-4 py-3"
                        >
                            <p
                                class="text-sm font-medium"
                            >
                                Vista previa del nuevo plano
                            </p>

                            <p
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                El nuevo plano aparecerá sobre el mapa
                                para que puedas acomodarlo.
                            </p>
                        </div>

                        <div
                            class="flex max-h-[300px] items-center justify-center overflow-auto p-4"
                        >
                            <img
                                :src="imagePreview"
                                alt="Vista previa del nuevo plano"
                                class="max-h-[250px] max-w-full rounded-lg object-contain shadow-sm"
                            />
                        </div>
                    </div>

                    <!-- Información -->
                    <div
                        class="rounded-lg bg-muted/30 p-4"
                    >
                        <div
                            class="flex items-start gap-3"
                        >
                            <div
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-sm font-medium"
                                >
                                    Nuevo plano listo para posicionar
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Usa el mapa para mover el plano y
                                    ajustar su tamaño. Las coordenadas
                                    se generan automáticamente.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Bounds -->
                    <div
                        v-if="form.map_image_bounds"
                        class="grid gap-3 rounded-lg border border-sidebar-border bg-background p-4 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Norte
                            </p>

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

                            <p
                                class="mt-1 text-sm font-medium"
                            >
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

            <!-- Botones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
            >
                <Link
                    href="/admin/zoo-zones"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Cancelar
                </Link>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        v-if="form.processing"
                        class="mr-2 h-4 w-4 animate-spin"
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

                    {{
                        form.processing
                            ? 'Guardando...'
                            : 'Guardar cambios'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
