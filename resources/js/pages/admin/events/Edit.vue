<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import admin from '@/routes/admin';

interface Zone {
    id: number;
    name: string;
}

interface EventItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: string | null;
    start_at: string | null;
    end_at: string | null;
    zoo_zone_id: number | null;
    image: string | null;
    capacity: number | null;
    is_featured: boolean;
    is_active: boolean;
}

const props = defineProps<{
    event: EventItem;
    zones: Zone[];
}>();

const formatDateTimeLocal = (
    value: string | null,
): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(
        date.getMonth() + 1,
    ).padStart(2, '0');
    const day = String(
        date.getDate(),
    ).padStart(2, '0');
    const hours = String(
        date.getHours(),
    ).padStart(2, '0');
    const minutes = String(
        date.getMinutes(),
    ).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
    name: props.event.name,
    description: props.event.description ?? '',
    type: props.event.type ?? '',
    start_at: formatDateTimeLocal(
        props.event.start_at,
    ),
    end_at: formatDateTimeLocal(
        props.event.end_at,
    ),
    zoo_zone_id:
        props.event.zoo_zone_id !== null
            ? String(props.event.zoo_zone_id)
            : '',
    image: null as File | null,
    capacity: props.event.capacity,
    is_featured: props.event.is_featured,
    is_active: props.event.is_active,
    remove_image: false,
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(
            admin.events.update(props.event.id).url,
            {
                forceFormData: true,
            },
        );
};
</script>

<template>
    <Head :title="`Editar ${event.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-2xl font-semibold">
                    Editar evento
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Actualiza la información del evento
                    "{{ event.name }}".
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
                        placeholder="Ej. Alimentación de jirafas"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Tipo y zona -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Tipo -->
                    <div class="space-y-2">
                        <label
                            for="type"
                            class="text-sm font-medium"
                        >
                            Tipo de evento
                        </label>

                        <input
                            id="type"
                            v-model="form.type"
                            type="text"
                            placeholder="Ej. Educativo"
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
                            for="zoo_zone_id"
                            class="text-sm font-medium"
                        >
                            Zona
                        </label>

                        <select
                            id="zoo_zone_id"
                            v-model="form.zoo_zone_id"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">
                                Sin zona
                            </option>

                            <option
                                v-for="zone in zones"
                                :key="zone.id"
                                :value="String(zone.id)"
                            >
                                {{ zone.name }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.zoo_zone_id"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.zoo_zone_id }}
                        </p>
                    </div>
                </div>

                <!-- Fechas -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Inicio -->
                    <div class="space-y-2">
                        <label
                            for="start_at"
                            class="text-sm font-medium"
                        >
                            Fecha y hora de inicio
                        </label>

                        <input
                            id="start_at"
                            v-model="form.start_at"
                            type="datetime-local"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p
                            v-if="form.errors.start_at"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.start_at }}
                        </p>
                    </div>

                    <!-- Fin -->
                    <div class="space-y-2">
                        <label
                            for="end_at"
                            class="text-sm font-medium"
                        >
                            Fecha y hora de finalización
                        </label>

                        <input
                            id="end_at"
                            v-model="form.end_at"
                            type="datetime-local"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p class="text-xs text-muted-foreground">
                            Déjalo vacío si el evento no tiene hora de finalización.
                        </p>

                        <p
                            v-if="form.errors.end_at"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.end_at }}
                        </p>
                    </div>
                </div>

                <!-- Capacidad -->
                <div class="space-y-2">
                    <label
                        for="capacity"
                        class="text-sm font-medium"
                    >
                        Capacidad
                    </label>

                    <input
                        id="capacity"
                        v-model.number="form.capacity"
                        type="number"
                        min="1"
                        placeholder="Ej. 50"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Déjalo vacío si el evento no tiene límite de asistentes.
                    </p>

                    <p
                        v-if="form.errors.capacity"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.capacity }}
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
                        placeholder="Describe el evento..."
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Imagen -->
                <div class="space-y-2">
                    <label
                        for="image"
                        class="text-sm font-medium"
                    >
                        Imagen
                    </label>

                    <div
                        v-if="event.image && !form.remove_image"
                        class="mb-3"
                    >
                        <p class="mb-2 text-xs text-muted-foreground">
                            Imagen actual:
                        </p>

                        <img
                            :src="`/storage/${event.image}`"
                            :alt="event.name"
                            class="h-48 w-full rounded-lg border border-sidebar-border object-cover md:w-80"
                        />

                        <button
                            type="button"
                            class="mt-2 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                            @click="form.remove_image = true"
                        >
                            Eliminar imagen actual
                        </button>
                    </div>

                    <div
                        v-else-if="event.image && form.remove_image"
                        class="mb-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            La imagen actual será eliminada al guardar.
                        </p>

                        <button
                            type="button"
                            class="mt-2 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                            @click="form.remove_image = false"
                        >
                            Conservar imagen
                        </button>
                    </div>

                    <input
                        id="image"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition file:mr-4 file:border-0 file:bg-muted file:px-4 file:py-2 file:text-sm"
                        @change="
                            form.image =
                                ($event.target as HTMLInputElement).files?.[0] ?? null
                        "
                    />

                    <p class="text-xs text-muted-foreground">
                        Selecciona una imagen nueva solamente si deseas reemplazar la actual.
                        JPG, JPEG, PNG o WEBP. Máximo 5 MB.
                    </p>

                    <p
                        v-if="form.errors.image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.image }}
                    </p>

                    <p
                        v-if="form.errors.remove_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.remove_image }}
                    </p>
                </div>

                <!-- Configuración -->
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Destacado -->
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-sidebar-border p-4 transition hover:bg-accent/30"
                    >
                        <input
                            v-model="form.is_featured"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-sidebar-border"
                        />

                        <span>
                            <span class="block text-sm font-medium">
                                Evento destacado
                            </span>

                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                            >
                                Marca el evento como destacado.
                            </span>
                        </span>
                    </label>

                    <!-- Activo -->
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border border-sidebar-border p-4 transition hover:bg-accent/30"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-sidebar-border"
                        />

                        <span>
                            <span class="block text-sm font-medium">
                                Evento activo
                            </span>

                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                            >
                                El evento estará disponible en el sistema.
                            </span>
                        </span>
                    </label>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.events.index().url"
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
                                ? 'Actualizando...'
                                : 'Actualizar evento'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>