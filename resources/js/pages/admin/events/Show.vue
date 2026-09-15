<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import {
    ArrowLeft,
    CalendarDays,
    Clock,
    Edit,
    Image as ImageIcon,
    MapPin,
    Star,
    Users,
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
    const start = new Date(
        props.event.start_at,
    );
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
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }

    if (!props.event.start_at) {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }

    const now = new Date();
    const start = new Date(
        props.event.start_at,
    );
    const end = props.event.end_at
        ? new Date(props.event.end_at)
        : null;

    if (start > now) {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    }

    if (end && end < now) {
        return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    }

    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
};
</script>

<template>
    <Head :title="event.name" />

    <div class="space-y-6 p-6">
        <!-- Encabezado -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <Link
                    :href="admin.events.index().url"
                    class="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Volver a eventos
                </Link>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-foreground"
                    >
                        {{ event.name }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            getStatusClasses()
                        "
                    >
                        {{ getStatusLabel() }}
                    </span>

                    <span
                        v-if="event.is_featured"
                        class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                    >
                        <Star
                            class="h-3.5 w-3.5"
                        />
                        Destacado
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-muted-foreground"
                >
                    Información y detalles del evento.
                </p>
            </div>

            <Link
                :href="
                    admin.events.edit(event.id).url
                "
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
            >
                <Edit class="h-4 w-4" />
                Editar evento
            </Link>
        </div>

        <!-- Información principal -->
        <div
            class="grid grid-cols-1 gap-6 lg:grid-cols-3"
        >
            <!-- Imagen -->
            <div
                class="overflow-hidden rounded-xl border border-sidebar-border bg-background shadow-sm lg:col-span-1"
            >
                <div
                    v-if="event.image"
                    class="aspect-[4/3] w-full overflow-hidden bg-muted"
                >
                    <img
                        :src="
                            `/storage/${event.image}`
                        "
                        :alt="event.name"
                        class="h-full w-full object-cover"
                    />
                </div>

                <div
                    v-else
                    class="flex aspect-[4/3] items-center justify-center bg-muted/30"
                >
                    <div
                        class="text-center text-muted-foreground"
                    >
                        <ImageIcon
                            class="mx-auto h-12 w-12"
                        />

                        <p
                            class="mt-3 text-sm"
                        >
                            Sin imagen
                        </p>
                    </div>
                </div>
            </div>

            <!-- Datos -->
            <div
                class="rounded-xl border border-sidebar-border bg-background p-6 shadow-sm lg:col-span-2"
            >
                <div class="mb-6">
                    <h2
                        class="text-base font-semibold text-foreground"
                    >
                        Información del evento
                    </h2>
                </div>

                <div
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2"
                >
                    <!-- Tipo -->
                    <div>
                        <p
                            class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            Tipo
                        </p>

                        <p
                            class="text-sm text-foreground"
                        >
                            {{ event.type || '—' }}
                        </p>
                    </div>

                    <!-- Zona -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <MapPin
                                class="h-3.5 w-3.5"
                            />
                            Zona
                        </p>

                        <p
                            class="text-sm text-foreground"
                        >
                            {{
                                event.zone?.name ??
                                'Sin zona'
                            }}
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
                            <CalendarDays
                                class="h-3.5 w-3.5"
                            />
                            Inicio
                        </p>

                        <p
                            class="text-sm text-foreground"
                        >
                            {{
                                formatDate(
                                    event.start_at,
                                )
                            }}
                        </p>
                    </div>

                    <!-- Finalización -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <Clock
                                class="h-3.5 w-3.5"
                            />
                            Finalización
                        </p>

                        <p
                            class="text-sm text-foreground"
                        >
                            {{
                                formatDate(
                                    event.end_at,
                                )
                            }}
                        </p>
                    </div>

                    <!-- Capacidad -->
                    <div>
                        <p
                            class="mb-1 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                        >
                            <Users
                                class="h-3.5 w-3.5"
                            />
                            Capacidad
                        </p>

                        <p
                            class="text-sm text-foreground"
                        >
                            {{
                                event.capacity
                                    ? event.capacity.toLocaleString(
                                          'es-MX',
                                      )
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
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="
                                getStatusClasses()
                            "
                        >
                            {{ getStatusLabel() }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Descripción -->
        <div
            class="rounded-xl border border-sidebar-border bg-background p-6 shadow-sm"
        >
            <div class="mb-4">
                <h2
                    class="text-base font-semibold text-foreground"
                >
                    Descripción
                </h2>
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

        <!-- Identificación -->
        <div
            class="rounded-xl border border-sidebar-border bg-background p-6 shadow-sm"
        >
            <div class="mb-4">
                <h2
                    class="text-base font-semibold text-foreground"
                >
                    Información del registro
                </h2>
            </div>

            <div
                class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        ID
                    </p>

                    <p
                        class="text-sm text-foreground"
                    >
                        #{{ event.id }}
                    </p>
                </div>

                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Slug
                    </p>

                    <p
                        class="break-all text-sm text-foreground"
                    >
                        {{ event.slug }}
                    </p>
                </div>

                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Creado
                    </p>

                    <p
                        class="text-sm text-foreground"
                    >
                        {{
                            formatDate(
                                event.created_at,
                            )
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        Actualizado
                    </p>

                    <p
                        class="text-sm text-foreground"
                    >
                        {{
                            formatDate(
                                event.updated_at,
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="admin.events.index().url"
                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium text-foreground transition hover:bg-muted"
            >
                Volver
            </Link>

            <Link
                :href="
                    admin.events.edit(event.id).url
                "
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-5 py-2 text-sm font-medium text-primary-foreground shadow-sm transition hover:bg-primary/90"
            >
                <Edit class="h-4 w-4" />
                Editar evento
            </Link>
        </div>
    </div>
</template>