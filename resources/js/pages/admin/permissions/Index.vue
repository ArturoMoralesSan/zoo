<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import {
    Edit,
    KeyRound,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PermissionsPagination {
    data: Permission[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    permissions: PermissionsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const submitSearch = () => {
    router.get(
        admin.permissions.index().url,
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const deletePermission = (permission: Permission) => {
    Swal.fire({
        title: '¿Eliminar permiso?',
        text: `Se eliminará el permiso "${permission.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.permissions.destroy(permission.id).url,
                {
                    preserveScroll: true,
                },
            );
        }
    });
};

const moduleOf = (name: string) => {
    return name.split('.')[0] ?? name;
};

const actionOf = (name: string) => {
    return name.split('.')[1] ?? '';
};
</script>

<template>
    <Head title="Permisos" />

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
                        <KeyRound class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Permisos
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Administra los permisos disponibles en el sistema.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.permissions.create().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo permiso
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 dark:border-sidebar-border md:flex-row md:items-center md:justify-between"
            >
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="submitSearch"
                >
                    <div class="relative flex-1">
                        <Search
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />

                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar permiso..."
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <Search class="h-4 w-4" />
                        Buscar
                    </button>
                </form>

                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ShieldCheck class="h-4 w-4" />

                    {{ permissions.total }}

                    {{
                        permissions.total === 1
                            ? 'permiso'
                            : 'permisos'
                    }}
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
                                Permiso
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Módulo
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Acción
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Guard
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
                            v-for="permission in permissions.data"
                            :key="permission.id"
                            class="transition hover:bg-muted/30"
                        >
                            <!-- Permiso -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-sidebar-border bg-primary/10 text-primary"
                                    >
                                        <KeyRound class="h-4 w-4" />
                                    </span>

                                    <div class="min-w-0">
                                        <div class="font-medium">
                                            {{ permission.name }}
                                        </div>

                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            ID: {{ permission.id }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Módulo -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full border border-sidebar-border bg-muted/30 px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ moduleOf(permission.name) }}
                                </span>
                            </td>

                            <!-- Acción -->
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{ actionOf(permission.name) }}
                                </span>
                            </td>

                            <!-- Guard -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <ShieldCheck
                                        class="h-4 w-4 text-muted-foreground"
                                    />

                                    <span
                                        class="inline-flex rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ permission.guard_name }}
                                    </span>
                                </div>
                            </td>

                            <!-- Acciones -->
                            <td class="px-6 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        :href="
                                            admin.permissions.edit(
                                                permission.id,
                                            ).url
                                        "
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-sidebar-border px-3 py-2 text-xs font-medium transition hover:bg-accent"
                                    >
                                        <Edit class="h-4 w-4" />
                                        Editar
                                    </Link>

                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-500/30 px-3 py-2 text-xs font-medium text-red-500 transition hover:bg-red-500/10"
                                        @click="
                                            deletePermission(permission)
                                        "
                                    >
                                        <Trash2 class="h-4 w-4" />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="permissions.data.length === 0"
                        >
                            <td
                                colspan="5"
                                class="px-6 py-16 text-center"
                            >
                                <div
                                    class="flex flex-col items-center justify-center"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                                    >
                                        <KeyRound class="h-6 w-6" />
                                    </div>

                                    <h3
                                        class="mt-4 text-sm font-semibold text-foreground"
                                    >
                                        No se encontraron permisos
                                    </h3>

                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        No hay permisos que coincidan con la
                                        búsqueda.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="permissions.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(link, index) in permissions.links"
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