
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    CalendarClock,
    CircleMinus,
    CirclePlus,
    Coins,
    FileText,
    History,
    Link2,
    User,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';

interface MovementUser {
    id: number;
    name: string;
    email: string;
}

interface PointMovement {
    id: number;
    user_id: number;
    points: number;
    type: string;
    description: string | null;
    reference_type: string | null;
    reference_id: number | null;
    created_at: string;
    user: MovementUser | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedMovements {
    data: PointMovement[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

defineProps<{
    movements: PaginatedMovements;
}>();

const formatDate = (date: string): string => {
    return new Date(date).toLocaleString('es-MX', {
        dateStyle: 'short',
        timeStyle: 'short',
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
                title: 'Movimientos de puntos',
                href: admin.pointMovements.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Historial de puntos" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Historial de puntos"
            description="Consulta los puntos ganados y descontados por los usuarios."
            section="Administración"
            :icon="History"
        />

        <DataTable
            :pagination="movements"
            :is-empty="movements.data.length === 0"
            :empty-colspan="6"
            :show-search="false"
            :show-clear-filters="false"
        >
            <template #table-head>
                <tr>
                    <th class="whitespace-nowrap px-5 py-4 font-semibold sm:px-6">
                        Fecha
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Usuario
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Acción
                    </th>

                    <th class="whitespace-nowrap px-5 py-4 font-semibold sm:px-6">
                        Puntos
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Descripción
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">
                        Referencia
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="movement in movements.data"
                    :key="movement.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2 text-sm text-[#244334]">
                            <CalendarClock class="h-4 w-4 shrink-0 text-[#66816F]" />

                            {{ formatDate(movement.created_at) }}
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <User class="h-4 w-4" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ movement.user?.name ?? 'Usuario eliminado' }}
                                </p>

                                <p
                                    v-if="movement.user?.email"
                                    class="mt-0.5 text-xs text-[#718477]"
                                >
                                    {{ movement.user.email }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            <Coins class="h-3.5 w-3.5" />
                            {{ movement.type }}
                        </span>
                    </td>

                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="movement.points > 0"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700"
                        >
                            <CirclePlus class="h-3.5 w-3.5" />
                            +{{ movement.points }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
                        >
                            <CircleMinus class="h-3.5 w-3.5" />
                            {{ movement.points }}
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <div
                            v-if="movement.description"
                            class="flex max-w-md items-start gap-2 text-sm text-[#52685A]"
                        >
                            <FileText class="mt-0.5 h-4 w-4 shrink-0 text-[#809486]" />

                            <span class="break-words">
                                {{ movement.description }}
                            </span>
                        </div>

                        <span v-else class="text-sm italic text-[#91A096]">
                            —
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="movement.reference_type && movement.reference_id"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-white px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            <Link2 class="h-3.5 w-3.5 text-[#718477]" />

                            {{ movement.reference_type }}#{{ movement.reference_id }}
                        </span>

                        <span v-else class="text-sm text-[#91A096]">
                            —
                        </span>
                    </td>
                </tr>
            </template>

            <template #empty>
                <div class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Coins class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No hay movimientos de puntos
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        Los movimientos aparecerán aquí cuando los usuarios ganen
                        o gasten puntos.
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
