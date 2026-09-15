<script setup lang="ts">

import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';

import { Html5Qrcode } from 'html5-qrcode';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
} from 'vue';

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

interface UserLevel {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    points: number;
    qr_token: string;
    level: UserLevel | null;
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
| Usuario / QR
|--------------------------------------------------------------------------
*/

const identifiedUser = ref<User | null>(null);

const qrScanner = ref<Html5Qrcode | null>(null);

const scanning = ref(false);

const qrError = ref<string | null>(null);

const searchingUser = ref(false);

const manualQrToken = ref('');

/*
|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------
*/

const formatCurrency = (
    value: number | string,
): string => {
    return Number(value).toLocaleString(
        'es-MX',
        {
            style: 'currency',
            currency: 'MXN',
        },
    );
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
        (ticketType) =>
            ticketType.id === ticketTypeId,
    );
};

const getItem = (
    ticketTypeId: number,
): OrderItem | undefined => {
    return form.items.find(
        (item) =>
            item.ticket_type_id === ticketTypeId,
    );
};

const getQuantity = (
    ticketTypeId: number,
): number => {
    return (
        getItem(ticketTypeId)?.quantity ??
        0
    );
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
            orderItem.ticket_type_id !==
            ticketTypeId,
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
            const ticketType =
                getTicketType(
                    item.ticket_type_id,
                );

            if (!ticketType) {
                return total;
            }

            return (
                total +
                Number(
                    ticketType.price,
                ) * item.quantity
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
        getPaymentMethod(
            paymentMethodId,
        );

    return method?.code === 'cash';
};

const requiresReference = (
    paymentMethodId: number | null,
): boolean => {
    const method =
        getPaymentMethod(
            paymentMethodId,
        );

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
| Usuario / QR
|--------------------------------------------------------------------------
*/

const findUserByQr = async (
    token: string,
): Promise<void> => {
    const cleanToken = token.trim();

    if (!cleanToken) {
        return;
    }

    searchingUser.value = true;

    qrError.value = null;

    try {
        const response = await fetch(
            '/admin/ticket-orders/user-by-qr',
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    Accept:
                        'application/json',

                    'X-CSRF-TOKEN':
                        document
                            .querySelector(
                                'meta[name="csrf-token"]',
                            )
                            ?.getAttribute(
                                'content',
                            ) ?? '',
                },

                body: JSON.stringify({
                    qr_token: cleanToken,
                }),
            },
        );

        const data =
            await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ??
                    'No se pudo identificar al usuario.',
            );
        }

        identifiedUser.value =
            data.user;

        form.user_id =
            data.user.id;

        manualQrToken.value = '';

        await stopQrScanner();
    } catch (error) {
        identifiedUser.value =
            null;

        form.user_id = null;

        qrError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo identificar al usuario.';
    } finally {
        searchingUser.value = false;
    }
};

/*
|--------------------------------------------------------------------------
| Iniciar lector QR
|--------------------------------------------------------------------------
*/

const startQrScanner = async (): Promise<void> => {
    if (scanning.value) {
        return;
    }

    qrError.value = null;

    try {
        /*
        |--------------------------------------------------------------------------
        | Activamos el lector
        |--------------------------------------------------------------------------
        */

        scanning.value = true;

        /*
        |--------------------------------------------------------------------------
        | Esperamos a que Vue renderice el contenedor
        |--------------------------------------------------------------------------
        */

        await nextTick();

        /*
        |--------------------------------------------------------------------------
        | Verificamos el elemento
        |--------------------------------------------------------------------------
        */

        const readerElement =
            document.getElementById(
                'user-qr-reader',
            );

        if (!readerElement) {
            throw new Error(
                'No se encontró el elemento #user-qr-reader.',
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Crear lector
        |--------------------------------------------------------------------------
        */

        qrScanner.value =
            new Html5Qrcode(
                'user-qr-reader',
            );

        /*
        |--------------------------------------------------------------------------
        | Iniciar cámara
        |--------------------------------------------------------------------------
        */

        await qrScanner.value.start(
            {
                facingMode:
                    'environment',
            },

            {
                fps: 10,

                qrbox: {
                    width: 230,
                    height: 230,
                },

                aspectRatio: 1,
            },

            async (decodedText) => {
                console.log(
                    'QR detectado:',
                    decodedText,
                );

                await findUserByQr(
                    decodedText,
                );
            },

            () => {
                /*
                |--------------------------------------------------------------------------
                | Este callback se ejecuta continuamente mientras
                | la cámara busca un QR.
                |--------------------------------------------------------------------------
                */
            },
        );
    } catch (error) {
        console.error(
            'Error iniciando lector QR:',
            error,
        );

        console.error(
            'Tipo:',
            typeof error,
        );

        console.error(
            'Nombre:',
            (error as any)?.name,
        );

        console.error(
            'Mensaje:',
            (error as any)?.message,
        );

        scanning.value = false;

        qrScanner.value = null;

        if (error instanceof Error) {
            qrError.value =
                `Error de cámara: ${error.name} - ${error.message}`;
        } else if (
            typeof error === 'string'
        ) {
            qrError.value =
                `Error de cámara: ${error}`;
        } else {
            try {
                qrError.value =
                    `Error de cámara: ${JSON.stringify(error)}`;
            } catch {
                qrError.value =
                    'No fue posible iniciar el lector QR.';
            }
        }
    }
};

/*
|--------------------------------------------------------------------------
| Detener lector QR
|--------------------------------------------------------------------------
*/

const stopQrScanner =
    async (): Promise<void> => {
        if (!qrScanner.value) {
            scanning.value = false;

            return;
        }

        try {
            if (scanning.value) {
                await qrScanner.value.stop();
            }

            await qrScanner.value.clear();
        } catch (error) {
            console.error(
                'Error deteniendo lector QR:',
                error,
            );
        }

        qrScanner.value = null;

        scanning.value = false;
    };

/*
|--------------------------------------------------------------------------
| Quitar usuario
|--------------------------------------------------------------------------
*/

const clearIdentifiedUser =
    async (): Promise<void> => {
        await stopQrScanner();

        identifiedUser.value = null;

        form.user_id = null;

        qrError.value = null;

        manualQrToken.value = '';
    };

/*
|--------------------------------------------------------------------------
| Buscar QR manualmente
|--------------------------------------------------------------------------
*/

const searchManualQr =
    async (): Promise<void> => {
        await findUserByQr(
            manualQrToken.value,
        );
    };

/*
|--------------------------------------------------------------------------
| Limpiar cámara al salir
|--------------------------------------------------------------------------
*/

onBeforeUnmount(async () => {
    await stopQrScanner();
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
                    <h1
                        class="text-2xl font-semibold"
                    >
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
                        admin.ticketOrders
                            .index().url
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

                <!-- Vendedor -->
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

                <!-- Cliente / visitante -->
                <div
                    class="mt-4 rounded-lg border border-sidebar-border p-4"
                >
                    <div
                        class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                    >
                        <div>
                            <p
                                class="text-sm font-medium"
                            >
                                Cliente / visitante
                            </p>

                            <p
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Escanea el código QR
                                personal del visitante
                                para asociar la venta a
                                su cuenta.
                            </p>
                        </div>

                        <button
                            v-if="!scanning"
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                            @click="
                                startQrScanner
                            "
                        >
                            Escanear QR
                        </button>

                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                            @click="
                                stopQrScanner
                            "
                        >
                            Detener cámara
                        </button>
                    </div>

                    <!--
                    |--------------------------------------------------------------------------
                    | Lector QR
                    |--------------------------------------------------------------------------
                    |
                    | La cámara ocupa un área cuadrada.
                    | El overlay oscurece el exterior y deja
                    | únicamente visible la zona central
                    | donde debe colocarse el QR.
                    |
                    -->
                    <div
                        v-if="scanning"
                        class="mt-4 flex justify-center"
                    >
                        <div
                            class="relative h-[320px] w-[320px] overflow-hidden rounded-2xl border border-sidebar-border bg-black shadow-lg"
                        >
                            <!-- Cámara -->
                            <div
                                id="user-qr-reader"
                                class="absolute inset-0"
                            ></div>

                            <!--
                            |--------------------------------------------------------------------------
                            | Overlay oscuro
                            |--------------------------------------------------------------------------
                            -->
                            <div
                                class="pointer-events-none absolute inset-0 z-20 grid grid-cols-[1fr_230px_1fr] grid-rows-[1fr_230px_1fr]"
                            >
                                <!-- Arriba -->
                                <div
                                    class="col-span-3 bg-black/55"
                                ></div>

                                <!-- Izquierda -->
                                <div
                                    class="bg-black/55"
                                ></div>

                                <!-- Centro transparente -->
                                <div
                                    class="relative"
                                >
                                    <!-- Esquinas -->
                                    <span
                                        class="absolute left-0 top-0 h-9 w-9 rounded-tl-xl border-l-4 border-t-4 border-primary"
                                    ></span>

                                    <span
                                        class="absolute right-0 top-0 h-9 w-9 rounded-tr-xl border-r-4 border-t-4 border-primary"
                                    ></span>

                                    <span
                                        class="absolute bottom-0 left-0 h-9 w-9 rounded-bl-xl border-b-4 border-l-4 border-primary"
                                    ></span>

                                    <span
                                        class="absolute bottom-0 right-0 h-9 w-9 rounded-br-xl border-b-4 border-r-4 border-primary"
                                    ></span>

                                    <!-- Línea de escaneo -->
                                    <span
                                        class="absolute left-3 right-3 top-1/2 h-0.5 -translate-y-1/2 bg-primary opacity-90 shadow-[0_0_8px_currentColor] animate-pulse"
                                    ></span>
                                </div>

                                <!-- Derecha -->
                                <div
                                    class="bg-black/55"
                                ></div>

                                <!-- Abajo -->
                                <div
                                    class="col-span-3 bg-black/55"
                                ></div>
                            </div>

                            <!-- Texto -->
                            <div
                                class="pointer-events-none absolute bottom-3 left-0 right-0 z-30 text-center"
                            >
                                <span
                                    class="rounded-full bg-black/60 px-3 py-1 text-xs text-white backdrop-blur-sm"
                                >
                                    Coloca el QR dentro
                                    del recuadro
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Búsqueda manual -->
                    <div
                        v-if="!identifiedUser"
                        class="mt-4"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row"
                        >
                            <input
                                v-model="
                                    manualQrToken
                                "
                                type="text"
                                placeholder="También puedes ingresar el código QR manualmente"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                @keyup.enter="
                                    searchManualQr
                                "
                            />

                            <button
                                type="button"
                                :disabled="
                                    searchingUser ||
                                    !manualQrToken.trim()
                                "
                                class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                                @click="
                                    searchManualQr
                                "
                            >
                                {{
                                    searchingUser
                                        ? 'Buscando...'
                                        : 'Buscar'
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- Usuario identificado -->
                    <div
                        v-if="identifiedUser"
                        class="mt-4 rounded-lg border border-green-500/30 bg-green-500/10 p-4"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium text-green-700 dark:text-green-400"
                                >
                                    Usuario identificado
                                </p>

                                <p
                                    class="mt-1 text-lg font-semibold"
                                >
                                    {{
                                        identifiedUser.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    {{
                                        identifiedUser.email
                                    }}
                                </p>

                                <div
                                    class="mt-2 flex flex-wrap gap-2"
                                >
                                    <span
                                        class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                    >
                                        {{
                                            identifiedUser
                                                .level
                                                ?.name ??
                                            'Sin nivel'
                                        }}
                                    </span>

                                    <span
                                        class="rounded-full border border-sidebar-border px-3 py-1 text-xs"
                                    >
                                        {{
                                            identifiedUser.points.toLocaleString(
                                                'es-MX',
                                            )
                                        }}
                                        puntos
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-red-500/30 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                                @click="
                                    clearIdentifiedUser
                                "
                            >
                                Quitar usuario
                            </button>
                        </div>
                    </div>

                    <!-- Error -->
                    <div
                        v-if="qrError"
                        class="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 p-4"
                    >
                        <p
                            class="text-sm font-medium text-red-600 dark:text-red-400"
                        >
                            {{ qrError }}
                        </p>
                    </div>
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