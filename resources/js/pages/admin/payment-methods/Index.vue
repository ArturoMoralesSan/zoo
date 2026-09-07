<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

import admin from '@/routes/admin';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    paymentMethods: PaymentMethod[];
}>();

const deletePaymentMethod = (paymentMethod: PaymentMethod) => {
    Swal.fire({
        title: '¿Eliminar método de pago?',
        text: `Se eliminará el método de pago "${paymentMethod.name}". Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(
                admin.paymentMethods.destroy(
                    paymentMethod.id,
                ).url,
                {
                    preserveScroll: true,
                },
            );
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
                        Métodos de pago
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Administra los métodos de pago disponibles para las
                        órdenes de boletos.
                    </p>
                </div>

                <Link
                    :href="admin.paymentMethods.create().url"
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nuevo método de pago
                </Link>
            </div>
        </div>

        <!-- Tabla -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                    >
                        <tr>
                            <th class="px-6 py-4 font-semibold">
                                Método de pago
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Código
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Descripción
                            </th>

                            <th class="px-6 py-4 text-center font-semibold">
                                Orden
                            </th>

                            <th class="px-6 py-4 font-semibold">
                                Estado
                            </th>

                            <th class="px-6 py-4 text-right font-semibold">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="paymentMethod in props.paymentMethods"
                            :key="paymentMethod.id"
                            class="transition hover:bg-muted/30"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium">
                                    {{ paymentMethod.name }}
                                </div>

                                <div class="text-xs text-muted-foreground">
                                    ID: {{ paymentMethod.id }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <code
                                    class="rounded bg-muted px-2 py-1 text-xs"
                                >
                                    {{ paymentMethod.code }}
                                </code>
                            </td>

                            <td class="px-6 py-4">
                                <div
                                    v-if="paymentMethod.description"
                                    class="max-w-md text-sm text-muted-foreground"
                                >
                                    {{ paymentMethod.description }}
                                </div>

                                <div
                                    v-else
                                    class="text-sm italic text-muted-foreground"
                                >
                                    Sin descripción
                                </div>
                            </td>

                            <td class="px-6 py-4 text-center">
                                <span
                                    class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                                >
                                    {{ paymentMethod.sort_order }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <span
                                    v-if="paymentMethod.is_active"
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

                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            admin.paymentMethods.edit(
                                                paymentMethod.id,
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
                                            deletePaymentMethod(
                                                paymentMethod,
                                            )
                                        "
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="props.paymentMethods.length === 0"
                        >
                            <td
                                colspan="6"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron métodos de pago.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>