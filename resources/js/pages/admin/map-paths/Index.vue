<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import admin from '@/routes/admin';

interface PathNode {
    id: number;
    lat: number;
    lng: number;
}

interface PathEdge {
    from: number;
    to: number;
}

interface PathData {
    nodes: PathNode[];
    edges: PathEdge[];
}

interface MapPath {
    id: number;
    name: string;
    description: string | null;
    coordinates: PathData;
    distance: number | null;
    estimated_time: number | null;
    is_active: boolean;
    order: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface MapPathsPagination {
    data: MapPath[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    mapPaths: MapPathsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(
    props.filters?.search ?? '',
);

const submitSearch = () => {
    router.get(
        admin.mapPaths.index().url,
        {
            search:
                search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteMapPath = (
    mapPath: MapPath,
) => {
    Swal.fire({
        title: '¿Eliminar camino?',
        text: `Se eliminará el camino "${mapPath.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText:
            'Sí, eliminar',
        cancelButtonText:
            'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.mapPaths.destroy(
                    mapPath.id,
                ).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const getNodeCount = (
    mapPath: MapPath,
): number => {
    return (
        mapPath.coordinates?.nodes
            ?.length ?? 0
    );
};

const getConnectionCount = (
    mapPath: MapPath,
): number => {
    return (
        mapPath.coordinates?.edges
            ?.length ?? 0
    );
};

const formatDistance = (
    distance: number | null,
) => {
    if (
        distance === null ||
        distance === undefined
    ) {
        return 'Sin calcular';
    }

    if (distance >= 1000) {
        return `${(
            distance / 1000
        ).toFixed(2)} km`;
    }

    return `${distance} m`;
};

const formatTime = (
    minutes: number | null,
) => {
    if (
        minutes === null ||
        minutes === undefined
    ) {
        return 'Sin calcular';
    }

    if (minutes === 1) {
        return '1 minuto';
    }

    return `${minutes} minutos`;
};
</script>

<template>
    <Head title="Caminos" />

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
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Caminos
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Administra la red de
                        caminos peatonales
                        internos del zoológico.
                    </p>
                </div>

                <Link
                    :href="
                        admin.mapPaths.create()
                            .url
                    "
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo camino
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
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="
                        submitSearch
                    "
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar camino..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

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
                    {{ mapPaths.total }}
                    caminos
                </div>
            </div>

            <!-- Tabla -->
            <div
                class="overflow-x-auto"
            >
                <table
                    class="w-full text-left text-sm"
                >
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Camino
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Nodos
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Conexiones
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Distancia
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Tiempo
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
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
                            v-for="mapPath in mapPaths.data"
                            :key="mapPath.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Camino -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="flex items-center gap-3"
                                >
                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-full border border-sidebar-border bg-muted"
                                    >
                                        🗺️
                                    </span>

                                    <div>
                                        <div
                                            class="font-medium"
                                        >
                                            {{
                                                mapPath.name
                                            }}
                                        </div>

                                        <div
                                            v-if="
                                                mapPath.description
                                            "
                                            class="max-w-xs truncate text-xs text-muted-foreground"
                                        >
                                            {{
                                                mapPath.description
                                            }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID:
                                            {{
                                                mapPath.id
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Nodos -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{
                                        getNodeCount(
                                            mapPath,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Conexiones -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{
                                        getConnectionCount(
                                            mapPath,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Distancia -->
                            <td
                                class="px-6 py-4"
                            >
                                {{
                                    formatDistance(
                                        mapPath.distance,
                                    )
                                }}
                            </td>

                            <!-- Tiempo -->
                            <td
                                class="px-6 py-4"
                            >
                                {{
                                    formatTime(
                                        mapPath.estimated_time,
                                    )
                                }}
                            </td>

                            <!-- Estado -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    v-if="mapPath.is_active"
                                    class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    Activo
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    Inactivo
                                </span>
                            </td>

                            <!-- Acciones -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.mapPaths.show(
                                                mapPath.id,
                                            ).url
                                        "
                                        class="rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        Ver
                                    </Link>

                                    <Link
                                        :href="
                                            admin.mapPaths.edit(
                                                mapPath.id,
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
                                            deleteMapPath(
                                                mapPath,
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
                                mapPaths.data
                                    .length ===
                                0
                            "
                        >
                            <td
                                colspan="7"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron
                                caminos.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="
                    mapPaths.last_page > 1
                "
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link,
                        index
                    ) in mapPaths.links"
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
