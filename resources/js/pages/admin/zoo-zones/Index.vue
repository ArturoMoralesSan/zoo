<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import {
    Edit,
    MapPin,
    Plus,
    Search,
    Trash2,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

interface ZooZone {
    id: number;
    name: string;
    description: string | null;
    type: string | null;
    geometry: {
        type: string;
        coordinates: number[][][];
    } | null;
    is_active: boolean;
    species_locations_count: number;
    map_markers_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ZonesPagination {
    data: ZooZone[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    zones: ZonesPagination;
    filters: {
        search?: string;
        status?: string;
    };
}>();

const search = ref(
    props.filters?.search ?? '',
);

const status = ref(
    props.filters?.status ?? '',
);

const submitSearch = () => {
    router.get(
        admin.zooZones.index().url,
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    submitSearch();
};

const deleteZone = (zone: ZooZone) => {
    Swal.fire({
        title: '¿Eliminar zona?',
        text: `Se eliminará "${zone.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.zooZones.destroy(zone.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};
</script>

<template>
    <Head title="Zonas del zoológico" />

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
                        <MapPin class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">
                            Zonas del zoológico
                        </h1>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Administra las zonas geográficas del Zoológico Sahuatoba.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.zooZones.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nueva zona
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    class="flex w-full flex-col gap-2 md:max-w-3xl md:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar zona..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-9 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <select
                        v-model="status"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 md:w-48"
                    >
                        <option value="">
                            Todos los estados
                        </option>

                        <option value="active">
                            Activas
                        </option>

                        <option value="inactive">
                            Inactivas
                        </option>
                    </select>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>

                    <button
                        v-if="search || status"
                        type="button"
                        class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                        @click="clearFilters"
                    >
                        Limpiar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ zones.total }} zonas
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
                                Zona
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Tipo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Ubicación
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
                            v-for="zone in zones.data"
                            :key="zone.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Zona -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <MapPin class="h-4 w-4" />
                                    </div>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ zone.name }}
                                        </div>

                                        <div
                                            v-if="zone.description"
                                            class="mt-1 max-w-md truncate text-xs text-muted-foreground"
                                        >
                                            {{ zone.description }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            ID: {{ zone.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="zone.type"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ zone.type }}
                                </span>

                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    Sin tipo
                                </span>
                            </td>

                            <!-- Ubicación -->
                            <td class="px-6 py-4">
                                <div class="flex items-start gap-2">
                                    <MapPin
                                        class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                                    />

                                    <div>
                                        <div class="font-medium">
                                            {{
                                                zone.geometry
                                                    ? 'Polígono definido'
                                                    : 'Sin polígono'
                                            }}
                                        </div>

                                        <div
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                zone.species_locations_count
                                            }}
                                            ubicación(es)
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                zone.map_markers_count
                                            }}
                                            marcador(es)
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="zone.is_active"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-green-500"
                                    />
                                    Activa
                                </span>

                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-red-500"
                                    />
                                    Inactiva
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.zooZones.show(
                                                zone.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <MapPin class="h-3.5 w-3.5" />
                                        Ver
                                    </Link>

                                    <Link
                                        :href="
                                            admin.zooZones.edit(
                                                zone.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-3.5 w-3.5" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteZone(zone)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="zones.data.length === 0"
                        >
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <MapPin class="h-5 w-5" />
                                    </div>

                                    <p class="font-medium">
                                        No se encontraron zonas.
                                    </p>

                                    <p class="mt-1 text-xs">
                                        Intenta cambiar los filtros de búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="zones.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in zones.links"
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
                        preserve-scroll
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