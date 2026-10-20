
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    CircleOff,
    Clock3,
    FilterX,
    Image,
    MapPin,
    Plus,
    Users,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

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

interface Filters {
    search?: string;
    type?: string;
    status?: string;
    zoo_zone_id?: string;
}

const props = defineProps<{
    events: EventsPagination;
    filters: Filters;
    zones: Zone[];
    types: string[];
}>();

const search = ref(props.filters?.search ?? '');
const type = ref(props.filters?.type ?? '');
const status = ref(props.filters?.status ?? '');
const zooZoneId = ref(props.filters?.zoo_zone_id ?? '');

const hasFilters = () =>
    Boolean(
        search.value.trim() ||
        type.value ||
        status.value ||
        zooZoneId.value,
    );

const applyFilters = () => {
    router.get(
        admin.events.index().url,
        {
            search: search.value.trim() || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
            zoo_zone_id: zooZoneId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const submitSearch = () => {
    applyFilters();
};

const clearFilters = () => {
    search.value = '';
    type.value = '';
    status.value = '';
    zooZoneId.value = '';

    router.get(
        admin.events.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
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
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.events.destroy(event.id).url, {
                preserveScroll: true,
            });
        }
    });
};

const formatDate = (value: string | null) => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) return '—';

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(date);
};

const getStatusLabel = (event: EventItem) => {
    if (!event.is_active) return 'Inactivo';

    const now = new Date();
    const start = new Date(event.start_at);
    const end = event.end_at ? new Date(event.end_at) : null;

    if (start > now) return 'Próximo';
    if (end && end < now) return 'Finalizado';

    return 'En curso';
};

const getStatusClasses = (event: EventItem) => {
    if (!event.is_active) {
        return 'border-[#dce7df] bg-[#f2f7f4] text-[#718477]';
    }

    const now = new Date();
    const start = new Date(event.start_at);
    const end = event.end_at ? new Date(event.end_at) : null;

    if (start > now) {
        return 'border-[#cbdff0] bg-[#edf5fc] text-[#326a98]';
    }

    if (end && end < now) {
        return 'border-[#dce7df] bg-[#f2f7f4] text-[#718477]';
    }

    return 'border-[#c9e4d2] bg-[#dcefe2] text-[#176b3d]';
};

const getImageUrl = (image: string) => {
    if (/^https?:\/\//i.test(image)) return image;

    return `/storage/${image.replace(/^\/+/, '')}`;
};
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Eventos',
                href: admin.events.edit,
            },
        ],
    },
});
</script>

<template>
    <Head title="Eventos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Eventos"
            description="Administra los eventos y actividades del zoológico."
            section="Administración"
            :icon="CalendarDays"
            :action-icon="Plus"
            action-label="Nuevo evento"
            :action-href="admin.events.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="events"
            :is-empty="events.data.length === 0"
            :empty-colspan="7"
            search-placeholder="Buscar evento..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <div class="relative min-w-0">
                    <select
                        v-model="type"
                        aria-label="Filtrar por tipo"
                        class="h-[42px] w-full appearance-none rounded-lg border border-[#c8dccf] bg-white px-3 pr-9 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10"
                        @change="applyFilters"
                    >
                        <option value="">Todos los tipos</option>
                        <option
                            v-for="item in types"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>

                    <ChevronDown
                        :size="16"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#718477]"
                    />
                </div>

                <div class="relative min-w-0">
                    <select
                        v-model="zooZoneId"
                        aria-label="Filtrar por zona"
                        class="h-[42px] w-full appearance-none rounded-lg border border-[#c8dccf] bg-white px-3 pr-9 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10"
                        @change="applyFilters"
                    >
                        <option value="">Todas las zonas</option>
                        <option
                            v-for="zone in zones"
                            :key="zone.id"
                            :value="String(zone.id)"
                        >
                            {{ zone.name }}
                        </option>
                    </select>

                    <ChevronDown
                        :size="16"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#718477]"
                    />
                </div>

                <div class="relative min-w-0">
                    <select
                        v-model="status"
                        aria-label="Filtrar por estado"
                        class="h-[42px] w-full appearance-none rounded-lg border border-[#c8dccf] bg-white px-3 pr-9 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10"
                        @change="applyFilters"
                    >
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                    </select>

                    <ChevronDown
                        :size="16"
                        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#718477]"
                    />
                </div>
            </template>

            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>

                <span
                    v-if="type"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Tipo: {{ type }}
                </span>

                <span
                    v-if="zooZoneId"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Zona:
                    {{
                        zones.find(
                            (zone) => String(zone.id) === zooZoneId,
                        )?.name ?? zooZoneId
                    }}
                </span>

                <span
                    v-if="status"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Estado: {{ status === 'active' ? 'Activos' : 'Inactivos' }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th
                        v-for="heading in [
                            'Evento',
                            'Tipo',
                            'Fecha',
                            'Zona',
                            'Capacidad',
                            'Estado',
                            'Acciones',
                        ]"
                        :key="heading"
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                        :class="heading === 'Acciones' ? 'text-right' : ''"
                    >
                        {{ heading }}
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="event in events.data"
                    :key="event.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[230px] items-center gap-3">
                            <div
                                class="h-12 w-16 shrink-0 overflow-hidden rounded-lg border border-[#dce7df] bg-[#f2f7f4]"
                            >
                                <img
                                    v-if="event.image"
                                    :src="getImageUrl(event.image)"
                                    :alt="event.name"
                                    class="h-full w-full object-cover"
                                />

                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#718477]"
                                >
                                    <Image :size="20" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-semibold text-[#244334]">
                                        {{ event.name }}
                                    </p>

                                    <span
                                        v-if="event.is_featured"
                                        class="inline-flex items-center rounded-full border border-[#ead9a7] bg-[#fff8e5] px-2 py-0.5 text-[10px] font-semibold text-[#8a6715]"
                                    >
                                        Destacado
                                    </span>
                                </div>

                                <p
                                    v-if="event.description"
                                    class="mt-1 max-w-[240px] truncate text-xs text-[#718477]"
                                >
                                    {{ event.description }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ event.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="event.type"
                            class="inline-flex whitespace-nowrap rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                        >
                            {{ event.type }}
                        </span>

                        <span v-else class="text-sm text-[#84958a]">
                            —
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[190px] items-start gap-2">
                            <Clock3
                                :size="16"
                                class="mt-0.5 shrink-0 text-[#718477]"
                            />

                            <div>
                                <p class="whitespace-nowrap text-sm font-medium text-[#244334]">
                                    {{ formatDate(event.start_at) }}
                                </p>

                                <p
                                    v-if="event.end_at"
                                    class="mt-1 text-xs text-[#718477]"
                                >
                                    Hasta: {{ formatDate(event.end_at) }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div
                            v-if="event.zone"
                            class="flex min-w-[120px] items-center gap-2"
                        >
                            <MapPin
                                :size="16"
                                class="shrink-0 text-[#718477]"
                            />

                            <span class="text-sm text-[#435d4c]">
                                {{ event.zone.name }}
                            </span>
                        </div>

                        <span
                            v-else
                            class="whitespace-nowrap text-sm text-[#84958a]"
                        >
                            Sin zona
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[100px] items-center gap-2">
                            <Users
                                :size="16"
                                class="shrink-0 text-[#718477]"
                            />

                            <span
                                v-if="event.capacity"
                                class="whitespace-nowrap text-sm font-medium text-[#244334]"
                            >
                                {{ event.capacity.toLocaleString('es-MX') }}
                                personas
                            </span>

                            <span
                                v-else
                                class="whitespace-nowrap text-sm text-[#718477]"
                            >
                                Ilimitada
                            </span>
                        </div>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold"
                            :class="getStatusClasses(event)"
                        >
                            <CheckCircle2
                                v-if="getStatusLabel(event) === 'En curso'"
                                :size="14"
                            />

                            <Clock3
                                v-else-if="getStatusLabel(event) === 'Próximo'"
                                :size="14"
                            />

                            <CircleOff v-else :size="14" />

                            {{ getStatusLabel(event) }}
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.events.edit(event.id).url"
                        item-name="evento"
                        @delete="deleteEvent(event)"
                    />
                </tr>
            </template>

            <template #empty>
                <div
                    class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center"
                >
                    <div
                        class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]"
                    >
                        <FilterX v-if="hasFilters()" :size="27" />
                        <CalendarDays v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay eventos'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos eventos que coincidan con los filtros seleccionados. Intenta modificar tu búsqueda.'
                                : 'Todavía no hay eventos registrados para mostrar.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
