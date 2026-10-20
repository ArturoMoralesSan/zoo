<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Html5Qrcode } from 'html5-qrcode';
import {
    ArrowLeft,
    Banknote,
    Camera,
    CheckCircle2,
    CreditCard,
    Info,
    Minus,
    Plus,
    QrCode,
    Receipt,
    Save,
    Search,
    Store,
    Ticket,
    Trash2,
    UserRound,
    WalletCards,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    ref,
} from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

/* Usuario / QR */
const identifiedUser = ref<User | null>(null);
const qrScanner = ref<Html5Qrcode | null>(null);
const scanning = ref(false);
const qrError = ref<string | null>(null);
const searchingUser = ref(false);
const manualQrToken = ref('');

/* Formato */
const formatCurrency = (value: number | string): string => {
    return Number(value).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
};

/* Boletos */
const getTicketType = (ticketTypeId: number): TicketType | undefined => {
    return props.ticketTypes.find(
        (ticketType) => ticketType.id === ticketTypeId,
    );
};

const getItem = (ticketTypeId: number): OrderItem | undefined => {
    return form.items.find(
        (item) => item.ticket_type_id === ticketTypeId,
    );
};

const getQuantity = (ticketTypeId: number): number => {
    return getItem(ticketTypeId)?.quantity ?? 0;
};

const addTicket = (ticketTypeId: number): void => {
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

const removeTicket = (ticketTypeId: number): void => {
    const item = getItem(ticketTypeId);

    if (!item) return;

    if (item.quantity > 1) {
        item.quantity--;
        return;
    }

    form.items = form.items.filter(
        (orderItem) => orderItem.ticket_type_id !== ticketTypeId,
    );
};

/* Totales */
const subtotal = computed(() => {
    return form.items.reduce((sum, item) => {
        const ticketType = getTicketType(item.ticket_type_id);

        if (!ticketType) return sum;

        return sum + Number(ticketType.price) * item.quantity;
    }, 0);
});

const discount = computed(() => {
    const value = Number(form.discount) || 0;

    return Math.min(Math.max(value, 0), subtotal.value);
});

const total = computed(() => {
    return Math.max(subtotal.value - discount.value, 0);
});

/* Métodos de pago */
const getPaymentMethod = (
    paymentMethodId: number | null,
): PaymentMethod | undefined => {
    return props.paymentMethods.find(
        (paymentMethod) => paymentMethod.id === paymentMethodId,
    );
};

const isCashPayment = (paymentMethodId: number | null): boolean => {
    return getPaymentMethod(paymentMethodId)?.code === 'cash';
};

const requiresReference = (paymentMethodId: number | null): boolean => {
    const method = getPaymentMethod(paymentMethodId);

    return method?.code === 'transfer' || method?.code === 'mercadopago';
};

const addPayment = (): void => {
    form.payments.push({
        payment_method_id: props.paymentMethods[0]?.id ?? null,
        amount: 0,
        reference: '',
    });
};

const removePayment = (index: number): void => {
    form.payments.splice(index, 1);
};

/* Totales de pagos */
const nonCashPaymentsTotal = computed(() => {
    return form.payments.reduce((sum, payment) => {
        if (isCashPayment(payment.payment_method_id)) return sum;

        return sum + (Number(payment.amount) || 0);
    }, 0);
});

const cashPaymentsTotal = computed(() => {
    return form.payments.reduce((sum, payment) => {
        if (!isCashPayment(payment.payment_method_id)) return sum;

        return sum + (Number(payment.amount) || 0);
    }, 0);
});

const remainingBeforeCash = computed(() => {
    return Math.max(total.value - nonCashPaymentsTotal.value, 0);
});

const remaining = computed(() => {
    return Math.max(
        remainingBeforeCash.value - cashPaymentsTotal.value,
        0,
    );
});

const change = computed(() => {
    return Math.max(
        cashPaymentsTotal.value - remainingBeforeCash.value,
        0,
    );
});

const isPaymentComplete = computed(() => {
    return total.value > 0 && remaining.value < 0.01;
});

const hasPaymentError = computed(() => {
    return (
        form.payments.length > 0 &&
        nonCashPaymentsTotal.value > total.value
    );
});

/* Buscar usuario por QR */
const findUserByQr = async (token: string): Promise<void> => {
    const cleanToken = token.trim();

    if (!cleanToken) return;

    searchingUser.value = true;
    qrError.value = null;

    try {
        const response = await fetch('/admin/ticket-orders/user-by-qr', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                qr_token: cleanToken,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message ?? 'No se pudo identificar al usuario.',
            );
        }

        identifiedUser.value = data.user;
        form.user_id = data.user.id;
        manualQrToken.value = '';

        await stopQrScanner();
    } catch (error) {
        identifiedUser.value = null;
        form.user_id = null;

        qrError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo identificar al usuario.';
    } finally {
        searchingUser.value = false;
    }
};

/* Iniciar lector QR */
const startQrScanner = async (): Promise<void> => {
    if (scanning.value) return;

    qrError.value = null;
    scanning.value = true;

    try {
        await nextTick();

        const readerElement = document.getElementById('user-qr-reader');

        if (!readerElement) {
            throw new Error('No se encontró el elemento #user-qr-reader.');
        }

        qrScanner.value = new Html5Qrcode('user-qr-reader');

        await qrScanner.value.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 230, height: 230 },
                aspectRatio: 1,
            },
            async (decodedText) => {
                await findUserByQr(decodedText);
            },
            () => {
                // Se ejecuta mientras la cámara busca un QR.
            },
        );
    } catch (error) {
        console.error('Error iniciando lector QR:', error);

        scanning.value = false;
        qrScanner.value = null;

        if (error instanceof Error) {
            qrError.value = `Error de cámara: ${error.name} - ${error.message}`;
        } else if (typeof error === 'string') {
            qrError.value = `Error de cámara: ${error}`;
        } else {
            qrError.value = 'No fue posible iniciar el lector QR.';
        }
    }
};

/* Detener lector QR */
const stopQrScanner = async (): Promise<void> => {
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
        console.error('Error deteniendo lector QR:', error);
    }

    qrScanner.value = null;
    scanning.value = false;
};

/* Quitar usuario */
const clearIdentifiedUser = async (): Promise<void> => {
    await stopQrScanner();

    identifiedUser.value = null;
    form.user_id = null;
    qrError.value = null;
    manualQrToken.value = '';
};

/* Búsqueda manual */
const searchManualQr = async (): Promise<void> => {
    await findUserByQr(manualQrToken.value);
};

/* Limpiar cámara al salir */
onBeforeUnmount(async () => {
    await stopQrScanner();
});

/* Guardar orden */
const submit = (): void => {
    form.post(admin.ticketOrders.store().url);
};
</script>

<template>
    <Head title="Nueva orden de boletos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nueva orden de boletos"
            description="Registra una venta de boletos realizada en taquilla."
            section="Taquilla"
            :icon="Ticket"
            :action-icon="ArrowLeft"
            action-label="Volver a órdenes"
            :action-href="admin.ticketOrders.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <!-- Información de la venta -->
            <AdminCard
                title="Información de la venta"
                description="Esta orden se registrará como una venta de taquilla."
                :icon="Info"
            >
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-2 self-start rounded-full bg-[#EDF5EF] px-3 py-1.5 text-xs font-medium text-[#187744]">
                        <Store class="h-3.5 w-3.5" />
                        Taquilla
                    </div>

                    <div class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
                        <UserRound class="mt-0.5 h-5 w-5 shrink-0 text-[#187744]" />

                        <div>
                            <p class="text-sm font-semibold text-[#244334]">
                                Vendedor
                            </p>
                            <p class="mt-1 text-sm leading-5 text-[#718477]">
                                Se asignará automáticamente al usuario que está registrando la venta.
                            </p>
                        </div>
                    </div>

                    <!-- Cliente -->
                    <div class="rounded-xl border border-[#DCE7DF] p-4 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF4ED] text-[#187744]">
                                    <QrCode class="h-5 w-5" />
                                </div>

                                <div>
                                    <h3 class="text-sm font-semibold text-[#244334]">
                                        Cliente / visitante
                                    </h3>
                                    <p class="mt-1 text-sm leading-5 text-[#718477]">
                                        Escanea el código QR personal del visitante para asociar la venta a su cuenta.
                                    </p>
                                </div>
                            </div>

                            <AdminButton
                                v-if="!scanning"
                                type="button"
                                variant="secondary"
                                :icon="Camera"
                                @click="startQrScanner"
                            >
                                Escanear QR
                            </AdminButton>

                            <AdminButton
                                v-else
                                type="button"
                                variant="secondary"
                                :icon="X"
                                @click="stopQrScanner"
                            >
                                Detener cámara
                            </AdminButton>
                        </div>

                        <!-- Lector QR -->
                        <div v-if="scanning" class="mt-5 flex justify-center">
                            <div class="relative h-[320px] w-full max-w-[320px] overflow-hidden rounded-2xl border border-[#DCE7DF] bg-black">
                                <div
                                    id="user-qr-reader"
                                    class="absolute inset-0 [&_video]:h-full [&_video]:w-full [&_video]:object-cover"
                                ></div>

                                <div class="pointer-events-none absolute inset-0 z-20 grid grid-cols-[1fr_230px_1fr] grid-rows-[1fr_230px_1fr]">
                                    <div class="col-span-3 bg-black/55"></div>
                                    <div class="bg-black/55"></div>

                                    <div class="relative">
                                        <span class="absolute left-0 top-0 h-9 w-9 rounded-tl-xl border-l-4 border-t-4 border-[#187744]"></span>
                                        <span class="absolute right-0 top-0 h-9 w-9 rounded-tr-xl border-r-4 border-t-4 border-[#187744]"></span>
                                        <span class="absolute bottom-0 left-0 h-9 w-9 rounded-bl-xl border-b-4 border-l-4 border-[#187744]"></span>
                                        <span class="absolute bottom-0 right-0 h-9 w-9 rounded-br-xl border-b-4 border-r-4 border-[#187744]"></span>
                                        <span class="absolute left-3 right-3 top-1/2 h-0.5 -translate-y-1/2 animate-pulse bg-[#187744]"></span>
                                    </div>

                                    <div class="bg-black/55"></div>
                                    <div class="col-span-3 bg-black/55"></div>
                                </div>

                                <div class="pointer-events-none absolute bottom-3 left-0 right-0 z-30 text-center">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-xs text-white">
                                        <QrCode class="h-3.5 w-3.5" />
                                        Coloca el QR dentro del recuadro
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Búsqueda manual -->
                        <div v-if="!identifiedUser" class="mt-5">
                            <label for="manual-qr" class="form-label">
                                Código QR manual
                            </label>

                            <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                                <div class="relative min-w-0 flex-1">
                                    <QrCode class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]" />

                                    <input
                                        id="manual-qr"
                                        v-model="manualQrToken"
                                        type="text"
                                        placeholder="Ingresa el código QR del visitante"
                                        class="form-input pl-11"
                                        @keyup.enter="searchManualQr"
                                    />
                                </div>

                                <AdminButton
                                    type="button"
                                    variant="secondary"
                                    :icon="Search"
                                    :loading="searchingUser"
                                    loading-text="Buscando..."
                                    :disabled="!manualQrToken.trim()"
                                    @click="searchManualQr"
                                >
                                    Buscar usuario
                                </AdminButton>
                            </div>
                        </div>

                        <!-- Usuario identificado -->
                        <div
                            v-if="identifiedUser"
                            class="mt-5 rounded-xl border border-[#B8DCC3] bg-[#F0F8F2] p-4 sm:p-5"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 items-start gap-3">
                                    <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-[#187744]" />

                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#187744]">
                                            Usuario identificado
                                        </p>

                                        <p class="mt-1 text-lg font-semibold text-[#244334]">
                                            {{ identifiedUser.name }}
                                        </p>

                                        <p class="mt-1 break-all text-sm text-[#718477]">
                                            {{ identifiedUser.email }}
                                        </p>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="rounded-lg border border-[#DCE7DF] bg-white px-3 py-1.5 text-xs text-[#435D4C]">
                                                {{ identifiedUser.level?.name ?? 'Sin nivel' }}
                                            </span>

                                            <span class="rounded-lg border border-[#DCE7DF] bg-white px-3 py-1.5 text-xs text-[#435D4C]">
                                                {{ identifiedUser.points.toLocaleString('es-MX') }} puntos
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <AdminButton
                                    type="button"
                                    variant="secondary"
                                    :icon="X"
                                    @click="clearIdentifiedUser"
                                >
                                    Quitar usuario
                                </AdminButton>
                            </div>
                        </div>

                        <div
                            v-if="qrError"
                            class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4"
                        >
                            <p class="text-sm font-medium text-red-600">
                                {{ qrError }}
                            </p>
                        </div>
                    </div>
                </div>
            </AdminCard>

            <!-- Boletos -->
            <AdminCard
                title="Boletos"
                description="Selecciona los tipos y cantidades de boletos que formarán parte de la orden."
                :icon="Ticket"
            >
                <div v-if="ticketTypes.length" class="flex flex-col gap-3">
                    <div
                        v-for="ticketType in ticketTypes"
                        :key="ticketType.id"
                        class="flex flex-col gap-4 rounded-xl border border-[#DCE7DF] p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF4ED] text-[#187744]">
                                <Ticket class="h-5 w-5" />
                            </div>

                            <div>
                                <p class="text-sm font-semibold text-[#244334]">
                                    {{ ticketType.name }}
                                </p>
                                <p class="mt-1 text-sm text-[#718477]">
                                    {{ formatCurrency(ticketType.price) }} por boleto
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 self-end sm:self-auto">
                            <button
                                type="button"
                                :disabled="getQuantity(ticketType.id) === 0"
                                aria-label="Restar boleto"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#DCE7DF] bg-white text-[#435D4C] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-40"
                                @click="removeTicket(ticketType.id)"
                            >
                                <Minus class="h-4 w-4" />
                            </button>

                            <span class="w-8 text-center text-sm font-semibold tabular-nums text-[#244334]">
                                {{ getQuantity(ticketType.id) }}
                            </span>

                            <button
                                type="button"
                                aria-label="Agregar boleto"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[#DCE7DF] bg-white text-[#435D4C] transition hover:bg-[#F2F7F4]"
                                @click="addTicket(ticketType.id)"
                            >
                                <Plus class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed border-[#C8DCCF] bg-[#F7FAF8] p-8 text-center"
                >
                    <Ticket class="mx-auto h-7 w-7 text-[#718477]" />
                    <p class="mt-3 text-sm text-[#718477]">
                        No hay tipos de boleto activos.
                    </p>
                </div>

                <p v-if="form.errors.items" class="form-error mt-3">
                    {{ form.errors.items }}
                </p>
            </AdminCard>

            <!-- Descuento y resumen -->
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <AdminCard
                    title="Descuento"
                    description="Aplica un descuento a la orden si corresponde."
                    :icon="WalletCards"
                >
                    <div class="form-field">
                        <label for="discount" class="form-label">
                            Importe del descuento
                        </label>

                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-[#718477]">
                                $
                            </span>

                            <input
                                id="discount"
                                v-model.number="form.discount"
                                type="number"
                                min="0"
                                :max="subtotal"
                                step="0.01"
                                class="form-input pl-8"
                                :class="{
                                    'form-input-error': form.errors.discount,
                                }"
                            />
                        </div>

                        <p class="form-help">
                            El descuento no puede superar el subtotal de la orden.
                        </p>

                        <p v-if="form.errors.discount" class="form-error">
                            {{ form.errors.discount }}
                        </p>
                    </div>
                </AdminCard>

                <AdminCard
                    title="Resumen de la orden"
                    description="Revisa el importe antes de registrar la venta."
                    :icon="Receipt"
                >
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-[#718477]">Subtotal</span>
                            <span class="font-medium text-[#244334]">
                                {{ formatCurrency(subtotal) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span class="text-[#718477]">Descuento</span>
                            <span class="font-medium text-[#187744]">
                                -{{ formatCurrency(discount) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 border-t border-[#E1EBE4] pt-4">
                            <span class="font-semibold text-[#244334]">
                                Total
                            </span>
                            <span class="text-2xl font-semibold text-[#187744]">
                                {{ formatCurrency(total) }}
                            </span>
                        </div>
                    </div>
                </AdminCard>
            </div>

            <!-- Métodos de pago -->
            <AdminCard
                title="Métodos de pago"
                description="Puedes utilizar uno o varios métodos de pago para cubrir el total de la orden."
                :icon="CreditCard"
            >
                <template #default>
                    <div class="flex flex-col gap-5">
                        <div class="flex justify-end">
                            <AdminButton
                                type="button"
                                variant="secondary"
                                :icon="Plus"
                                :disabled="paymentMethods.length === 0"
                                @click="addPayment"
                            >
                                Agregar pago
                            </AdminButton>
                        </div>

                        <div
                            v-if="paymentMethods.length === 0"
                            class="rounded-xl border border-dashed border-[#C8DCCF] bg-[#F7FAF8] p-8 text-center"
                        >
                            <CreditCard class="mx-auto h-7 w-7 text-[#718477]" />
                            <p class="mt-3 text-sm text-[#718477]">
                                No hay métodos de pago activos.
                            </p>
                        </div>

                        <div
                            v-else-if="form.payments.length === 0"
                            class="rounded-xl border border-dashed border-[#C8DCCF] bg-[#F7FAF8] p-8 text-center"
                        >
                            <CreditCard class="mx-auto h-7 w-7 text-[#718477]" />
                            <p class="mt-3 text-sm text-[#718477]">
                                Agrega al menos un método de pago para continuar.
                            </p>

                            <div class="mt-4 flex justify-center">
                                <AdminButton
                                    type="button"
                                    variant="primary"
                                    :icon="Plus"
                                    @click="addPayment"
                                >
                                    Agregar método de pago
                                </AdminButton>
                            </div>
                        </div>

                        <div v-else class="flex flex-col gap-4">
                            <div
                                v-for="(payment, index) in form.payments"
                                :key="index"
                                class="rounded-xl border border-[#DCE7DF] p-4 sm:p-5"
                            >
                                <div class="mb-4 flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold text-[#244334]">
                                        Pago {{ index + 1 }}
                                    </h3>

                                    <button
                                        type="button"
                                        :aria-label="`Quitar pago ${index + 1}`"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-[#718477] transition hover:bg-red-50 hover:text-red-600"
                                        @click="removePayment(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>

                                <div class="form-grid">
                                    <!-- Método -->
                                    <div class="form-field">
                                        <label
                                            :for="`payment-method-${index}`"
                                            class="form-label"
                                        >
                                            Método de pago
                                            <span class="form-required">*</span>
                                        </label>

                                        <div class="form-select-wrapper">
                                            <select
                                                :id="`payment-method-${index}`"
                                                v-model="payment.payment_method_id"
                                                class="form-select"
                                                :class="{
                                                    'form-select-error':
                                                        form.errors[`payments.${index}.payment_method_id`],
                                                }"
                                            >
                                                <option :value="null">
                                                    Selecciona un método
                                                </option>

                                                <option
                                                    v-for="method in paymentMethods"
                                                    :key="method.id"
                                                    :value="method.id"
                                                >
                                                    {{ method.name }}
                                                </option>
                                            </select>

                                            <WalletCards class="form-select-icon h-4 w-4" />
                                        </div>

                                        <p
                                            v-if="form.errors[`payments.${index}.payment_method_id`]"
                                            class="form-error"
                                        >
                                            {{ form.errors[`payments.${index}.payment_method_id`] }}
                                        </p>
                                    </div>

                                    <!-- Importe -->
                                    <div class="form-field">
                                        <label
                                            :for="`payment-amount-${index}`"
                                            class="form-label"
                                        >
                                            {{ isCashPayment(payment.payment_method_id) ? 'Efectivo recibido' : 'Monto' }}
                                            <span class="form-required">*</span>
                                        </label>

                                        <div class="relative">
                                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-[#718477]">
                                                $
                                            </span>

                                            <input
                                                :id="`payment-amount-${index}`"
                                                v-model.number="payment.amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="form-input pl-8"
                                                :class="{
                                                    'form-input-error':
                                                        form.errors[`payments.${index}.amount`],
                                                }"
                                            />
                                        </div>

                                        <p
                                            v-if="form.errors[`payments.${index}.amount`]"
                                            class="form-error"
                                        >
                                            {{ form.errors[`payments.${index}.amount`] }}
                                        </p>
                                    </div>

                                    <!-- Referencia -->
                                    <div class="form-field form-field-full">
                                        <label
                                            :for="`payment-reference-${index}`"
                                            class="form-label"
                                        >
                                            Referencia
                                            <span
                                                v-if="requiresReference(payment.payment_method_id)"
                                                class="form-required"
                                            >
                                                *
                                            </span>
                                            <span
                                                v-else
                                                class="font-normal text-[#718477]"
                                            >
                                                (opcional)
                                            </span>
                                        </label>

                                        <input
                                            :id="`payment-reference-${index}`"
                                            v-model="payment.reference"
                                            type="text"
                                            :placeholder="
                                                requiresReference(payment.payment_method_id)
                                                    ? 'Ingresa la referencia de la operación'
                                                    : 'Ingresa una referencia si corresponde'
                                            "
                                            class="form-input"
                                            :class="{
                                                'form-input-error':
                                                    form.errors[`payments.${index}.reference`],
                                            }"
                                        />

                                        <p
                                            v-if="form.errors[`payments.${index}.reference`]"
                                            class="form-error"
                                        >
                                            {{ form.errors[`payments.${index}.reference`] }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="isCashPayment(payment.payment_method_id)"
                                    class="mt-4 flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
                                >
                                    <Banknote class="h-5 w-5 shrink-0 text-[#187744]" />
                                    <p class="text-sm leading-5 text-[#718477]">
                                        El efectivo recibido puede ser mayor al saldo pendiente. El excedente se devuelve como cambio.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen de pagos -->
                        <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:p-5">
                            <div class="mb-5 flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF4ED] text-[#187744]">
                                    <WalletCards class="h-5 w-5" />
                                </div>

                                <div>
                                    <h3 class="text-sm font-semibold text-[#244334]">
                                        Resumen de pagos
                                    </h3>
                                    <p class="mt-1 text-sm text-[#718477]">
                                        Estado de los importes registrados.
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-4">
                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="text-[#718477]">Total de la orden</span>
                                    <span class="font-semibold text-[#244334]">
                                        {{ formatCurrency(total) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="text-[#718477]">Pagos electrónicos / otros</span>
                                    <span class="font-medium text-[#244334]">
                                        {{ formatCurrency(nonCashPaymentsTotal) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-4 text-sm">
                                    <span class="text-[#718477]">Efectivo recibido</span>
                                    <span class="font-medium text-[#244334]">
                                        {{ formatCurrency(cashPaymentsTotal) }}
                                    </span>
                                </div>

                                <div
                                    v-if="hasPaymentError"
                                    class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4"
                                >
                                    <X class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                                    <p class="text-sm leading-5 text-red-600">
                                        Los pagos que no son en efectivo no pueden superar el total de la orden.
                                    </p>
                                </div>

                                <div
                                    v-if="remaining > 0"
                                    class="flex items-center justify-between gap-4 border-t border-[#DCE7DF] pt-4"
                                >
                                    <span class="text-sm font-semibold text-[#244334]">
                                        Falta pagar
                                    </span>
                                    <span class="text-lg font-semibold text-red-600">
                                        {{ formatCurrency(remaining) }}
                                    </span>
                                </div>

                                <div
                                    v-else-if="change > 0"
                                    class="flex items-center justify-between gap-4 border-t border-[#DCE7DF] pt-4"
                                >
                                    <span class="text-sm font-semibold text-[#244334]">
                                        Cambio
                                    </span>
                                    <span class="text-lg font-semibold text-[#187744]">
                                        {{ formatCurrency(change) }}
                                    </span>
                                </div>

                                <div
                                    v-else-if="isPaymentComplete"
                                    class="flex items-center justify-between gap-4 border-t border-[#DCE7DF] pt-4"
                                >
                                    <span class="text-sm font-semibold text-[#244334]">
                                        Estado
                                    </span>
                                    <span class="inline-flex items-center gap-2 text-sm font-semibold text-[#187744]">
                                        <CheckCircle2 class="h-4 w-4" />
                                        Pago completo
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.payments" class="form-error">
                            {{ form.errors.payments }}
                        </p>
                    </div>
                </template>
            </AdminCard>

            <!-- Acciones -->
            <div class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-end">
                <CancelButton
                    :href="admin.ticketOrders.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Procesando..."
                    :disabled="
                        form.items.length === 0 ||
                        form.payments.length === 0 ||
                        !isPaymentComplete ||
                        hasPaymentError
                    "
                >
                    Crear orden
                </AdminButton>
            </div>
        </form>
    </div>
</template>