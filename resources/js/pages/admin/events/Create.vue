<script setup lang="ts">
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronDown,
    Clock,
    FileImage,
    Info,
    MapPin,
    Save,
    Sparkles,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface Zone {
    id: number;
    name: string;
}

const props = defineProps<{
    zones: Zone[];
}>();

const form = useForm({
    name: '',
    description: '',
    type: '',
    start_at: '',
    end_at: '',
    zoo_zone_id: '',
    image: null as File | null,
    capacity: null as number | null,
    is_featured: false,
    is_active: true,
});

const submit = () => {
    form.post(admin.events.store().url, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Nuevo evento" />

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
                        <CalendarDays class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Nuevo evento
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Crea un nuevo evento o actividad para el zoológico.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.events.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-8"
                @submit.prevent="submit"
            >
                <!-- Información general -->
                <div class="space-y-6">
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Info class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="font-semibold">
                                Información general
                            </h2>

                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Define los datos principales del evento.
                            </p>
                        </div>
                    </div>

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
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <MapPin class="h-4 w-4 text-muted-foreground" />
                                Zona
                            </label>

                            <div class="relative">
                                <select
                                    id="zoo_zone_id"
                                    v-model="form.zoo_zone_id"
                                    class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option value="">
                                        Sin zona
                                    </option>

                                    <option
                                        v-for="zone in props.zones"
                                        :key="zone.id"
                                        :value="String(zone.id)"
                                    >
                                        {{ zone.name }}
                                    </option>
                                </select>

                                <ChevronDown
                                    class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                            </div>

                            <p
                                v-if="form.errors.zoo_zone_id"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.zoo_zone_id }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Fecha y capacidad -->
                <div class="space-y-6">
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Clock class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="font-semibold">
                                Fecha y capacidad
                            </h2>

                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Configura cuándo se realizará y cuántas
                                personas pueden asistir.
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
                                Déjalo vacío si el evento no tiene hora de
                                finalización.
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
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <Users class="h-4 w-4 text-muted-foreground" />
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
                            Déjalo vacío si el evento no tiene límite de
                            asistentes.
                        </p>

                        <p
                            v-if="form.errors.capacity"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.capacity }}
                        </p>
                    </div>
                </div>

                <!-- Descripción e imagen -->
                <div class="space-y-6">
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <FileImage class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="font-semibold">
                                Contenido
                            </h2>

                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Agrega información y material visual del
                                evento.
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
                            class="rounded-xl border border-dashed border-sidebar-border bg-muted/20 p-6 transition hover:bg-muted/30"
                        >
                            <input
                                id="image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition file:mr-4 file:border-0 file:bg-muted file:px-4 file:py-2 file:text-sm"
                                @change="
                                    form.image =
                                        ($event.target as HTMLInputElement)
                                            .files?.[0] ?? null
                                "
                            />

                            <p class="mt-2 text-xs text-muted-foreground">
                                JPG, JPEG, PNG o WEBP. Máximo 5 MB.
                            </p>
                        </div>

                        <p
                            v-if="form.errors.image"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.image }}
                        </p>
                    </div>
                </div>

                <!-- Configuración -->
                <div class="space-y-6">
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Star class="h-4 w-4" />
                        </div>

                        <div>
                            <h2 class="font-semibold">
                                Configuración
                            </h2>

                            <p class="mt-0.5 text-sm text-muted-foreground">
                                Define la visibilidad y estado del evento.
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Destacado -->
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-sidebar-border p-4 transition hover:bg-accent/30"
                        >
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-sidebar-border transition"
                                :class="
                                    form.is_featured
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <Check
                                    v-if="form.is_featured"
                                    class="h-3.5 w-3.5"
                                />

                                <input
                                    v-model="form.is_featured"
                                    type="checkbox"
                                    class="sr-only"
                                />
                            </span>

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
                            class="flex cursor-pointer items-start gap-3 rounded-xl border border-sidebar-border p-4 transition hover:bg-accent/30"
                        >
                            <span
                                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border border-sidebar-border transition"
                                :class="
                                    form.is_active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <Check
                                    v-if="form.is_active"
                                    class="h-3.5 w-3.5"
                                />

                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="sr-only"
                                />
                            </span>

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
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.events.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <X class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
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
                                ? 'Guardando...'
                                : 'Guardar evento'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>