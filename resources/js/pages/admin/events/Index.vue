<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    Clock3,
    Edit,
    Image,
    MapPin,
    Plus,
    Search,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';
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
                        <h1 class="text-2xl font-semibold">
                            Eventos
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra los eventos y actividades del zoológico.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.events.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo evento
                </Link>
            </div>
        </div>

        <!-- Listado -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador y filtros -->
            <div
                class="border-b border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-5 flex items-center gap-3 border-b border-sidebar-border/70 pb-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Search class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold">
                            Buscar y filtrar
                        </h2>

                        <p class="text-sm text-muted-foreground">
                            Utiliza los filtros para encontrar un evento.
                        </p>
                    </div>
                </div>

                <form
                    @submit.prevent="submitSearch"
                    class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                >
                    <!-- Buscar -->
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar evento..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <!-- Tipo -->
                    <div class="relative">
                        <select
                            v-model="type"
                            class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                        <ChevronDown
                            class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>

                    <!-- Zona -->
                    <div class="relative">
                        <select
                            v-model="zooZoneId"
                            class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                        <ChevronDown
                            class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>

                    <!-- Estado -->
                    <div class="relative">
                        <select
                            v-model="status"
                            class="w-full appearance-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 pr-10 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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

                        <ChevronDown
                            class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                    </div>

                    <!-- Buscar -->
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div class="mt-4 flex justify-end text-sm text-muted-foreground">
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
                                <div class="flex items-center gap-3">
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
                                            class="flex h-full w-full items-center justify-center text-muted-foreground"
                                        >
                                            <Image class="h-5 w-5" />
                                        </div>
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ event.name }}

                                            <span
                                                v-if="event.is_featured"
                                                class="ml-2 inline-flex items-center gap-1 rounded-full border border-yellow-500/30 px-2 py-0.5 text-[10px] font-medium text-yellow-600"
                                            >
                                                Destacado
                                            </span>
                                        </div>

                                        <div
                                            v-if="event.description"
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{ event.description }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ event.id }}
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
                                <div class="flex items-start gap-2">
                                    <Clock3
                                        class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                                    />

                                    <div>
                                        <div class="font-medium">
                                            {{ formatDate(event.start_at) }}
                                        </div>

                                        <div
                                            v-if="event.end_at"
                                            class="text-xs text-muted-foreground"
                                        >
                                            Hasta:
                                            {{ formatDate(event.end_at) }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Zona -->
                            <td class="px-6 py-4">
                                <div
                                    v-if="event.zone"
                                    class="flex items-center gap-2"
                                >
                                    <MapPin
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span
                                        class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ event.zone.name }}
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Sin zona
                                </span>
                            </td>

                            <!-- Capacidad -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Users
                                        class="h-4 w-4 text-muted-foreground"
                                    />

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
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="getStatusClasses(event)"
                                >
                                    <CheckCircle2 class="h-3.5 w-3.5" />

                                    {{
                                        getStatusLabel(event)
                                    }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            admin.events
                                                .edit(event.id).url
                                        "
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteEvent(event)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="events.data.length === 0">
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-3"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <CalendarDays class="h-5 w-5" />
                                    </div>

                                    <div>
                                        <p class="font-medium">
                                            No se encontraron eventos.
                                        </p>

                                        <p class="mt-1 text-sm text-muted-foreground">
                                            Intenta cambiar los filtros de búsqueda.
                                        </p>
                                    </div>
                                </div>
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
                    v-for="(link, index) in events.links"
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