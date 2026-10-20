
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    FilterX,
    Gift,
    Hash,
    Package,
    Plus,
    Star,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface Reward {
    id: number;
    name: string;
    description: string | null;
    points: number;
    stock: number;
    image: string | null;
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RewardsPagination {
    data: Reward[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    rewards: RewardsPagination;
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
        admin.rewards.index().url,
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
        admin.rewards.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteReward = async (reward: Reward): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar recompensa?',
        text: `Se eliminará la recompensa "${reward.name}". Esta acción no se puede deshacer.`,
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

    router.delete(admin.rewards.destroy(reward.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Eliminada',
                text: 'La recompensa se eliminó correctamente.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo eliminar la recompensa.',
                icon: 'error',
                confirmButtonText: 'Aceptar',
            });
        },
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
                title: 'Recompensas',
                href: admin.rewards.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Recompensas" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Recompensas"
            description="Administra las recompensas que los usuarios pueden canjear con sus puntos."
            section="Administración"
            :icon="Gift"
            :action-icon="Plus"
            action-label="Nueva recompensa"
            :action-href="admin.rewards.create().url"
        />

        <DataTable
            :pagination="rewards"
            :is-empty="rewards.data.length === 0"
            :empty-colspan="5"
            :search="search"
            search-placeholder="Buscar recompensa..."
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
                    <th class="min-w-[240px] px-5 py-4 font-semibold sm:px-6">
                        Recompensa
                    </th>

                    <th class="whitespace-nowrap px-5 py-4 font-semibold sm:px-6">
                        Puntos
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Stock
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Estado
                    </th>

                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="reward in rewards.data"
                    :key="reward.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E4F2E8] text-[#187744]"
                            >
                                <Gift class="h-5 w-5" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ reward.name }}
                                </p>

                                <p class="mt-0.5 flex items-center gap-1 text-xs text-[#718477]">
                                    <Hash class="h-3 w-3" />
                                    ID: {{ reward.id }}
                                </p>

                                <p
                                    v-if="reward.description"
                                    class="mt-1 max-w-md text-xs leading-relaxed text-[#718477]"
                                >
                                    {{ reward.description }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#EAF4EC] px-2.5 py-1 text-xs font-semibold text-[#187744]"
                        >
                            <Star class="h-3.5 w-3.5" />
                            {{ reward.points.toLocaleString('es-MX') }} pts
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#F0F4F1] text-[#66816F]"
                            >
                                <Package class="h-4 w-4" />
                            </span>

                            <div>
                                <p
                                    class="font-semibold"
                                    :class="
                                        reward.stock > 0
                                            ? 'text-green-700'
                                            : 'text-red-600'
                                    "
                                >
                                    {{ reward.stock.toLocaleString('es-MX') }}
                                </p>

                                <p class="text-xs text-[#718477]">
                                    {{ reward.stock > 0 ? 'Disponible' : 'Agotada' }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="reward.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#718477]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#91A096]" />
                            Inactiva
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.rewards.edit(reward.id).url"
                        item-name="recompensa"
                        @delete="deleteReward(reward)"
                    />
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Gift class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron recompensas
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay recompensas que coincidan con la búsqueda realizada.'
                                : 'Todavía no hay recompensas registradas. Puedes crear la primera desde el botón Nueva recompensa.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
