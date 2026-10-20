
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    KeyRound,
    Plus,
    ShieldCheck,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

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

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = () => {
    router.get(
        admin.permissions.index().url,
        {
            search: search.value.trim() || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = () => {
    search.value = '';

    router.get(
        admin.permissions.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
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
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
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

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Permisos',
                href: admin.permissions.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Permisos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Permisos"
            description="Administra los permisos disponibles en el sistema."
            section="Administración"
            :icon="KeyRound"
            :action-icon="Plus"
            action-label="Nuevo permiso"
            :action-href="admin.permissions.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="permissions"
            :is-empty="permissions.data.length === 0"
            :empty-colspan="5"
            search-placeholder="Buscar permiso..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th
                        v-for="heading in [
                            'Permiso',
                            'Módulo',
                            'Acción',
                            'Guard',
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
                    v-for="permission in permissions.data"
                    :key="permission.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Permiso -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[180px] items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#dce7df] bg-[#e4f2e8] text-[#187744]"
                            >
                                <KeyRound :size="18" />
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ permission.name }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ permission.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Módulo -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                        >
                            {{ moduleOf(permission.name) }}
                        </span>
                    </td>

                    <!-- Acción -->
                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex rounded-full border border-[#dce7df] px-2.5 py-1 text-xs font-medium text-[#718477]"
                        >
                            {{ actionOf(permission.name) || 'Sin acción' }}
                        </span>
                    </td>

                    <!-- Guard -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2">
                            <ShieldCheck
                                :size="16"
                                class="text-[#718477]"
                            />

                            <span
                                class="inline-flex rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                            >
                                {{ permission.guard_name }}
                            </span>
                        </div>
                    </td>

                    <!-- Acciones -->
                    <TableActions
                        :edit-url="admin.permissions.edit(permission.id).url"
                        item-name="permiso"
                        @delete="deletePermission(permission)"
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
                        <KeyRound v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay permisos'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos permisos que coincidan con tu búsqueda. Intenta con otro término.'
                                : 'Todavía no hay permisos registrados en el sistema.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
