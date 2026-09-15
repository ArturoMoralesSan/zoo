<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleDollarSign,
    CreditCard,
    Heart,
    Receipt,
    Ticket,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import VueApexCharts from 'vue3-apexcharts';

import admin from '@/routes/admin';

interface Today {
    sales: number;
    income: number;
    tickets: number;
    average_ticket: number;
    donations: number;
    donations_amount: number;
}

interface Period {
    sales: number;
    income: number;
    tickets: number;
    donations: number;
    donations_amount: number;
}

interface SalesByDay {
    date: string;
    sales: number;
    amount: number;
}

interface DonationByDay {
    date: string;
    donations: number;
    amount: number;
}

interface TicketByType {
    id: number;
    name: string;
    quantity: number;
    amount: number;
    percentage: number;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
    sales: number;
    amount: number;
    percentage: number;
}

interface Props {
    filters: {
        from: string;
        to: string;
    };

    today: Today;

    period: Period;

    sales_by_day: SalesByDay[];

    donations_by_day: DonationByDay[];

    tickets_by_type: TicketByType[];

    payment_methods: PaymentMethod[];
}

const props = defineProps<Props>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const submitFilter = (): void => {
    router.get(
        admin.dashboard().url,
        {
            from: from.value,
            to: to.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(amount);
};

const formatDate = (date: string): string => {
    const parsed = new Date(`${date}T00:00:00`);

    return parsed.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
    });
};

/*
|--------------------------------------------------------------------------
| Gráfica de ventas
|--------------------------------------------------------------------------
*/

const salesCategories = computed(() => {
    return props.sales_by_day.map(
        (item) => formatDate(item.date),
    );
});

const salesChartSeries = computed(() => {
    return [
        {
            name: 'Ventas',
            type: 'column',
            data: props.sales_by_day.map(
                (item) => item.sales,
            ),
        },
        {
            name: 'Ingresos',
            type: 'line',
            data: props.sales_by_day.map(
                (item) => item.amount,
            ),
        },
    ];
});

const salesChartOptions = computed(() => {
    return {
        chart: {
            type: 'line',
            height: 360,
            toolbar: {
                show: false,
            },
            fontFamily: 'inherit',
        },

        stroke: {
            width: [0, 3],
            curve: 'smooth',
        },

        plotOptions: {
            bar: {
                columnWidth: '45%',
                borderRadius: 5,
            },
        },

        xaxis: {
            categories: salesCategories.value,

            labels: {
                style: {
                    fontSize: '12px',
                },
            },
        },

        yaxis: [
            {
                title: {
                    text: 'Ventas',
                },

                labels: {
                    formatter: (value: number) => {
                        return Math.round(value).toString();
                    },
                },
            },
            {
                opposite: true,

                title: {
                    text: 'Ingresos',
                },

                labels: {
                    formatter: (value: number) => {
                        return formatCurrency(value);
                    },
                },
            },
        ],

        tooltip: {
            shared: true,
            intersect: false,

            y: {
                formatter: (
                    value: number,
                    {
                        seriesIndex,
                    }: {
                        seriesIndex: number;
                    },
                ) => {
                    if (seriesIndex === 0) {
                        return `${Math.round(value)} ventas`;
                    }

                    return formatCurrency(value);
                },
            },
        },

        legend: {
            position: 'top',
            horizontalAlign: 'right',
        },

        grid: {
            strokeDashArray: 4,
        },

        dataLabels: {
            enabled: false,
        },
    };
});

/*
|--------------------------------------------------------------------------
| Gráfica de donaciones
|--------------------------------------------------------------------------
*/

const donationCategories = computed(() => {
    return props.donations_by_day.map(
        (item) => formatDate(item.date),
    );
});

const donationsChartSeries = computed(() => {
    return [
        {
            name: 'Donaciones',
            data: props.donations_by_day.map(
                (item) => item.amount,
            ),
        },
    ];
});

const donationsChartOptions = computed(() => {
    return {
        chart: {
            type: 'area',
            height: 320,
            toolbar: {
                show: false,
            },
            fontFamily: 'inherit',
        },

        stroke: {
            curve: 'smooth',
            width: 3,
        },

        fill: {
            type: 'gradient',

            gradient: {
                opacityFrom: 0.35,
                opacityTo: 0.05,
            },
        },

        xaxis: {
            categories: donationCategories.value,

            labels: {
                style: {
                    fontSize: '12px',
                },
            },
        },

        yaxis: {
            labels: {
                formatter: (value: number) => {
                    return formatCurrency(value);
                },
            },
        },

        tooltip: {
            y: {
                formatter: (
                    value: number,
                    {
                        dataPointIndex,
                    }: {
                        dataPointIndex: number;
                    },
                ) => {
                    const donation =
                        props.donations_by_day[
                            dataPointIndex
                        ];

                    if (!donation) {
                        return formatCurrency(value);
                    }

                    return `${formatCurrency(value)} · ${donation.donations} donación${donation.donations === 1 ? '' : 'es'}`;
                },
            },
        },

        legend: {
            show: false,
        },

        grid: {
            strokeDashArray: 4,
        },

        dataLabels: {
            enabled: false,
        },
    };
});

/*
|--------------------------------------------------------------------------
| Gráfica de boletos
|--------------------------------------------------------------------------
*/

const ticketChartSeries = computed(() => {
    return [
        {
            name: 'Boletos',
            data: props.tickets_by_type.map(
                (item) => item.quantity,
            ),
        },
    ];
});

const ticketChartOptions = computed(() => {
    return {
        chart: {
            type: 'bar',
            height: Math.max(
                260,
                props.tickets_by_type.length * 55,
            ),
            toolbar: {
                show: false,
            },
            fontFamily: 'inherit',
        },

        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 5,
                barHeight: '55%',
            },
        },

        xaxis: {
            categories:
                props.tickets_by_type.map(
                    (item) => item.name,
                ),
        },

        tooltip: {
            y: {
                formatter: (value: number) => {
                    return `${Math.round(value)} boletos`;
                },
            },
        },

        legend: {
            show: false,
        },

        grid: {
            strokeDashArray: 4,
        },

        dataLabels: {
            enabled: false,
        },
    };
});
</script>

<template>
    <Head title="Panel" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- ===================================================== -->
        <!-- PANEL / FILTRO -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Panel
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Resumen general de taquilla,
                        boletos y donaciones.
                    </p>
                </div>

                <form
                    class="grid w-full gap-3 sm:grid-cols-3 md:w-auto"
                    @submit.prevent="submitFilter"
                >
                    <!-- Fecha inicial -->

                    <div>
                        <label
                            for="from"
                            class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >
                            Fecha inicial
                        </label>

                        <div class="relative">
                            <CalendarDays
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <input
                                id="from"
                                v-model="from"
                                type="date"
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2 pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>
                    </div>

                    <!-- Fecha final -->

                    <div>
                        <label
                            for="to"
                            class="mb-1.5 block text-xs font-medium text-muted-foreground"
                        >
                            Fecha final
                        </label>

                        <div class="relative">
                            <CalendarDays
                                class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            />

                            <input
                                id="to"
                                v-model="to"
                                type="date"
                                class="w-full rounded-lg border border-sidebar-border bg-background py-2 pl-9 pr-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>
                    </div>

                    <!-- Botón -->

                    <div class="flex items-end">
                        <button
                            type="submit"
                            class="inline-flex h-[38px] w-full items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                        >
                            <TrendingUp class="size-4" />

                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- KPIs DE HOY -->
        <!-- ===================================================== -->

        <div
            class="grid grid-cols-2 gap-4 lg:grid-cols-5"
        >
            <!-- Ventas hoy -->

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <span
                        class="text-sm text-muted-foreground"
                    >
                        Ventas hoy
                    </span>

                    <Receipt
                        class="size-5 text-muted-foreground"
                    />
                </div>

                <div
                    class="mt-3 text-2xl font-semibold"
                >
                    {{ today.sales }}
                </div>

                <div
                    class="mt-1 text-xs text-muted-foreground"
                >
                    operaciones
                </div>
            </div>

            <!-- Ingresos hoy -->

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <span
                        class="text-sm text-muted-foreground"
                    >
                        Ingresos hoy
                    </span>

                    <CircleDollarSign
                        class="size-5 text-muted-foreground"
                    />
                </div>

                <div
                    class="mt-3 text-2xl font-semibold"
                >
                    {{ formatCurrency(today.income) }}
                </div>

                <div
                    class="mt-1 text-xs text-muted-foreground"
                >
                    ventas pagadas
                </div>
            </div>

            <!-- Boletos hoy -->

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <span
                        class="text-sm text-muted-foreground"
                    >
                        Boletos hoy
                    </span>

                    <Ticket
                        class="size-5 text-muted-foreground"
                    />
                </div>

                <div
                    class="mt-3 text-2xl font-semibold"
                >
                    {{ today.tickets }}
                </div>

                <div
                    class="mt-1 text-xs text-muted-foreground"
                >
                    boletos vendidos
                </div>
            </div>

            <!-- Ticket promedio -->

            <div
                class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <span
                        class="text-sm text-muted-foreground"
                    >
                        Ticket promedio
                    </span>

                    <CreditCard
                        class="size-5 text-muted-foreground"
                    />
                </div>

                <div
                    class="mt-3 text-2xl font-semibold"
                >
                    {{
                        formatCurrency(
                            today.average_ticket,
                        )
                    }}
                </div>

                <div
                    class="mt-1 text-xs text-muted-foreground"
                >
                    por venta
                </div>
            </div>

            <!-- Donaciones hoy -->

            <div
                class="col-span-2 rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border lg:col-span-1"
            >
                <div
                    class="flex items-center justify-between"
                >
                    <span
                        class="text-sm text-muted-foreground"
                    >
                        Donaciones hoy
                    </span>

                    <Heart
                        class="size-5 text-muted-foreground"
                    />
                </div>

                <div
                    class="mt-3 text-2xl font-semibold"
                >
                    {{
                        formatCurrency(
                            today.donations_amount,
                        )
                    }}
                </div>

                <div
                    class="mt-1 text-xs text-muted-foreground"
                >
                    {{ today.donations }}
                    donación{{
                        today.donations === 1
                            ? ''
                            : 'es'
                    }}
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- VENTAS DEL PERIODO -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Ventas del periodo
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Comportamiento de las ventas durante el periodo seleccionado.
                    </p>
                </div>

                <div
                    class="text-sm text-muted-foreground"
                >
                    {{ period.sales }} ventas ·
                    {{ formatCurrency(period.income) }}
                </div>
            </div>

            <div
                v-if="sales_by_day.length > 0"
            >
                <VueApexCharts
                    type="line"
                    height="360"
                    :options="salesChartOptions"
                    :series="salesChartSeries"
                />
            </div>

            <div
                v-else
                class="flex h-[300px] items-center justify-center text-sm text-muted-foreground"
            >
                No hay ventas en el periodo seleccionado.
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- DONACIONES DEL PERIODO -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Donaciones del periodo
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Comportamiento de las donaciones durante el periodo seleccionado.
                    </p>
                </div>

                <div
                    class="text-sm text-muted-foreground"
                >
                    {{ period.donations }}
                    donaciones ·
                    {{
                        formatCurrency(
                            period.donations_amount,
                        )
                    }}
                </div>
            </div>

            <div
                v-if="donations_by_day.length > 0"
            >
                <VueApexCharts
                    type="area"
                    height="320"
                    :options="donationsChartOptions"
                    :series="donationsChartSeries"
                />
            </div>

            <div
                v-else
                class="flex h-[260px] items-center justify-center text-sm text-muted-foreground"
            >
                No hay donaciones en el periodo seleccionado.
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- TABLAS -->
        <!-- ===================================================== -->

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Boletos más vendidos -->

            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <Ticket
                            class="size-5 text-muted-foreground"
                        />

                        <div>
                            <h2 class="font-semibold">
                                Boletos más vendidos
                            </h2>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Distribución por tipo de boleto.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="tickets_by_type.length > 0"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full text-left text-sm"
                    >
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                        >
                            <tr>
                                <th
                                    class="px-5 py-3 font-semibold"
                                >
                                    Boleto
                                </th>

                                <th
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Cantidad
                                </th>

                                <th
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Ingresos
                                </th>

                                <th
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    %
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <tr
                                v-for="ticket in tickets_by_type"
                                :key="ticket.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td
                                    class="px-5 py-4 font-medium"
                                >
                                    {{ ticket.name }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right"
                                >
                                    {{ ticket.quantity }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right"
                                >
                                    {{
                                        formatCurrency(
                                            ticket.amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-muted-foreground"
                                >
                                    {{ ticket.percentage }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No hay boletos vendidos.
                </div>
            </div>

            <!-- Métodos de pago -->

            <div
                class="relative overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div
                    class="border-b border-sidebar-border/70 p-5 dark:border-sidebar-border"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <CreditCard
                            class="size-5 text-muted-foreground"
                        />

                        <div>
                            <h2 class="font-semibold">
                                Métodos de pago
                            </h2>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Métodos utilizados durante el periodo.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="payment_methods.length > 0"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full text-left text-sm"
                    >
                        <thead
                            class="border-b border-sidebar-border/70 bg-muted/40 dark:border-sidebar-border"
                        >
                            <tr>
                                <th
                                    class="px-5 py-3 font-semibold"
                                >
                                    Método
                                </th>

                                <th
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Ventas
                                </th>

                                <th
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Monto
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                        >
                            <tr
                                v-for="method in payment_methods"
                                :key="method.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td class="px-5 py-4">
                                    <div
                                        class="font-medium"
                                    >
                                        {{ method.name }}
                                    </div>

                                    <div
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ method.code }}
                                    </div>
                                </td>

                                <td
                                    class="px-5 py-4 text-right"
                                >
                                    {{ method.sales }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right font-medium"
                                >
                                    {{
                                        formatCurrency(
                                            method.amount,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No hay pagos registrados.
                </div>
            </div>
        </div>

        <!-- ===================================================== -->
        <!-- BOLETOS VENDIDOS -->
        <!-- ===================================================== -->

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-lg font-semibold">
                        Boletos vendidos
                    </h2>

                    <p
                        class="text-sm text-muted-foreground"
                    >
                        Comparación de boletos vendidos por tipo.
                    </p>
                </div>

                <div
                    class="text-sm text-muted-foreground"
                >
                    Total: {{ period.tickets }}
                </div>
            </div>

            <div
                v-if="tickets_by_type.length > 0"
            >
                <VueApexCharts
                    type="bar"
                    :height="
                        Math.max(
                            260,
                            tickets_by_type.length * 55,
                        )
                    "
                    :options="ticketChartOptions"
                    :series="ticketChartSeries"
                />
            </div>

            <div
                v-else
                class="flex h-[250px] items-center justify-center text-sm text-muted-foreground"
            >
                No hay boletos vendidos en el periodo seleccionado.
            </div>
        </div>
    </div>
</template>