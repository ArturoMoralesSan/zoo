
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    KeyRound,
    Plus,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface Role {
    id: number;
    name: string;
    users_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RolesPagination {
    data: Role[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    roles: RolesPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = (): boolean => {
    return Boolean(search.value.trim());
};

const submitSearch = (): void => {
    router.get(
        admin.roles.index().url,
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

const clearFilters = (): void => {
    search.value = '';

    router.get(
        admin.roles.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteRole = async (role: Role): Promise<void> => {
    if (role.name === 'admin') {
        return;
    }

    const result = await Swal.fire({
        title: '¿Eliminar rol?',
        text: `Se eliminará el rol "${role.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#6B7280',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(admin.roles.destroy(role.id).url, {
        preserveScroll: true,
    });
};
defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Roles',
                href: admin.roles.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Roles" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Roles"
            description="Administra los roles y sus permisos."
            section="Administración"
            :icon="ShieldCheck"
            :action-icon="Plus"
            action-label="Nuevo rol"
            :action-href="admin.roles.create().url"
        />

        <DataTable
            :pagination="roles"
            :is-empty="roles.data.length === 0"
            :empty-colspan="3"
            :search="search"
            search-placeholder="Buscar rol..."
            :has-filters="hasFilters()"
            @update:search="search = $event"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-3 py-1 text-xs font-medium text-[#42604C]"
                >
                    <FilterX class="h-3.5 w-3.5" />
                    Búsqueda: {{ search }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Rol
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Usuarios
                    </th>

                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="role in roles.data"
                    :key="role.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <ShieldCheck class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-[#244334]">
                                        {{ role.name }}
                                    </span>

                                    <span
                                        v-if="role.name === 'admin'"
                                        class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                                    >
                                        <ShieldCheck class="h-3 w-3" />
                                        Protegido
                                    </span>
                                </div>

                                <div class="mt-0.5 flex items-center gap-1 text-xs text-[#718477]">
                                    <KeyRound class="h-3 w-3" />
                                    ID: {{ role.id }}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F9F6] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            <Users class="h-3.5 w-3.5" />
                            {{ role.users_count.toLocaleString('es-MX') }}
                            {{ role.users_count === 1 ? 'usuario' : 'usuarios' }}
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.roles.edit(role.id).url"
                        :show-delete="role.name !== 'admin'"
                        item-name="rol"
                        @delete="deleteRole(role)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <ShieldCheck class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron roles
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay roles que coincidan con la búsqueda realizada.'
                                : 'Todavía no hay roles registrados.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
