<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { nextTick, onMounted, ref } from 'vue';

import admin from '@/routes/admin';

interface User {
    id: number;
    name: string;
    email?: string;
}

interface TicketType {
    id: number;
    name: string;
    price?: string | number;
}

interface OrderItem {
    id: number;
    quantity: number;
    unit_price: string | number;
    subtotal: string | number;
    ticket_type: TicketType;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface Payment {
    id: number;
    amount: string | number;
    reference: string | null;
    payment_method: PaymentMethod;
}

interface Ticket {
    id: number;
    qr_token: string;
    status: string;
    used_at: string | null;
    ticket_type: TicketType;
    validated_by: User | null;
}

interface Order {
    id: number;
    folio: string;
    source: 'app' | 'taquilla';
    subtotal: string | number;
    discount: string | number;
    total: string | number;
    status: string;
    paid_at: string | null;
    created_at: string;

    user: User | null;
    seller: User | null;

    items: OrderItem[];
    payments: Payment[];
    tickets: Ticket[];
}

const props = defineProps<{
    order: Order;
}>();

/*
|--------------------------------------------------------------------------
| QR
|--------------------------------------------------------------------------
*/

const qrCodes = ref<Record<number, string>>({});

const generateQRCodes = async (): Promise<void> => {
    for (const ticket of props.order.tickets) {
        if (qrCodes.value[ticket.id]) {
            continue;
        }

        try {
            const qr = await QRCode.toDataURL(
                ticket.qr_token,
                {
                    width: 300,
                    margin: 2,
                    errorCorrectionLevel: 'H',
                },
            );

            qrCodes.value[ticket.id] = qr;
        } catch (error) {
            console.error(
                `Error generando QR del boleto ${ticket.id}:`,
                error,
            );
        }
    }
};

onMounted(async () => {
    await generateQRCodes();
});

/*
|--------------------------------------------------------------------------
| Formato
|--------------------------------------------------------------------------
*/

const formatCurrency = (
    value: number | string,
): string => {
    return Number(value).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
};

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
};

/*
|--------------------------------------------------------------------------
| Estados
|--------------------------------------------------------------------------
*/

const statusLabel = (
    status: string,
): string => {
    const statuses: Record<string, string> = {
        pending: 'Pendiente',
        paid: 'Pagada',
        cancelled: 'Cancelada',
        refunded: 'Reembolsada',
    };

    return statuses[status] ?? status;
};

const ticketStatusLabel = (
    status: string,
): string => {
    const statuses: Record<string, string> = {
        active: 'Activo',
        used: 'Utilizado',
        cancelled: 'Cancelado',
    };

    return statuses[status] ?? status;
};

const statusClass = (
    status: string,
): string => {
    const classes: Record<string, string> = {
        pending:
            'border-yellow-500/30 bg-yellow-500/10 text-yellow-600 dark:text-yellow-400',

        paid:
            'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400',

        cancelled:
            'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400',

        refunded:
            'border-orange-500/30 bg-orange-500/10 text-orange-600 dark:text-orange-400',
    };

    return (
        classes[status] ??
        'border-sidebar-border bg-muted text-muted-foreground'
    );
};

const ticketStatusClass = (
    status: string,
): string => {
    const classes: Record<string, string> = {
        active:
            'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400',

        used:
            'border-blue-500/30 bg-blue-500/10 text-blue-600 dark:text-blue-400',

        cancelled:
            'border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400',
    };

    return (
        classes[status] ??
        'border-sidebar-border bg-muted text-muted-foreground'
    );
};

/*
|--------------------------------------------------------------------------
| Pagos
|--------------------------------------------------------------------------
*/

const cashPaymentsTotal = (): number => {
    return props.order.payments
        .filter(
            (payment) =>
                payment.payment_method.code === 'cash',
        )
        .reduce(
            (total, payment) =>
                total + Number(payment.amount),
            0,
        );
};

const nonCashPaymentsTotal = (): number => {
    return props.order.payments
        .filter(
            (payment) =>
                payment.payment_method.code !== 'cash',
        )
        .reduce(
            (total, payment) =>
                total + Number(payment.amount),
            0,
        );
};

const change = (): number => {
    const cash = cashPaymentsTotal();
    const nonCash = nonCashPaymentsTotal();

    const remaining = Math.max(
        Number(props.order.total) - nonCash,
        0,
    );

    return Math.max(
        cash - remaining,
        0,
    );
};

/*
|--------------------------------------------------------------------------
| Seguridad para impresión
|--------------------------------------------------------------------------
*/

const escapeHtml = (
    value: unknown,
): string => {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

/*
|--------------------------------------------------------------------------
| Impresión de boletos
|--------------------------------------------------------------------------
*/

const printTickets = async (): Promise<void> => {
    await generateQRCodes();

    await nextTick();

    const printWindow = window.open(
        '',
        '_blank',
        'width=400,height=800',
    );

    if (!printWindow) {
        window.alert(
            'Permite las ventanas emergentes del navegador para imprimir los boletos.',
        );

        return;
    }

    const ticketsHtml = props.order.tickets
        .map(
            (ticket, index) => {
                const qr =
                    qrCodes.value[ticket.id] ?? '';

                return `
                    <div class="ticket">

                        <div class="ticket-header">

                            <h1>
                                ZOOLÓGICO SAHUATOBA
                            </h1>

                            <p>
                                Boleto de acceso
                            </p>

                            <div class="ticket-number">
                                #${index + 1}
                            </div>

                        </div>

                        <div class="ticket-info">

                            <div class="info-item">
                                <span>
                                    Tipo
                                </span>

                                <strong>
                                    ${escapeHtml(
                                        ticket.ticket_type.name,
                                    )}
                                </strong>
                            </div>

                            <div class="info-item">
                                <span>
                                    Folio
                                </span>

                                <strong>
                                    ${escapeHtml(
                                        props.order.folio,
                                    )}
                                </strong>
                            </div>

                            <div class="info-item">
                                <span>
                                    Fecha
                                </span>

                                <strong>
                                    ${escapeHtml(
                                        formatDate(
                                            props.order.created_at,
                                        ),
                                    )}
                                </strong>
                            </div>

                        </div>

                        <div class="ticket-qr">

                            ${
                                qr
                                    ? `
                                        <img
                                            src="${qr}"
                                            alt="Código QR"
                                        >
                                    `
                                    : `
                                        <div class="qr-error">
                                            No se pudo generar el QR
                                        </div>
                                    `
                            }

                            <p>
                                Presenta este código QR
                                en el acceso
                            </p>

                        </div>

                        <div class="ticket-footer">

                            <span>
                                Zoológico Sahuatoba
                            </span>

                            <span>
                                ${escapeHtml(
                                    props.order.folio,
                                )}
                            </span>

                        </div>

                    </div>
                `;
            },
        )
        .join('');

    printWindow.document.open();

    printWindow.document.write(`
        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>
                ${escapeHtml(props.order.folio)}
            </title>

            <style>

                @page {
                    size: 80mm auto;
                    margin: 0;
                }

                * {
                    box-sizing: border-box;
                }

                html {
                    margin: 0;
                    padding: 0;
                }

                body {
                    width: 100%;
                    margin: 0;
                    padding: 0;

                    background: #ffffff;
                    color: #000000;

                    font-family:
                        Arial,
                        Helvetica,
                        sans-serif;

                    font-size: 10pt;
                }

                /*
                |--------------------------------------------------------------------------
                | CONTENEDOR DE IMPRESIÓN
                |--------------------------------------------------------------------------
                */

                .print-container {
                    width: 80mm;
                    max-width: 80mm;

                    margin: 0 auto;
                    padding: 0;

                    display: flex;
                    flex-direction: column;
                    align-items: center;
                }

                /*
                |--------------------------------------------------------------------------
                | CARD DEL BOLETO
                |--------------------------------------------------------------------------
                */

                .ticket {
                    width: 72mm;
                    min-width: 72mm;
                    max-width: 72mm;

                    margin: 0 auto 5mm auto;
                    padding: 5mm;

                    display: flex;
                    flex-direction: column;
                    align-items: center;

                    text-align: center;

                    background: #ffffff;
                    color: #000000;

                    border: 1px solid #000000;
                    border-radius: 3mm;

                    page-break-inside: avoid;
                    break-inside: avoid;
                }

                .ticket-header {
                    width: 100%;

                    display: flex;
                    flex-direction: column;
                    align-items: center;

                    padding-bottom: 3mm;

                    border-bottom:
                        1px dashed #000000;
                }

                .ticket-header h1 {
                    width: 100%;

                    margin: 0;

                    font-size: 13pt;
                    line-height: 1.2;
                    font-weight: 700;

                    text-align: center;
                }

                .ticket-header p {
                    width: 100%;

                    margin:
                        1.5mm 0 2.5mm;

                    font-size: 8pt;
                    line-height: 1.2;

                    text-align: center;
                }

                .ticket-number {
                    width: 10mm;
                    height: 10mm;

                    display: flex;

                    align-items: center;
                    justify-content: center;

                    border:
                        1px solid #000000;

                    border-radius: 50%;

                    font-size: 8pt;
                    font-weight: 700;
                }

                .ticket-info {
                    width: 100%;

                    display: flex;
                    flex-direction: column;

                    gap: 2.5mm;

                    margin-top: 4mm;

                    text-align: center;
                }

                .info-item {
                    width: 100%;

                    display: flex;
                    flex-direction: column;
                    align-items: center;

                    gap: 0.5mm;
                }

                .info-item span {
                    font-size: 7pt;

                    text-transform: uppercase;

                    font-weight: 600;
                }

                .info-item strong {
                    font-size: 9pt;

                    font-weight: 700;

                    word-break: break-word;
                    overflow-wrap: anywhere;
                }

                .ticket-qr {
                    width: 100%;

                    display: flex;
                    flex-direction: column;
                    align-items: center;

                    margin-top: 5mm;

                    text-align: center;
                }

                .ticket-qr img {
                    display: block;

                    width: 45mm;
                    height: 45mm;

                    margin: 0 auto;

                    object-fit: contain;
                }

                .ticket-qr p {
                    width: 100%;

                    margin:
                        2mm 0 0;

                    font-size: 7pt;
                    line-height: 1.2;

                    text-align: center;
                }

                .qr-error {
                    width: 45mm;
                    height: 45mm;

                    display: flex;

                    align-items: center;
                    justify-content: center;

                    border:
                        1px solid #000000;

                    font-size: 7pt;

                    text-align: center;
                }

                .ticket-footer {
                    width: 100%;

                    display: flex;
                    flex-direction: column;
                    align-items: center;

                    gap: 1mm;

                    margin-top: 4mm;
                    padding-top: 2mm;

                    border-top:
                        1px dashed #000000;

                    font-size: 6.5pt;
                    line-height: 1.2;

                    text-align: center;
                }

            </style>

        </head>

        <body>

            <div class="print-container">
                ${ticketsHtml}
            </div>

        </body>

        </html>
    `);

    printWindow.document.close();

    printWindow.focus();

    const images =
        Array.from(
            printWindow.document.images,
        );

    await Promise.all(
        images.map(
            (image) =>
                new Promise<void>(
                    (resolve) => {
                        if (image.complete) {
                            resolve();

                            return;
                        }

                        image.onload = () => resolve();
                        image.onerror = () => resolve();
                    },
                ),
        ),
    );

    await new Promise<void>(
        (resolve) => {
            setTimeout(resolve, 300);
        },
    );

    printWindow.print();

    printWindow.onafterprint = () => {
        printWindow.close();
    };
};

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [],
    },
});
</script>

<template>
    <Head
        :title="`Orden ${order.folio}`"
    />

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
                        Orden {{ order.folio }}
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Consulta el detalle de la
                        venta y los boletos generados.
                    </p>
                </div>

                <div
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <Link
                        :href="
                            admin.ticketOrders
                                .index()
                                .url
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Regresar
                    </Link>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        @click="printTickets"
                    >
                        Imprimir boletos
                    </button>
                </div>
            </div>
        </div>

        <!-- Información de la orden -->

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
                        Información de la orden
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Datos generales de la venta.
                    </p>
                </div>

                <span
                    class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-medium"
                    :class="
                        statusClass(order.status)
                    "
                >
                    {{
                        statusLabel(order.status)
                    }}
                </span>
            </div>

            <div
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Folio
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ order.folio }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Origen
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            order.source ===
                            'taquilla'
                                ? 'Taquilla'
                                : 'Aplicación'
                        }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Fecha
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            formatDate(
                                order.created_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Fecha de pago
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            formatDate(
                                order.paid_at,
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Cliente y vendedor -->

        <div
            class="grid gap-4 lg:grid-cols-2"
        >
            <!-- Cliente -->

            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Cliente
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Información del cliente asociado
                        a la orden.
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Nombre
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            order.user?.name ??
                            'Venta en taquilla'
                        }}
                    </p>

                    <template
                        v-if="order.user?.email"
                    >
                        <p
                            class="mt-4 text-sm font-medium"
                        >
                            Correo electrónico
                        </p>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ order.user.email }}
                        </p>
                    </template>
                </div>
            </div>

            <!-- Vendedor -->

            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Vendedor
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Usuario que registró la venta.
                    </p>
                </div>

                <div
                    class="rounded-lg border border-sidebar-border p-4"
                >
                    <p class="text-sm font-medium">
                        Nombre
                    </p>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{
                            order.seller?.name ??
                            '—'
                        }}
                    </p>

                    <template
                        v-if="order.seller?.email"
                    >
                        <p
                            class="mt-4 text-sm font-medium"
                        >
                            Correo electrónico
                        </p>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ order.seller.email }}
                        </p>
                    </template>
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
                    Tipos y cantidades incluidos en
                    la orden.
                </p>
            </div>

            <div
                class="overflow-x-auto rounded-lg border border-sidebar-border"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border bg-muted/40"
                        >
                            <th
                                class="px-4 py-3 text-left font-medium"
                            >
                                Tipo
                            </th>

                            <th
                                class="px-4 py-3 text-center font-medium"
                            >
                                Cantidad
                            </th>

                            <th
                                class="px-4 py-3 text-right font-medium"
                            >
                                Precio
                            </th>

                            <th
                                class="px-4 py-3 text-right font-medium"
                            >
                                Subtotal
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in order.items"
                            :key="item.id"
                            class="border-b border-sidebar-border last:border-0"
                        >
                            <td class="px-4 py-3">
                                <span
                                    class="font-medium"
                                >
                                    {{
                                        item
                                            .ticket_type
                                            .name
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-4 py-3 text-center"
                            >
                                {{ item.quantity }}
                            </td>

                            <td
                                class="px-4 py-3 text-right"
                            >
                                {{
                                    formatCurrency(
                                        item.unit_price,
                                    )
                                }}
                            </td>

                            <td
                                class="px-4 py-3 text-right font-medium"
                            >
                                {{
                                    formatCurrency(
                                        item.subtotal,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Resumen y pagos -->

        <div
            class="grid gap-4 lg:grid-cols-2"
        >
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

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Resumen económico de la orden.
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-muted-foreground"
                        >
                            Subtotal
                        </span>

                        <span class="font-medium">
                            {{
                                formatCurrency(
                                    order.subtotal,
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

                        <span class="font-medium">
                            -
                            {{
                                formatCurrency(
                                    order.discount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between border-t border-sidebar-border pt-3"
                    >
                        <span class="font-semibold">
                            Total
                        </span>

                        <span
                            class="text-xl font-semibold"
                        >
                            {{
                                formatCurrency(
                                    order.total,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Pagos -->

            <div
                class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <div class="mb-6">
                    <h2
                        class="text-lg font-semibold"
                    >
                        Pagos
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Métodos de pago registrados en la
                        orden.
                    </p>
                </div>

                <div
                    v-if="order.payments.length > 0"
                    class="space-y-3"
                >
                    <div
                        v-for="payment in order.payments"
                        :key="payment.id"
                        class="rounded-lg border border-sidebar-border p-4"
                    >
                        <div
                            class="flex items-center justify-between gap-4"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium"
                                >
                                    {{
                                        payment
                                            .payment_method
                                            .name
                                    }}
                                </p>

                                <p
                                    v-if="
                                        payment.reference
                                    "
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Referencia:
                                    {{
                                        payment.reference
                                    }}
                                </p>
                            </div>

                            <span
                                class="text-sm font-semibold"
                            >
                                {{
                                    formatCurrency(
                                        payment.amount,
                                    )
                                }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="change() > 0"
                        class="flex justify-between border-t border-sidebar-border pt-3 text-sm"
                    >
                        <span class="font-medium">
                            Cambio
                        </span>

                        <span class="font-semibold">
                            {{
                                formatCurrency(
                                    change(),
                                )
                            }}
                        </span>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-sidebar-border p-6 text-center"
                >
                    <p
                        class="text-sm text-muted-foreground"
                    >
                        No hay pagos registrados.
                    </p>
                </div>
            </div>
        </div>

        <!-- Boletos generados -->

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
                        Boletos generados
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Cada boleto cuenta con un código QR
                        único para validar el acceso.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    @click="printTickets"
                >
                    Imprimir boletos
                </button>
            </div>

            <div
                v-if="order.tickets.length > 0"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="(
                        ticket, index
                    ) in order.tickets"
                    :key="ticket.id"
                    class="rounded-lg border border-sidebar-border p-5"
                >
                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Boleto #{{ index + 1 }}
                            </p>

                            <p
                                class="mt-1 font-medium"
                            >
                                {{
                                    ticket
                                        .ticket_type
                                        .name
                                }}
                            </p>
                        </div>

                        <span
                            class="inline-flex rounded-full border px-2.5 py-1 text-xs font-medium"
                            :class="
                                ticketStatusClass(
                                    ticket.status,
                                )
                            "
                        >
                            {{
                                ticketStatusLabel(
                                    ticket.status,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="mt-5 flex justify-center"
                    >
                        <div
                            v-if="
                                qrCodes[ticket.id]
                            "
                            class="rounded-lg border border-sidebar-border p-3"
                        >
                            <img
                                :src="
                                    qrCodes[
                                        ticket.id
                                    ]
                                "
                                alt="Código QR"
                                class="h-40 w-40"
                            />
                        </div>

                        <div
                            v-else
                            class="flex h-40 w-40 items-center justify-center rounded-lg border border-dashed border-sidebar-border"
                        >
                            <span
                                class="text-xs text-muted-foreground"
                            >
                                Generando QR...
                            </span>
                        </div>
                    </div>

                    <div
                        class="mt-5 space-y-3 border-t border-sidebar-border pt-4"
                    >
                        <div>
                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Token QR
                            </p>

                            <p
                                class="mt-1 break-all font-mono text-xs"
                            >
                                {{ ticket.qr_token }}
                            </p>
                        </div>

                        <div
                            class="flex justify-between gap-4"
                        >
                            <span
                                class="text-xs text-muted-foreground"
                            >
                                Utilizado
                            </span>

                            <span
                                class="text-right text-xs font-medium"
                            >
                                {{
                                    formatDate(
                                        ticket.used_at,
                                    )
                                }}
                            </span>
                        </div>

                        <div
                            v-if="
                                ticket.validated_by
                            "
                            class="flex justify-between gap-4"
                        >
                            <span
                                class="text-xs text-muted-foreground"
                            >
                                Validó
                            </span>

                            <span
                                class="text-right text-xs font-medium"
                            >
                                {{
                                    ticket
                                        .validated_by
                                        .name
                                }}
                            </span>
                        </div>
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
                    No hay boletos generados para esta
                    orden.
                </p>
            </div>
        </div>
    </div>
</template>