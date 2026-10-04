<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock,
    Edit,
    Image as ImageIcon,
    Info,
    MapPin,
    Star,
    Users,
    X,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

interface Zone {
    id: number;
    name: string;
    type: string | null;
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
    zone: Zone | null;
    created_at: string | null;
    updated_at: string | null;
}

const props = defineProps<{
    event: EventItem;
}>();

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
};

const getStatusLabel = (): string => {
    if (!props.event.is_active) {
        return 'Inactivo';
    }

    if (!props.event.start_at) {
        return 'Sin fecha';
    }

    const now = new Date();
    const start = new Date(props.event.start_at);
    const end = props.event.end_at
        ? new Date(props.event.end_at)
        : null;

    if (start > now) {
        return 'Próximo';
    }

    if (end && end < now) {
        return 'Finalizado';
    }

    return 'En curso';
};

const getStatusClasses = (): string => {
    if (!props.event.is_active) {
        return 'border-sidebar-border text-muted-foreground';
    }

    if (!props.event.start_at) {
        return 'border-sidebar-border text-muted-foreground';
    }

    const now = new Date();
    const start = new Date(props.event.start_at);
    const end = props.event.end_at
        ? new Date(props.event.end_at)
        : null;

    if (start > now) {
        return 'border-blue-500/30 text-blue-500';
    }

    if (end && end < now) {
        return 'border-sidebar-border text-muted-foreground';
    }

    return 'border-green-500/30 text-green-500';
};
</script>

<template>
    <Head :title="event.name" />

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
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <CalendarDays class="h-5 w-5" />
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-semibold">
                                {{ event.name }}
                            </h1>

                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                                :class="getStatusClasses()"
                            >
                                {{ getStatusLabel() }}
                            </span>

                            <span
                                v-if="event.is_featured"
                                class="inline-flex items-center gap-1 rounded-full border border-yellow-500/30 px-2.5 py-1 text-xs font-medium text-yellow-600"
                            >
                                <Star class="h-3.5 w-3.5" />
                                Destacado
                            </span>
                        </div>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Información y detalles del evento.
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

        <!-- Información principal -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Imagen -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-5 flex items-center gap-3 border-b border-sidebar-border/70 pb-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <ImageIcon class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Imagen
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Imagen principal del evento.
                        </p>
                    </div>
                </div>

                <div
                    v-if="event.image"
                    class="overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                >
                    <img
                        :src="`/storage/${event.image}`"
                        :alt="event.name"
                        class="aspect-[4/3] h-full w-full object-cover"
                    />
                </div>

                <div
                    v-else
                    class="flex aspect-[4/3] items-center justify-center rounded-lg border border-dashed border-sidebar-border bg-muted/20"
                >
                    <div class="text-center text-muted-foreground">
                        <ImageIcon class="mx-auto h-10 w-10" />

                        <p class="mt-3 text-sm">
                            Sin imagen
                        </p>
                    </div>
                </div>
            </div>

            <!-- Información -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border lg:col-span-2"
            >
                <div
                    class="mb-6 flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Info class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Información del evento
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Datos principales y configuración del evento.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <!-- Tipo -->
                    <div>
                        <p
                            class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Tipo
                        </p>

                        <p class="text-sm">
                            {{ event.type || '—' }}
                        </p>
                    </div>

                    <!-- Zona -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <MapPin class="h-3.5 w-3.5" />
                            Zona
                        </p>

                        <p class="text-sm">
                            {{ event.zone?.name ?? 'Sin zona' }}
                        </p>

                        <p
                            v-if="event.zone?.type"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ event.zone.type }}
                        </p>
                    </div>

                    <!-- Inicio -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <CalendarDays class="h-3.5 w-3.5" />
                            Inicio
                        </p>

                        <p class="text-sm">
                            {{ formatDate(event.start_at) }}
                        </p>
                    </div>

                    <!-- Finalización -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <Clock class="h-3.5 w-3.5" />
                            Finalización
                        </p>

                        <p class="text-sm">
                            {{ formatDate(event.end_at) }}
                        </p>
                    </div>

                    <!-- Capacidad -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <Users class="h-3.5 w-3.5" />
                            Capacidad
                        </p>

                        <p class="text-sm">
                            {{
                                event.capacity
                                    ? event.capacity.toLocaleString('es-MX')
                                    : 'Ilimitada'
                            }}
                        </p>
                    </div>

                    <!-- Estado -->
                    <div>
                        <p
                            class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Estado
                        </p>

                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                            :class="getStatusClasses()"
                        >
                            {{ getStatusLabel() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descripción -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-5 flex items-center gap-3 border-b border-sidebar-border/70 pb-5 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Info class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Descripción
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Información proporcionada para describir el evento.
                    </p>
                </div>
            </div>

            <div
                v-if="event.description"
                class="whitespace-pre-line text-sm leading-6 text-muted-foreground"
            >
                {{ event.description }}
            </div>

            <p
                v-else
                class="text-sm text-muted-foreground"
            >
                No se ha agregado una descripción para este evento.
            </p>
        </div>

        <!-- Información del registro -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-5 flex items-center gap-3 border-b border-sidebar-border/70 pb-5 dark:border-sidebar-border"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <CalendarDays class="h-5 w-5" />
                </div>

                <div>
                    <h2 class="text-base font-semibold">
                        Información del registro
                    </h2>

                    <p class="text-sm text-muted-foreground">
                        Identificación y fechas de registro del evento.
                    </p>
                </div>
            </div>

            <div
                class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <!-- ID -->
                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        ID
                    </p>

                    <p class="text-sm">
                        #{{ event.id }}
                    </p>
                </div>

                <!-- Slug -->
                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Slug
                    </p>

                    <p class="break-all text-sm">
                        {{ event.slug }}
                    </p>
                </div>

                <!-- Creado -->
                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Creado
                    </p>

                    <p class="text-sm">
                        {{ formatDate(event.created_at) }}
                    </p>
                </div>

                <!-- Actualizado -->
                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Actualizado
                    </p>

                    <p class="text-sm">
                        {{ formatDate(event.updated_at) }}
                    </p>
                </div>
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

            <Link
                :href="admin.events.edit(event.id).url"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
            >
                <Edit class="h-4 w-4" />
                Editar evento
            </Link>
        </div>
    </div>
</template>