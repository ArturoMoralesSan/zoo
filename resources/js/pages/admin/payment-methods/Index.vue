
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CreditCard,
    Hash,
    Plus,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import DataTable from '@/components/admin/DataTable.vue';
import TableActions from '@/components/admin/TableActions.vue';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaymentMethodsPagination {
    data: PaymentMethod[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    paymentMethods: PaymentMethodsPagination;
    filters: {
        search?: string;
    };
}>();

const search = ref(props.filters?.search ?? '');

const hasFilters = () => Boolean(search.value.trim());

const submitSearch = (): void => {
    router.get(
        admin.paymentMethods.index().url,
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
        admin.paymentMethods.index().url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const deletePaymentMethod = async (
    paymentMethod: PaymentMethod,
): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar método de pago?',
        text: `Se eliminará el método de pago "${paymentMethod.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.paymentMethods.destroy(paymentMethod.id).url,
        {
            preserveScroll: true,
            onSuccess: () => {
                Swal.fire({
                    title: 'Eliminado',
                    text: 'El método de pago se eliminó correctamente.',
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                });
            },
            onError: () => {
                Swal.fire({
                    title: 'Error',
                    text: 'No se pudo eliminar el método de pago.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar',
                });
            },
        },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Métodos de pago',
                href: admin.paymentMethods.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Métodos de pago" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-6 lg:px-8"
    >
        <PageHeader
            title="Métodos de pago"
            description="Administra los métodos de pago disponibles para las órdenes de boletos."
            section="Administración"
            :icon="CreditCard"
            :action-icon="Plus"
            action-label="Nuevo método de pago"
            :action-href="admin.paymentMethods.create().url"
        />

        <DataTable
            v-model:search="search"
            :pagination="paymentMethods"
            :is-empty="paymentMethods.data.length === 0"
            :empty-colspan="6"
            search-placeholder="Buscar método de pago..."
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
                            'Método de pago',
                            'Código',
                            'Descripción',
                            'Orden',
                            'Estado',
                            'Acciones',
                        ]"
                        :key="heading"
                        class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6"
                        :class="[
                            heading === 'Orden' ? 'text-center' : 'text-left',
                            heading === 'Acciones' ? 'text-right' : '',
                        ]"
                    >
                        {{ heading }}
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="paymentMethod in paymentMethods.data"
                    :key="paymentMethod.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- Método de pago -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="flex min-w-[200px] items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#dce7df] bg-[#e4f2e8] text-[#187744]"
                            >
                                <CreditCard :size="18" />
                            </span>

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ paymentMethod.name }}
                                </p>

                                <p class="mt-1 text-xs text-[#84958a]">
                                    ID: {{ paymentMethod.id }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <!-- Código -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-2">
                            <Hash
                                :size="15"
                                class="text-[#718477]"
                            />

                            <code
                                class="rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-medium text-[#435d4c]"
                            >
                                {{ paymentMethod.code }}
                            </code>
                        </div>
                    </td>

                    <!-- Descripción -->
                    <td class="px-5 py-4 sm:px-6">
                        <p
                            v-if="paymentMethod.description"
                            class="max-w-md text-sm text-[#435d4c]"
                        >
                            {{ paymentMethod.description }}
                        </p>

                        <p
                            v-else
                            class="text-sm italic text-[#84958a]"
                        >
                            Sin descripción
                        </p>
                    </td>

                    <!-- Orden -->
                    <td class="whitespace-nowrap px-5 py-4 text-center sm:px-6">
                        <span
                            class="inline-flex min-w-8 justify-center rounded-full border border-[#dce7df] bg-[#f7faf8] px-2.5 py-1 text-xs font-semibold text-[#435d4c]"
                        >
                            {{ paymentMethod.sort_order }}
                        </span>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="paymentMethod.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#c9e4d2] bg-[#dcefe2] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#187744]" />
                            Activo
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#f0cccc] bg-[#fceaea] px-3 py-1.5 text-xs font-semibold text-[#b42323]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#c24141]" />
                            Inactivo
                        </span>
                    </td>

                    <!-- Acciones -->
                    <TableActions
                        :edit-url="admin.paymentMethods.edit(paymentMethod.id).url"
                        item-name="método de pago"
                        @delete="deletePaymentMethod(paymentMethod)"
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
                        <CreditCard :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay métodos de pago'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos métodos de pago que coincidan con tu búsqueda. Intenta con otro término.'
                                : 'Todavía no hay métodos de pago registrados.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
