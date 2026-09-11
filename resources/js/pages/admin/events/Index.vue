<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

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
    start_at: string;
    end_at: string | null;
    zoo_zone_id: number | null;
    image: string | null;
    capacity: number | null;
    is_featured: boolean;
    is_active: boolean;
    zone: Zone | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface EventsPagination {
    data: EventItem[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    events: EventsPagination;
    filters: {
        search?: string;
        type?: string;
        status?: string;
        zoo_zone_id?: string;
    };
    zones: Zone[];
    types: string[];
}>();

const search = ref(props.filters?.search ?? '');
const type = ref(props.filters?.type ?? '');
const status = ref(props.filters?.status ?? '');
const zooZoneId = ref(props.filters?.zoo_zone_id ?? '');

const submitSearch = () => {
    router.get(
        admin.events.index().url,
        {
            search: search.value || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
            zoo_zone_id: zooZoneId.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const applyFilters = () => {
    router.get(
        admin.events.index().url,
        {
            search: search.value || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
            zoo_zone_id: zooZoneId.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteEvent = (event: EventItem) => {
    Swal.fire({
        title: '¿Eliminar evento?',
        text: `Se eliminará el evento "${event.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.events.destroy(event.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const formatDate = (value: string | null) => {
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

const getStatusLabel = (event: EventItem) => {
    if (!event.is_active) {
        return 'Inactivo';
    }

    const now = new Date();
    const start = new Date(event.start_at);
    const end = event.end_at
        ? new Date(event.end_at)
        : null;

    if (start > now) {
        return 'Próximo';
    }

    if (end && end < now) {
        return 'Finalizado';
    }

    return 'En curso';
};

const getStatusClasses = (event: EventItem) => {
    if (!event.is_active) {
        return 'border-sidebar-border text-muted-foreground';
    }

    const now = new Date();
    const start = new Date(event.start_at);
    const end = event.end_at
        ? new Date(event.end_at)
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
    <Head title="Eventos" />

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
                        Eventos
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Administra los eventos y actividades del zoológico.
                    </p>
                </div>

                <Link
                    :href="admin.events.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo evento
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador y filtros -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    @submit.prevent="submitSearch"
                    class="flex w-full flex-col gap-2 md:max-w-2xl md:flex-row"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar evento..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <select
                        v-model="type"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todos los tipos
                        </option>

                        <option
                            v-for="item in types"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>

                    <select
                        v-model="zooZoneId"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todas las zonas
                        </option>

                        <option
                            v-for="zone in zones"
                            :key="zone.id"
                            :value="String(zone.id)"
                        >
                            {{ zone.name }}
                        </option>
                    </select>

                    <select
                        v-model="status"
                        class="rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        @change="applyFilters"
                    >
                        <option value="">
                            Todos los estados
                        </option>

                        <option value="active">
                            Activos
                        </option>

                        <option value="inactive">
                            Inactivos
                        </option>
                    </select>

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div
                    class="text-sm text-muted-foreground"
                >
                    {{ events.total }} eventos
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Evento
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Tipo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Fecha
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Zona
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Capacidad
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 text-right font-semibold"
                            >
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="event in events.data"
                            :key="event.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Evento -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex items-center gap-3"
                                >
                                    <div
                                        class="h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-sidebar-border bg-muted"
                                    >
                                        <img
                                            v-if="event.image"
                                            :src="`/storage/${event.image}`"
                                            :alt="event.name"
                                            class="h-full w-full object-cover"
                                        />

                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
                                        >
                                            Sin imagen
                                        </div>
                                    </div>

                                    <div>
                                        <div
                                            class="font-medium"
                                        >
                                            {{ event.name }}

                                            <span
                                                v-if="
                                                    event.is_featured
                                                "
                                                class="ml-2 rounded-full border border-yellow-500/30 px-2 py-0.5 text-[10px] font-medium text-yellow-600"
                                            >
                                                Destacado
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                event.description
                                            "
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                event.description
                                            }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID:
                                            {{ event.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="event.type"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ event.type }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    —
                                </span>
                            </td>

                            <!-- Fecha -->
                            <td class="px-6 py-4">
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        formatDate(
                                            event.start_at,
                                        )
                                    }}
                                </div>

                                <div
                                    v-if="event.end_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    Hasta:
                                    {{
                                        formatDate(
                                            event.end_at,
                                        )
                                    }}
                                </div>
                            </td>

                            <!-- Zona -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="event.zone"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ event.zone.name }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Sin zona
                                </span>
                            </td>

                            <!-- Capacidad -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="event.capacity"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{
                                        event.capacity.toLocaleString(
                                            'es-MX',
                                        )
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Ilimitada
                                </span>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        getStatusClasses(
                                            event,
                                        )
                                    "
                                >
                                    {{
                                        getStatusLabel(
                                            event,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.events
                                                .edit(
                                                    event.id,
                                                ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="
                                            deleteEvent(
                                                event,
                                            )
                                        "
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="
                                events.data.length === 0
                            "
                        >
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron eventos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="events.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link, index
                    ) in events.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-2 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border hover:bg-accent'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="rounded-lg border border-sidebar-border px-3 py-2 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>