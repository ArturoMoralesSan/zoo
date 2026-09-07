<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import admin from '@/routes/admin';

interface ZooZone {
    id: number;
    name: string;
}

interface MapMarker {
    id: number;
    name: string;
    type: string | null;
    description: string | null;
    latitude: number;
    longitude: number;
    icon: string | null;
    color: string | null;
    zone: ZooZone | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MapMarkersPagination {
    data: MapMarker[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    markers: MapMarkersPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.mapMarkers.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

const deleteMarker = (marker: MapMarker) => {
    Swal.fire({
        title: '¿Eliminar marker?',
        text: `Se eliminará el marker "${marker.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.mapMarkers.destroy(marker.id).url,
                {
                    preserveScroll: true,
                }
            );
        }
    });
};
</script>

<template>
    <Head title="Markers" />

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
                        Markers
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra los puntos y ubicaciones del mapa del
                        zoológico.
                    </p>
                </div>

                <Link
                    :href="admin.mapMarkers.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo marker
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    @submit.prevent="submitSearch"
                    class="flex w-full gap-2 md:max-w-md"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar marker..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div class="text-sm text-muted-foreground">
                    {{ markers.total }} markers
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
                                Marker
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Tipo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Zona
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Coordenadas
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th class="px-6 py-4 text-right font-semibold">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="marker in markers.data"
                            :key="marker.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Marker -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-full border"
                                        :style="
                                            marker.color
                                                ? {
                                                      backgroundColor:
                                                          marker.color,
                                                  }
                                                : undefined
                                        "
                                    >
                                        📍
                                    </span>

                                    <div>
                                        <div class="font-medium">
                                            {{ marker.name }}
                                        </div>

                                        <div
                                            v-if="marker.description"
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{ marker.description }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ marker.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Tipo -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="marker.type"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ marker.type }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Sin tipo
                                </span>
                            </td>

                            <!-- Zona -->
                            <td class="px-6 py-4">
                                <span
                                    v-if="marker.zone"
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ marker.zone.name }}
                                </span>

                                <span
                                    v-else
                                    class="text-muted-foreground"
                                >
                                    Sin zona
                                </span>
                            </td>

                            <!-- Coordenadas -->
                            <td class="px-6 py-4">
                                <div class="font-mono text-xs">
                                    {{ Number(marker.latitude).toFixed(7) }}
                                </div>

                                <div
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ Number(marker.longitude).toFixed(7) }}
                                </div>
                            </td>

                            <!-- Estado -->
                            <td class="px-6 py-4">
                                <span
                                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        marker.is_active
                                            ? 'border-green-500/30 text-green-600'
                                            : 'border-sidebar-border text-muted-foreground'
                                    "
                                >
                                    {{
                                        marker.is_active
                                            ? 'Activo'
                                            : 'Inactivo'
                                    }}
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            admin.mapMarkers.show(
                                                marker.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Ver
                                    </Link>

                                    <Link
                                        :href="
                                            admin.mapMarkers.edit(
                                                marker.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="deleteMarker(marker)"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr v-if="markers.data.length === 0">
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron markers.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="markers.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in markers.links"
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