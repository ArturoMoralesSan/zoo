<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarRange,
    Coins,
    FilterX,
    Plus,
    TriangleAlert,
    ToggleLeft,
    ToggleRight,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import DataTable from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import TableActions from '@/components/admin/TableActions.vue';
import admin from '@/routes/admin';

interface PointRule {
    id: number;
    type: string;
    name: string;
    points: number;
    description: string | null;
    is_active: boolean;
    // Dónde tiene efecto: acciones del código que la otorgan y rarezas que la usan.
    usage: {
        actions: string[];
        rarities: string[];
        in_use: boolean;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RulesPagination {
    data: PointRule[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    rules: RulesPagination;
    activeMode: { id: number; name: string } | null;
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
        admin.pointRules.index().url,
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
        admin.pointRules.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deleteRule = async (rule: PointRule): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar regla?',
        text: `Se eliminará la regla "${rule.name}". Esta acción no se puede deshacer.`,
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

    router.delete(admin.pointRules.destroy(rule.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Eliminada',
                text: 'La regla de puntos se eliminó correctamente.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
        },
        onError: () => {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo eliminar la regla de puntos.',
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
                title: 'Reglas de puntos',
                href: admin.pointRules.index,
            },
        ],
    },
});
</script>

<template>
    <Head title="Reglas de puntos" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            title="Reglas de puntos"
            description="Administra las reglas que generan o descuentan puntos."
            section="Administración"
            :icon="Coins"
            :action-icon="Plus"
            action-label="Nueva regla"
            :action-href="admin.pointRules.create().url"
        />

        <div
            class="flex flex-col gap-2 rounded-2xl border px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between"
            :class="
                activeMode
                    ? 'border-[#BFE3CB] bg-[#E2F3E8] text-[#244334]'
                    : 'border-[#DCE7DF] bg-white text-[#435D4C]'
            "
        >
            <p class="flex items-center gap-2">
                <CalendarRange
                    class="h-4 w-4 shrink-0 text-[#187744]"
                    aria-hidden="true"
                />
                <span v-if="activeMode">
                    Modo activo: <strong>{{ activeMode.name }}</strong
                    >. Los puntos de esta tabla son los base; el modo puede
                    cambiarlos.
                </span>
                <span v-else>
                    Sin modo activo: cada regla da los puntos de esta tabla.
                </span>
            </p>

            <Link
                :href="admin.pointModes.index().url"
                class="shrink-0 font-semibold text-[#187744] hover:underline"
            >
                Ver modos de puntos
            </Link>
        </div>

        <DataTable
            :pagination="rules"
            :is-empty="rules.data.length === 0"
            :empty-colspan="6"
            :search="search"
            search-placeholder="Buscar regla..."
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
                    <th class="px-5 py-4 font-semibold sm:px-6">Regla</th>

                    <th class="px-5 py-4 font-semibold sm:px-6">Tipo</th>

                    <th
                        class="px-5 py-4 font-semibold whitespace-nowrap sm:px-6"
                    >
                        Puntos
                    </th>

                    <th class="px-5 py-4 font-semibold sm:px-6">Uso</th>

                    <th class="px-5 py-4 font-semibold sm:px-6">Estado</th>

                    <th class="px-5 py-4 text-right font-semibold sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="rule in rules.data"
                    :key="rule.id"
                    class="transition-colors hover:bg-[#F5F9F6]"
                >
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex items-start gap-3">
                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E4F2E8] text-[#187744]"
                            >
                                <Coins class="h-4 w-4" />
                            </span>

                            <div class="min-w-0">
                                <p class="font-medium text-[#244334]">
                                    {{ rule.name }}
                                </p>

                                <p class="mt-0.5 text-xs text-[#718477]">
                                    ID: {{ rule.id }}
                                </p>

                                <p
                                    v-if="rule.description"
                                    class="mt-1 max-w-md text-xs leading-relaxed text-[#718477]"
                                >
                                    {{ rule.description }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <code
                            class="inline-flex rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#42604C]"
                        >
                            {{ rule.type }}
                        </code>
                    </td>

                    <td class="px-5 py-4 whitespace-nowrap sm:px-6">
                        <span
                            v-if="rule.points > 0"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700"
                        >
                            +{{ rule.points }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"
                        >
                            {{ rule.points }}
                        </span>
                    </td>

                    <td class="px-5 py-4 text-xs sm:px-6">
                        <div
                            v-if="rule.usage.in_use"
                            class="max-w-xs space-y-1 text-[#435D4C]"
                        >
                            <p v-if="rule.usage.actions.length > 0">
                                <span class="font-semibold text-[#244334]"
                                    >Lo otorga:</span
                                >
                                {{ rule.usage.actions.join(', ') }}
                            </p>

                            <p v-if="rule.usage.rarities.length > 0">
                                <span class="font-semibold text-[#244334]"
                                    >Asignada a:</span
                                >
                                {{ rule.usage.rarities.join(', ') }}
                            </p>
                        </div>

                        <span
                            v-else
                            class="inline-flex max-w-xs items-start gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 font-medium text-amber-800"
                            title='Asígnala a una rareza en "Configuración por rareza" o pide al equipo de desarrollo que la conecte a una acción.'
                        >
                            <TriangleAlert
                                class="mt-px h-3.5 w-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            Sin uso: ninguna acción otorga esta regla
                        </span>
                    </td>

                    <td class="px-5 py-4 sm:px-6">
                        <span
                            v-if="rule.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                        >
                            <ToggleRight class="h-3.5 w-3.5" />
                            Activa
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#DCE7DF] bg-[#F5F8F5] px-2.5 py-1 text-xs font-medium text-[#718477]"
                        >
                            <ToggleLeft class="h-3.5 w-3.5" />
                            Inactiva
                        </span>
                    </td>

                    <TableActions
                        :edit-url="admin.pointRules.edit(rule.id).url"
                        item-name="regla de puntos"
                        @delete="deleteRule(rule)"
                    />
                </tr>
            </template>

            <template #empty>
                <div
                    class="flex min-h-[260px] flex-col items-center justify-center p-8 text-center"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#EAF2EC] text-[#66816F]"
                    >
                        <Coins class="h-6 w-6" />
                    </div>

                    <h3 class="mt-4 text-sm font-semibold text-[#244334]">
                        No se encontraron reglas de puntos
                    </h3>

                    <p class="mt-1 max-w-md text-sm text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No hay reglas que coincidan con la búsqueda.'
                                : 'Crea una regla para definir cómo se generan o descuentan los puntos.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
