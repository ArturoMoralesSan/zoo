<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface TicketType {
    id: number;
    name: string;
    price: string | number;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface OrderItem {
    ticket_type_id: number;
    quantity: number;
}

interface OrderPayment {
    payment_method_id: number | null;
    amount: number;
    reference: string;
}

const props = defineProps<{
    ticketTypes: TicketType[];
    paymentMethods: PaymentMethod[];
}>();

const form = useForm({
    user_id: null as number | null,

    items: [] as OrderItem[],

    discount: 0,

    payments: [] as OrderPayment[],
});

/*
|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------
*/

const formatCurrency = (value: number | string): string => {
    return Number(value).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
};

/*
|--------------------------------------------------------------------------
| Boletos
|--------------------------------------------------------------------------
*/

const getTicketType = (
    ticketTypeId: number,
): TicketType | undefined => {
    return props.ticketTypes.find(
        (ticketType) => ticketType.id === ticketTypeId,
    );
};

const getItem = (
    ticketTypeId: number,
): OrderItem | undefined => {
    return form.items.find(
        (item) => item.ticket_type_id === ticketTypeId,
    );
};

const getQuantity = (
    ticketTypeId: number,
): number => {
    return getItem(ticketTypeId)?.quantity ?? 0;
};

const addTicket = (
    ticketTypeId: number,
): void => {
    const item = getItem(ticketTypeId);

    if (item) {
        item.quantity++;
        return;
    }

    form.items.push({
        ticket_type_id: ticketTypeId,
        quantity: 1,
    });
};

const removeTicket = (
    ticketTypeId: number,
): void => {
    const item = getItem(ticketTypeId);

    if (!item) {
        return;
    }

    if (item.quantity > 1) {
        item.quantity--;
        return;
    }

    form.items = form.items.filter(
        (orderItem) =>
            orderItem.ticket_type_id !== ticketTypeId,
    );
};

/*
|--------------------------------------------------------------------------
| Totales
|--------------------------------------------------------------------------
*/

const subtotal = computed(() => {
    return form.items.reduce(
        (total, item) => {
            const ticketType = getTicketType(
                item.ticket_type_id,
            );

            if (!ticketType) {
                return total;
            }

            return (
                total +
                Number(ticketType.price) *
                    item.quantity
            );
        },
        0,
    );
});

const discount = computed(() => {
    const value =
        Number(form.discount) || 0;

    return Math.min(
        Math.max(value, 0),
        subtotal.value,
    );
});

const total = computed(() => {
    return Math.max(
        subtotal.value -
            discount.value,
        0,
    );
});

/*
|--------------------------------------------------------------------------
| Métodos de pago
|--------------------------------------------------------------------------
*/

const getPaymentMethod = (
    paymentMethodId: number | null,
): PaymentMethod | undefined => {
    return props.paymentMethods.find(
        (paymentMethod) =>
            paymentMethod.id ===
            paymentMethodId,
    );
};

const isCashPayment = (
    paymentMethodId: number | null,
): boolean => {
    const method =
        getPaymentMethod(paymentMethodId);

    return method?.code === 'cash';
};

const requiresReference = (
    paymentMethodId: number | null,
): boolean => {
    const method =
        getPaymentMethod(paymentMethodId);

    return (
        method?.code === 'transfer' ||
        method?.code === 'mercadopago'
    );
};

const addPayment = (): void => {
    form.payments.push({
        payment_method_id:
            props.paymentMethods[0]?.id ??
            null,

        amount: 0,

        reference: '',
    });
};

const removePayment = (
    index: number,
): void => {
    form.payments.splice(index, 1);
};

/*
|--------------------------------------------------------------------------
| Totales de pagos
|--------------------------------------------------------------------------
*/

const nonCashPaymentsTotal =
    computed(() => {
        return form.payments.reduce(
            (total, payment) => {
                if (
                    isCashPayment(
                        payment.payment_method_id,
                    )
                ) {
                    return total;
                }

                return (
                    total +
                    (Number(
                        payment.amount,
                    ) || 0)
                );
            },
            0,
        );
    });

const cashPaymentsTotal =
    computed(() => {
        return form.payments.reduce(
            (total, payment) => {
                if (
                    !isCashPayment(
                        payment.payment_method_id,
                    )
                ) {
                    return total;
                }

                return (
                    total +
                    (Number(
                        payment.amount,
                    ) || 0)
                );
            },
            0,
        );
    });

/*
|--------------------------------------------------------------------------
| Saldo antes del efectivo
|--------------------------------------------------------------------------
*/

const remainingBeforeCash =
    computed(() => {
        return Math.max(
            total.value -
                nonCashPaymentsTotal.value,
            0,
        );
    });

/*
|--------------------------------------------------------------------------
| Falta por pagar
|--------------------------------------------------------------------------
*/

const remaining = computed(() => {
    return Math.max(
        remainingBeforeCash.value -
            cashPaymentsTotal.value,
        0,
    );
});

/*
|--------------------------------------------------------------------------
| Cambio
|--------------------------------------------------------------------------
*/

const change = computed(() => {
    return Math.max(
        cashPaymentsTotal.value -
            remainingBeforeCash.value,
        0,
    );
});

/*
|--------------------------------------------------------------------------
| Estado del pago
|--------------------------------------------------------------------------
*/

const isPaymentComplete =
    computed(() => {
        return (
            total.value > 0 &&
            remaining.value < 0.01
        );
    });

const hasPaymentError =
    computed(() => {
        return (
            form.payments.length > 0 &&
            nonCashPaymentsTotal.value >
                total.value
        );
    });

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = (): void => {
    form.post(
        admin.ticketOrders.store().url,
    );
};
</script>

<template>
    <Head title="Nueva orden de boletos" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Header -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Nueva orden de boletos
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Registra una venta de boletos
                        realizada en taquilla.
                    </p>
                </div>

                <Link
                    :href="
                        admin.ticketOrders.index()
                            .url
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <form
            class="space-y-4"
            @submit.prevent="submit"
        >
            <!-- Información de la venta -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Información de la venta
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Esta orden se registrará
                            como una venta de taquilla.
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit rounded-full border border-purple-500/30 bg-purple-500/10 px-3 py-1 text-xs font-medium text-purple-600 dark:text-purple-400"
                    >
                        Taquilla
                    </span>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p
                        class="text-sm font-medium"
                    >
                        Vendedor
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Se asignará automáticamente
                        al usuario que está registrando
                        la venta.
                    </p>
                </div>
            </div>

            <!-- Boletos -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Boletos
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Selecciona los tipos y
                        cantidades de boletos.
                    </p>
                </div>

                <div
                    v-if="ticketTypes.length > 0"
                    class="space-y-3"
                >
                    <div
                        v-for="ticketType in ticketTypes"
                        :key="ticketType.id"
                        class="flex flex-col gap-4 rounded-lg border border-sidebar-border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="font-medium"
                            >
                                {{ ticketType.name }}
                            </p>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{
                                    formatCurrency(
                                        ticketType.price,
                                    )
                                }}
                                por boleto
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-3"
                        >
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-sidebar-border text-lg transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="
                                    getQuantity(
                                        ticketType.id,
                                    ) === 0
                                "
                                @click="
                                    removeTicket(
                                        ticketType.id,
                                    )
                                "
                            >
                                −
                            </button>

                            <span
                                class="w-8 text-center text-sm font-medium"
                            >
                                {{
                                    getQuantity(
                                        ticketType.id,
                                    )
                                }}
                            </span>

                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-sidebar-border text-lg transition hover:bg-accent"
                                @click="
                                    addTicket(
                                        ticketType.id,
                                    )
                                "
                            >
                                +
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                >
                    <p
                        class="text-sm text-muted-foreground"
                    >
                        No hay tipos de boleto
                        activos.
                    </p>
                </div>

                <p
                    v-if="form.errors.items"
                    class="mt-3 text-sm text-red-500"
                >
                    {{ form.errors.items }}
                </p>
            </div>

            <!-- Resumen y descuento -->
            <div
                class="grid gap-4 lg:grid-cols-2"
            >
                <!-- Descuento -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <div class="mb-6">
                        <h2
                            class="text-lg font-semibold"
                        >
                            Descuento
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Aplica un descuento a la
                            orden si corresponde.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label
                            for="discount"
                            class="text-sm font-medium"
                        >
                            Descuento
                        </label>

                        <div
                            class="relative"
                        >
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                            >
                                $
                            </span>

                            <input
                                id="discount"
                                v-model.number="
                                    form.discount
                                "
                                type="number"
                                min="0"
                                :max="subtotal"
                                step="0.01"
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-8 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <p
                            v-if="
                                form.errors.discount
                            "
                            class="text-sm text-red-500"
                        >
                            {{
                                form.errors
                                    .discount
                            }}
                        </p>
                    </div>
                </div>

                <!-- Resumen -->
                <div
                    class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
                >
                    <div class="mb-6">
                        <h2
                            class="text-lg font-semibold"
                        >
                            Resumen
                        </h2>
                    </div>

                    <div
                        class="space-y-3"
                    >
                        <div
                            class="flex justify-between text-sm"
                        >
                            <span
                                class="text-muted-foreground"
                            >
                                Subtotal
                            </span>

                            <span
                                class="font-medium"
                            >
                                {{
                                    formatCurrency(
                                        subtotal,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between text-sm"
                        >
                            <span
                                class="text-muted-foreground"
                            >
                                Descuento
                            </span>

                            <span
                                class="font-medium"
                            >
                                -
                                {{
                                    formatCurrency(
                                        discount,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between border-t border-sidebar-border pt-3"
                        >
                            <span
                                class="font-semibold"
                            >
                                Total
                            </span>

                            <span
                                class="text-xl font-semibold"
                            >
                                {{
                                    formatCurrency(
                                        total,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métodos de pago -->
            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div
                    class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-lg font-semibold"
                        >
                            Métodos de pago
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Puedes utilizar uno o
                            varios métodos de pago.
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="
                            paymentMethods.length ===
                            0
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                        @click="addPayment"
                    >
                        Agregar pago
                    </button>
                </div>

                <div
                    v-if="
                        paymentMethods.length ===
                        0
                    "
                    class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                >
                    <p
                        class="text-sm text-muted-foreground"
                    >
                        No hay métodos de pago
                        activos.
                    </p>
                </div>

                <div
                    v-else-if="
                        form.payments.length ===
                        0
                    "
                    class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                >
                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Agrega al menos un método
                        de pago.
                    </p>

                    <button
                        type="button"
                        class="mt-3 text-sm font-medium text-primary hover:underline"
                        @click="addPayment"
                    >
                        Agregar método de pago
                    </button>
                </div>

                <div
                    v-else
                    class="space-y-4"
                >
                    <div
                        v-for="(
                            payment, index
                        ) in form.payments"
                        :key="index"
                        class="rounded-lg border border-sidebar-border p-4"
                    >
                        <!-- Campos de pago -->
                        <div
                            class="grid gap-4 md:grid-cols-12 md:items-end"
                        >
                            <!-- Método -->
                            <div
                                class="space-y-2 md:col-span-4"
                            >
                                <label
                                    :for="`payment-method-${index}`"
                                    class="text-sm font-medium"
                                >
                                    Método de pago
                                </label>

                                <select
                                    :id="`payment-method-${index}`"
                                    v-model="
                                        payment.payment_method_id
                                    "
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                >
                                    <option
                                        :value="
                                            null
                                        "
                                    >
                                        Selecciona un
                                        método
                                    </option>

                                    <option
                                        v-for="method in paymentMethods"
                                        :key="
                                            method.id
                                        "
                                        :value="
                                            method.id
                                        "
                                    >
                                        {{
                                            method.name
                                        }}
                                    </option>
                                </select>
                            </div>

                            <!-- Monto -->
                            <div
                                class="space-y-2 md:col-span-3"
                            >
                                <label
                                    :for="`payment-amount-${index}`"
                                    class="text-sm font-medium"
                                >
                                    {{
                                        isCashPayment(
                                            payment.payment_method_id,
                                        )
                                            ? 'Efectivo recibido'
                                            : 'Monto'
                                    }}
                                </label>

                                <div
                                    class="relative"
                                >
                                    <span
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                                    >
                                        $
                                    </span>

                                    <input
                                        :id="`payment-amount-${index}`"
                                        v-model.number="
                                            payment.amount
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-8 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    />
                                </div>
                            </div>

                            <!-- Referencia -->
                            <div
                                class="space-y-2 md:col-span-4"
                            >
                                <label
                                    :for="`payment-reference-${index}`"
                                    class="text-sm font-medium"
                                >
                                    Referencia

                                    <span
                                        v-if="
                                            requiresReference(
                                                payment.payment_method_id,
                                            )
                                        "
                                        class="text-muted-foreground"
                                    >
                                        *
                                    </span>

                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                    >
                                        (opcional)
                                    </span>
                                </label>

                                <input
                                    :id="`payment-reference-${index}`"
                                    v-model="
                                        payment.reference
                                    "
                                    type="text"
                                    :placeholder="
                                        requiresReference(
                                            payment.payment_method_id,
                                        )
                                            ? 'Ingresa la referencia'
                                            : 'Opcional'
                                    "
                                    class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                />
                            </div>

                            <!-- Quitar -->
                            <div
                                class="flex justify-end md:col-span-1"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg border border-red-500/30 px-3 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                                    @click="
                                        removePayment(
                                            index,
                                        )
                                    "
                                >
                                    Quitar
                                </button>
                            </div>
                        </div>

                        <!-- Aviso de efectivo -->
                        <div
                            v-if="
                                isCashPayment(
                                    payment.payment_method_id,
                                )
                            "
                            class="mt-3"
                        >
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Puede ser mayor al
                                saldo. El excedente
                                se devuelve como
                                cambio.
                            </p>
                        </div>

                        <!-- Errores -->
                        <p
                            v-if="
                                form.errors[
                                    `payments.${index}.payment_method_id`
                                ]
                            "
                            class="mt-2 text-sm text-red-500"
                        >
                            {{
                                form.errors[
                                    `payments.${index}.payment_method_id`
                                ]
                            }}
                        </p>

                        <p
                            v-if="
                                form.errors[
                                    `payments.${index}.amount`
                                ]
                            "
                            class="mt-2 text-sm text-red-500"
                        >
                            {{
                                form.errors[
                                    `payments.${index}.amount`
                                ]
                            }}
                        </p>

                        <p
                            v-if="
                                form.errors[
                                    `payments.${index}.reference`
                                ]
                            "
                            class="mt-2 text-sm text-red-500"
                        >
                            {{
                                form.errors[
                                    `payments.${index}.reference`
                                ]
                            }}
                        </p>
                    </div>
                </div>

                <!-- Resumen de pagos -->
                <div
                    class="mt-6 rounded-lg border border-sidebar-border p-4"
                >
                    <div
                        class="space-y-3"
                    >
                        <div
                            class="flex justify-between text-sm"
                        >
                            <span
                                class="text-muted-foreground"
                            >
                                Total de la orden
                            </span>

                            <span
                                class="font-medium"
                            >
                                {{
                                    formatCurrency(
                                        total,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between text-sm"
                        >
                            <span
                                class="text-muted-foreground"
                            >
                                Pagos electrónicos /
                                otros
                            </span>

                            <span
                                class="font-medium"
                            >
                                {{
                                    formatCurrency(
                                        nonCashPaymentsTotal,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            class="flex justify-between text-sm"
                        >
                            <span
                                class="text-muted-foreground"
                            >
                                Efectivo recibido
                            </span>

                            <span
                                class="font-medium"
                            >
                                {{
                                    formatCurrency(
                                        cashPaymentsTotal,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-if="hasPaymentError"
                            class="rounded-lg border border-red-500/30 bg-red-500/10 p-3"
                        >
                            <p
                                class="text-sm font-medium text-red-600 dark:text-red-400"
                            >
                                Los pagos que no son en
                                efectivo no pueden
                                superar el total de la
                                orden.
                            </p>
                        </div>

                        <div
                            v-if="
                                remaining > 0
                            "
                            class="flex justify-between border-t border-sidebar-border pt-3 text-sm"
                        >
                            <span
                                class="font-medium"
                            >
                                Falta pagar
                            </span>

                            <span
                                class="font-semibold text-red-600 dark:text-red-400"
                            >
                                {{
                                    formatCurrency(
                                        remaining,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-else-if="
                                change > 0
                            "
                            class="flex justify-between border-t border-sidebar-border pt-3 text-sm"
                        >
                            <span
                                class="font-medium"
                            >
                                Cambio
                            </span>

                            <span
                                class="font-semibold text-green-600 dark:text-green-400"
                            >
                                {{
                                    formatCurrency(
                                        change,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-else-if="
                                isPaymentComplete
                            "
                            class="flex justify-between border-t border-sidebar-border pt-3 text-sm"
                        >
                            <span
                                class="font-medium"
                            >
                                Estado
                            </span>

                            <span
                                class="font-medium text-green-600 dark:text-green-400"
                            >
                                Pago completo
                            </span>
                        </div>
                    </div>
                </div>

                <p
                    v-if="form.errors.payments"
                    class="mt-3 text-sm text-red-500"
                >
                    {{ form.errors.payments }}
                </p>

                <!-- Acciones -->
                <div
                    class="mt-6 flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.ticketOrders
                                .index().url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="
                            form.processing ||
                            form.items.length ===
                                0 ||
                            form.payments.length ===
                                0 ||
                            !isPaymentComplete ||
                            hasPaymentError
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Procesando...'
                                : 'Crear orden'
                        }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>