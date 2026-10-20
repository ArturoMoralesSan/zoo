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
    ArrowUpRight,
    BarChart3,
    ChartNoAxesCombined,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
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
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (date: string): string => {
    const parsed = new Date(`${date}T00:00:00`);

    return parsed.toLocaleDateString('es-MX', {
        day: '2-digit',
        month: 'short',
    });
};

const salesCategories = computed(() => {
    return props.sales_by_day.map((item) => formatDate(item.date));
});

const salesChartSeries = computed(() => [
    {
        name: 'Ventas',
        type: 'column',
        data: props.sales_by_day.map((item) => item.sales),
    },
    {
        name: 'Ingresos',
        type: 'line',
        data: props.sales_by_day.map((item) => item.amount),
    },
]);

const salesChartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 360,
        toolbar: { show: false },
        fontFamily: 'inherit',
        zoom: { enabled: false },
    },
    colors: ['#A9CBB5', '#187744'],
    stroke: {
        width: [0, 3],
        curve: 'smooth',
    },
    plotOptions: {
        bar: {
            columnWidth: '42%',
            borderRadius: 5,
        },
    },
    xaxis: {
        categories: salesCategories.value,
        labels: {
            style: {
                fontSize: '12px',
                colors: '#718477',
            },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: [
        {
            title: {
                text: 'Ventas',
                style: { color: '#718477', fontWeight: 500 },
            },
            labels: {
                style: { colors: '#718477' },
                formatter: (value: number) => Math.round(value).toString(),
            },
        },
        {
            opposite: true,
            title: {
                text: 'Ingresos',
                style: { color: '#718477', fontWeight: 500 },
            },
            labels: {
                style: { colors: '#718477' },
                formatter: (value: number) => formatCurrency(value),
            },
        },
    ],
    tooltip: {
        shared: true,
        intersect: false,
        y: {
            formatter: (
                value: number,
                { seriesIndex }: { seriesIndex: number },
            ) => {
                return seriesIndex === 0
                    ? `${Math.round(value)} ventas`
                    : formatCurrency(value);
            },
        },
    },
    legend: {
        position: 'top',
        horizontalAlign: 'right',
        fontSize: '12px',
        labels: { colors: '#435D4C' },
        markers: { radius: 10 },
    },
    grid: {
        borderColor: '#E5ECE7',
        strokeDashArray: 4,
    },
    dataLabels: { enabled: false },
}));

const donationCategories = computed(() => {
    return props.donations_by_day.map((item) => formatDate(item.date));
});

const donationsChartSeries = computed(() => [
    {
        name: 'Donaciones',
        data: props.donations_by_day.map((item) => item.amount),
    },
]);

const donationsChartOptions = computed(() => ({
    chart: {
        type: 'area',
        height: 320,
        toolbar: { show: false },
        fontFamily: 'inherit',
        zoom: { enabled: false },
    },
    colors: ['#187744'],
    stroke: {
        curve: 'smooth',
        width: 3,
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 0,
            opacityFrom: 0.28,
            opacityTo: 0.02,
            stops: [0, 95, 100],
        },
    },
    markers: {
        size: 3,
        strokeWidth: 0,
        hover: { size: 5 },
    },
    xaxis: {
        categories: donationCategories.value,
        labels: {
            style: { fontSize: '12px', colors: '#718477' },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#718477' },
            formatter: (value: number) => formatCurrency(value),
        },
    },
    tooltip: {
        y: {
            formatter: (
                value: number,
                { dataPointIndex }: { dataPointIndex: number },
            ) => {
                const donation = props.donations_by_day[dataPointIndex];

                if (!donation) {
                    return formatCurrency(value);
                }

                return `${formatCurrency(value)} · ${donation.donations} donación${donation.donations === 1 ? '' : 'es'}`;
            },
        },
    },
    legend: { show: false },
    grid: {
        borderColor: '#E5ECE7',
        strokeDashArray: 4,
    },
    dataLabels: { enabled: false },
}));

const ticketChartSeries = computed(() => [
    {
        name: 'Boletos',
        data: props.tickets_by_type.map((item) => item.quantity),
    },
]);

const ticketChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        height: Math.max(260, props.tickets_by_type.length * 55),
        toolbar: { show: false },
        fontFamily: 'inherit',
    },
    colors: ['#187744'],
    plotOptions: {
        bar: {
            horizontal: true,
            borderRadius: 5,
            barHeight: '52%',
            distributed: false,
        },
    },
    xaxis: {
        categories: props.tickets_by_type.map((item) => item.name),
        labels: {
            style: { colors: '#718477' },
        },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#435D4C' },
            maxWidth: 180,
        },
    },
    tooltip: {
        y: {
            formatter: (value: number) => `${Math.round(value)} boletos`,
        },
    },
    legend: { show: false },
    grid: {
        borderColor: '#E5ECE7',
        strokeDashArray: 4,
    },
    dataLabels: {
        enabled: true,
        formatter: (value: number) => Math.round(value).toString(),
        style: {
            fontSize: '11px',
            colors: ['#FFFFFF'],
        },
    },
}));

const kpis = computed(() => [
    {
        label: 'Ventas de hoy',
        value: props.today.sales.toLocaleString('es-MX'),
        detail: 'Operaciones registradas',
        icon: Receipt,
        tone: 'green',
    },
    {
        label: 'Ingresos de hoy',
        value: formatCurrency(props.today.income),
        detail: 'Ventas pagadas',
        icon: CircleDollarSign,
        tone: 'emerald',
    },
    {
        label: 'Boletos vendidos',
        value: props.today.tickets.toLocaleString('es-MX'),
        detail: 'Boletos emitidos hoy',
        icon: Ticket,
        tone: 'blue',
    },
    {
        label: 'Ticket promedio',
        value: formatCurrency(props.today.average_ticket),
        detail: 'Ingreso promedio por venta',
        icon: CreditCard,
        tone: 'violet',
    },
    {
        label: 'Donaciones de hoy',
        value: formatCurrency(props.today.donations_amount),
        detail: `${props.today.donations} donación${props.today.donations === 1 ? '' : 'es'}`,
        icon: Heart,
        tone: 'rose',
    },
]);
</script>

<template>
    <Head title="Panel administrativo" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado y filtros -->
        <div
            class="flex flex-col gap-5 rounded-2xl border border-[#DCE7DF] bg-white p-5 sm:p-6 xl:flex-row xl:items-end xl:justify-between"
        >
            <PageHeader
                title="Panel administrativo"
                description="Resumen de taquilla, ingresos, boletos y donaciones del zoológico."
                section="Resumen general"
                :icon="ChartNoAxesCombined"
            />

            <form
                class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2 xl:max-w-[520px] xl:grid-cols-[1fr_1fr_auto]"
                @submit.prevent="submitFilter"
            >
                <div class="form-field">
                    <label for="from" class="form-label">
                        Fecha inicial
                    </label>

                    <div class="relative">
                        <CalendarDays
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                        />

                        <input
                            id="from"
                            v-model="from"
                            type="date"
                            class="form-input pl-9"
                        />
                    </div>
                </div>

                <div class="form-field">
                    <label for="to" class="form-label">
                        Fecha final
                    </label>

                    <div class="relative">
                        <CalendarDays
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                        />

                        <input
                            id="to"
                            v-model="to"
                            type="date"
                            class="form-input pl-9"
                        />
                    </div>
                </div>

                <div class="flex items-end">
                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="TrendingUp"
                    >
                        Filtrar
                    </AdminButton>
                </div>
            </form>
        </div>

        <!-- Indicadores de hoy -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 px-1">
                <div>
                    <h2 class="text-base font-semibold text-[#244334]">
                        Actividad de hoy
                    </h2>

                    <p class="mt-1 text-sm text-[#718477]">
                        Indicadores principales de taquilla y donaciones.
                    </p>
                </div>

                <span
                    class="inline-flex items-center gap-2 rounded-full border border-[#DCE7DF] bg-white px-3 py-1.5 text-xs font-medium text-[#435D4C]"
                >
                    <span class="h-2 w-2 rounded-full bg-[#187744]" />
                    Resumen diario
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    class="group rounded-2xl border border-[#DCE7DF] bg-white p-5 transition-colors hover:border-[#BFCFC6]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#718477]">
                                {{ kpi.label }}
                            </p>

                            <p class="mt-3 break-words text-2xl font-semibold tracking-tight text-[#244334]">
                                {{ kpi.value }}
                            </p>
                        </div>

                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="{
                                'bg-[#EAF3ED] text-[#187744]': kpi.tone === 'green' || kpi.tone === 'emerald',
                                'bg-[#EAF2FA] text-[#376A9A]': kpi.tone === 'blue',
                                'bg-[#F0EDFA] text-[#6B59A3]': kpi.tone === 'violet',
                                'bg-[#FFF0F0] text-[#B65361]': kpi.tone === 'rose',
                            }"
                        >
                            <component :is="kpi.icon" class="h-5 w-5" />
                        </div>
                    </div>

                    <p class="mt-3 text-xs leading-5 text-[#84958A]">
                        {{ kpi.detail }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Ventas del periodo -->
        <AdminCard
            title="Ventas del periodo"
            description="Comportamiento diario de las ventas y los ingresos."
            :icon="TrendingUp"
        >
            <template #default>
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] px-4 py-3">
                        <p class="text-xs text-[#718477]">
                            Ventas registradas
                        </p>

                        <p class="mt-1 text-lg font-semibold text-[#244334]">
                            {{ period.sales.toLocaleString('es-MX') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] px-4 py-3">
                        <p class="text-xs text-[#718477]">
                            Ingresos del periodo
                        </p>

                        <p class="mt-1 text-lg font-semibold text-[#187744]">
                            {{ formatCurrency(period.income) }}
                        </p>
                    </div>
                </div>

                <div v-if="sales_by_day.length > 0" class="min-w-0">
                    <VueApexCharts
                        type="line"
                        height="360"
                        :options="salesChartOptions"
                        :series="salesChartSeries"
                    />
                </div>

                <div
                    v-else
                    class="flex min-h-[260px] flex-col items-center justify-center rounded-xl border border-dashed border-[#DCE7DF] bg-[#F8FBF9] px-4 text-center"
                >
                    <BarChart3 class="h-8 w-8 text-[#9AAC9F]" />

                    <p class="mt-3 text-sm font-medium text-[#435D4C]">
                        No hay ventas registradas
                    </p>

                    <p class="mt-1 text-xs text-[#84958A]">
                        Prueba con otro periodo para consultar resultados.
                    </p>
                </div>
            </template>
        </AdminCard>

        <!-- Donaciones del periodo -->
        <AdminCard
            title="Donaciones del periodo"
            description="Evolución de los importes recibidos por donaciones."
            :icon="Heart"
        >
            <template #default>
                <div class="mb-4 flex flex-wrap items-center gap-3">
                    <div class="rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] px-4 py-3">
                        <p class="text-xs text-[#718477]">
                            Donaciones recibidas
                        </p>

                        <p class="mt-1 text-lg font-semibold text-[#244334]">
                            {{ period.donations.toLocaleString('es-MX') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] px-4 py-3">
                        <p class="text-xs text-[#718477]">
                            Importe total
                        </p>

                        <p class="mt-1 text-lg font-semibold text-[#187744]">
                            {{ formatCurrency(period.donations_amount) }}
                        </p>
                    </div>
                </div>

                <div v-if="donations_by_day.length > 0" class="min-w-0">
                    <VueApexCharts
                        type="area"
                        height="320"
                        :options="donationsChartOptions"
                        :series="donationsChartSeries"
                    />
                </div>

                <div
                    v-else
                    class="flex min-h-[240px] flex-col items-center justify-center rounded-xl border border-dashed border-[#DCE7DF] bg-[#F8FBF9] px-4 text-center"
                >
                    <Heart class="h-8 w-8 text-[#9AAC9F]" />

                    <p class="mt-3 text-sm font-medium text-[#435D4C]">
                        No hay donaciones registradas
                    </p>

                    <p class="mt-1 text-xs text-[#84958A]">
                        No se encontraron donaciones para este periodo.
                    </p>
                </div>
            </template>
        </AdminCard>

        <!-- Tablas de resumen -->
        <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <!-- Tipos de boleto -->
            <div class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white">
                <div class="flex items-center gap-3 border-b border-[#E5ECE7] p-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]">
                        <Ticket class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-[#244334]">
                            Boletos más vendidos
                        </h2>

                        <p class="mt-1 text-xs text-[#718477]">
                            Distribución por tipo de boleto.
                        </p>
                    </div>
                </div>

                <div v-if="tickets_by_type.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[#F8FBF9] text-xs uppercase tracking-wide text-[#718477]">
                            <tr>
                                <th class="px-5 py-3 font-medium">
                                    Boleto
                                </th>

                                <th class="px-5 py-3 text-right font-medium">
                                    Cantidad
                                </th>

                                <th class="px-5 py-3 text-right font-medium">
                                    Ingresos
                                </th>

                                <th class="px-5 py-3 text-right font-medium">
                                    %
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#E5ECE7]">
                            <tr
                                v-for="ticket in tickets_by_type"
                                :key="ticket.id"
                                class="transition-colors hover:bg-[#F8FBF9]"
                            >
                                <td class="px-5 py-4 font-medium text-[#244334]">
                                    {{ ticket.name }}
                                </td>

                                <td class="px-5 py-4 text-right tabular-nums text-[#435D4C]">
                                    {{ ticket.quantity.toLocaleString('es-MX') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right font-medium tabular-nums text-[#244334]">
                                    {{ formatCurrency(ticket.amount) }}
                                </td>

                                <td class="px-5 py-4 text-right tabular-nums text-[#718477]">
                                    {{ ticket.percentage }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex flex-col items-center px-5 py-12 text-center">
                    <Ticket class="h-8 w-8 text-[#9AAC9F]" />

                    <p class="mt-3 text-sm font-medium text-[#435D4C]">
                        Sin boletos vendidos
                    </p>

                    <p class="mt-1 text-xs text-[#84958A]">
                        No hay datos disponibles para mostrar.
                    </p>
                </div>
            </div>

            <!-- Métodos de pago -->
            <div class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white">
                <div class="flex items-center gap-3 border-b border-[#E5ECE7] p-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]">
                        <Wallet class="h-5 w-5" />
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-[#244334]">
                            Métodos de pago
                        </h2>

                        <p class="mt-1 text-xs text-[#718477]">
                            Métodos utilizados durante el periodo.
                        </p>
                    </div>
                </div>

                <div v-if="payment_methods.length > 0" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-[#F8FBF9] text-xs uppercase tracking-wide text-[#718477]">
                            <tr>
                                <th class="px-5 py-3 font-medium">
                                    Método
                                </th>

                                <th class="px-5 py-3 text-right font-medium">
                                    Ventas
                                </th>

                                <th class="px-5 py-3 text-right font-medium">
                                    Monto
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#E5ECE7]">
                            <tr
                                v-for="method in payment_methods"
                                :key="method.id"
                                class="transition-colors hover:bg-[#F8FBF9]"
                            >
                                <td class="px-5 py-4">
                                    <p class="font-medium text-[#244334]">
                                        {{ method.name }}
                                    </p>

                                    <p class="mt-1 text-xs text-[#84958A]">
                                        {{ method.code }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-right tabular-nums text-[#435D4C]">
                                    {{ method.sales.toLocaleString('es-MX') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right font-medium tabular-nums text-[#244334]">
                                    {{ formatCurrency(method.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="flex flex-col items-center px-5 py-12 text-center">
                    <CreditCard class="h-8 w-8 text-[#9AAC9F]" />

                    <p class="mt-3 text-sm font-medium text-[#435D4C]">
                        Sin pagos registrados
                    </p>

                    <p class="mt-1 text-xs text-[#84958A]">
                        No hay datos disponibles para mostrar.
                    </p>
                </div>
            </div>
        </section>

        <!-- Comparativa de boletos -->
        <AdminCard
            title="Boletos vendidos"
            description="Comparación de cantidades vendidas por tipo de boleto."
            :icon="BarChart3"
        >
            <template #default>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] px-4 py-3">
                    <div>
                        <p class="text-xs text-[#718477]">
                            Total de boletos del periodo
                        </p>

                        <p class="mt-1 text-xl font-semibold text-[#244334]">
                            {{ period.tickets.toLocaleString('es-MX') }}
                        </p>
                    </div>

                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]">
                        <ArrowUpRight class="h-5 w-5" />
                    </div>
                </div>

                <div v-if="tickets_by_type.length > 0" class="min-w-0 overflow-x-auto">
                    <VueApexCharts
                        type="bar"
                        :height="Math.max(260, tickets_by_type.length * 55)"
                        :options="ticketChartOptions"
                        :series="ticketChartSeries"
                    />
                </div>

                <div
                    v-else
                    class="flex min-h-[220px] flex-col items-center justify-center rounded-xl border border-dashed border-[#DCE7DF] bg-[#F8FBF9] px-4 text-center"
                >
                    <BarChart3 class="h-8 w-8 text-[#9AAC9F]" />

                    <p class="mt-3 text-sm font-medium text-[#435D4C]">
                        Sin datos para comparar
                    </p>

                    <p class="mt-1 text-xs text-[#84958A]">
                        No hay boletos vendidos en el periodo seleccionado.
                    </p>
                </div>
            </template>
        </AdminCard>
    </div>
</template>