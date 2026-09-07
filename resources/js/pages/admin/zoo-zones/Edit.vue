<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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
}

const props = defineProps<{
    zone: ZooZone;
}>();

const form = useForm({
    name: props.zone.name ?? '',
    description: props.zone.description ?? '',
    type: props.zone.type ?? '',
    geometry: props.zone.geometry,
    is_active: props.zone.is_active,
});

const submit = () => {
    form.put(
        admin.zooZones.update(props.zone.id).url,
    );
};
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
                    <h1 class="text-2xl font-semibold">
                        Editar zona
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modifica la información y ubicación de la zona.
                    </p>
                </div>

                <Link
                    :href="
                        admin.zooZones.index().url
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
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
                        placeholder="Ej. Zona de felinos"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
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
                        placeholder="Describe brevemente esta zona..."
                        class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
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
                        placeholder="Ej. Exhibición, servicios, acceso..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.type"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.type }}
                    </p>
                </div>

                <!-- Ubicación -->
                <div class="space-y-2">
                    <div>
                        <label
                            class="text-sm font-medium"
                        >
                            Ubicación de la zona
                        </label>

                        <p
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Puedes modificar el polígono existente o eliminarlo y dibujar uno nuevo.
                        </p>
                    </div>

                    <ZooZoneMap
                        v-model="form.geometry"
                        height="500px"
                    />

                    <p
                        v-if="form.errors.geometry"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.geometry }}
                    </p>
                </div>

                <!-- Estado -->
                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <label
                        class="flex cursor-pointer items-center gap-3"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-sidebar-border text-primary focus:ring-primary"
                        />

                        <span>
                            <span class="block text-sm font-medium">
                                Zona activa
                            </span>

                            <span class="mt-1 block text-xs text-muted-foreground">
                                La zona estará disponible para utilizarse dentro del sistema.
                            </span>
                        </span>
                    </label>

                    <p
                        v-if="form.errors.is_active"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.is_active }}
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.zooZones.index().url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>