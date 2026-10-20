
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FilterX, Plus, Users } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';
import admin from '@/routes/admin';

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface UsersPagination {
    data: User[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    users: UsersPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = () => {
    router.get(
        admin.users.index().url,
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
        admin.users.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteUser = (user: User) => {
    Swal.fire({
        title: '¿Eliminar usuario?',
        text: `Se eliminará a "${user.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(admin.users.destroy(user.id).url, {
                preserveScroll: true,
            });
        }
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
                title: 'Usuarios',
                href: admin.users.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex h-full flex-1 flex-col gap-5 overflow-x-hidden p-4 sm:p-6">
        <PageHeader
            title="Usuarios"
            description="Administra los usuarios y sus roles."
            section="Administración"
            :icon="Users"
            :action-icon="Plus"
            action-label="Nuevo usuario"
            :action-href="admin.users.create().url"
        />

        <DataTable
            :pagination="users"
            :is-empty="users.data.length === 0"
            :empty-colspan="4"
            v-model:search="search"
            search-placeholder="Buscar usuario..."
            :has-filters="hasFilters()"
            @search="submitSearch"
            @clear-filters="clearFilters"
        >
            <template #active-filters>
                <div
                    v-if="search.trim()"
                    class="flex flex-wrap items-center gap-2"
                >
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-[#DCE7DF] bg-[#EDF4EF] px-3 py-1.5 text-xs font-medium text-[#244334]"
                    >
                        <FilterX class="h-3.5 w-3.5" />
                        Búsqueda: {{ search }}
                    </span>
                </div>
            </template>

            <template #table-head>
                <tr>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Usuario
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Correo
                    </th>
                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Roles
                    </th>
                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="user in users.data"
                    :key="user.id"
                    class="transition-colors hover:bg-[#F6F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <Users class="h-5 w-5" />
                            </div>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ user.name }}
                                </p>
                                <p class="mt-0.5 text-xs text-[#718277]">
                                    ID: {{ user.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 text-[#52665A] sm:px-6">
                        <span class="break-all">{{ user.email }}</span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="role in user.roles"
                                :key="role.id"
                                class="inline-flex items-center rounded-full border border-[#DCE7DF] bg-[#F6F9F6] px-2.5 py-1 text-xs font-medium text-[#385644]"
                            >
                                {{ role.name }}
                            </span>

                            <span
                                v-if="!user.roles.length"
                                class="text-xs italic text-[#87958B]"
                            >
                                Sin rol asignado
                            </span>
                        </div>
                    </td>

                    <TableActions
                        :edit-url="admin.users.edit(user.id).url"
                        :show-view="false"
                        item-name="usuario"
                        @delete="deleteUser(user)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex flex-col items-center justify-center gap-3 py-8">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                    >
                        <Users class="h-6 w-6" />
                    </div>

                    <div class="text-center">
                        <p class="font-medium text-[#244334]">
                            No se encontraron usuarios
                        </p>
                        <p class="mt-1 text-sm text-[#718277]">
                            {{
                                search.trim()
                                    ? 'Prueba con otro término de búsqueda o limpia los filtros.'
                                    : 'Todavía no hay usuarios registrados.'
                            }}
                        </p>
                    </div>
                </div>
            </template>
        </DataTable>
    </div>
</template>
