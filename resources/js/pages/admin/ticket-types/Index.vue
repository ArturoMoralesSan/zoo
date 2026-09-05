
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

import admin from '@/routes/admin';

interface TicketType {
    id: number;
    name: string;
    price: number | string;
    description: string | null;
    is_active: boolean;
    tickets_count?: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TicketTypePagination {
    data: TicketType[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    ticketTypes: TicketTypePagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(
    props.filters?.search ?? '',
);

const submitSearch = () => {
    router.get(
        admin.ticketTypes.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deleteTicketType = (
    item: TicketType,
) => {
    Swal.fire({
        title: '¿Eliminar tipo de boleto?',
        text: `Se eliminará "${item.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.ticketTypes.destroy(
                    item.id,
                ).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const formatPrice = (
    price: number | string,
) => {
    return Number(price).toLocaleString(
        'es-MX',
        {
            style: 'currency',
            currency: 'MXN',
        },
    );
};
</script>

<template>
    <Head title="Tipos de boleto" />

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
                        Tipos de boleto
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Administra las tarifas y tipos de acceso al zoológico.
                    </p>
                </div>

                <Link
                    :href="
                        admin.ticketTypes.create().url
                    "
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo tipo de boleto
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full flex-col gap-2 md:max-w-2xl md:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar tipo de boleto..."
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
                    {{ ticketTypes.total }} tipos
                </div>
            </div>

            <!-- Tabla -->
            <div class="overflow-x-auto">
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
                                Tipo
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Precio
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Descripción
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
                            v-for="item in ticketTypes.data"
                            :key="item.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    {{ item.name }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    ID: {{ item.id }}
                                </div>
                            </td>

                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    class="text-base font-semibold"
                                >
                                    {{
                                        formatPrice(
                                            item.price,
                                        )
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    v-if="
                                        item.description
                                    "
                                    class="text-sm"
                                >
                                    {{
                                        item.description
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    Sin descripción
                                </span>
                            </td>

                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    v-if="
                                        item.is_active
                                    "
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

                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.ticketTypes.edit(
                                                item.id,
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
                                            deleteTicketType(
                                                item,
                                            )
                                        "
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                ticketTypes.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="5"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron tipos de boleto.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="
                    ticketTypes.last_page > 1
                "
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link, index
                    ) in ticketTypes.links"
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
