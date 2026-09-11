<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import { ref } from 'vue';

import admin from '@/routes/admin';

interface User {
    id: number;
    name: string;
    email: string;
    qr_token?: string | null;
}

interface Seller {
    id: number;
    name: string;
    email?: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface Donation {
    id: number;
    user: User | null;
    seller: Seller | null;
    payment_method: PaymentMethod | null;
    amount: number;
    reference: string | null;
    status: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface DonationsPagination {
    data: Donation[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface Filters {
    search?: string;
}

const props = defineProps<{
    donations?: DonationsPagination;
    donation?: Donation;
    filters?: Filters;
}>();

const search = ref(
    props.filters?.search ?? '',
);

const hasDonation = Boolean(
    props.donation,
);

const submitSearch = (): void => {
    router.get(
        admin.donations.index().url,
        {
            search:
                search.value.trim() ||
                undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
};

const formatDate = (
    date: string | null,
): string => {
    if (!date) {
        return '-';
    }

    return new Date(
        date,
    ).toLocaleString(
        'es-MX',
        {
            dateStyle: 'short',
            timeStyle: 'short',
        },
    );
};

const formatCurrency = (
    amount: number,
): string => {
    return new Intl.NumberFormat(
        'es-MX',
        {
            style: 'currency',
            currency: 'MXN',
        },
    ).format(amount);
};

const statusLabel = (
    status: string,
): string => {
    const labels: Record<
        string,
        string
    > = {
        completed: 'Completada',
        pending: 'Pendiente',
        cancelled: 'Cancelada',
    };

    return (
        labels[status] ??
        status
    );
};
</script>

<template>
    <Head title="Donaciones" />

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
                    <h1
                        class="text-2xl font-semibold"
                    >
                        Donaciones
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Consulta el historial de
                        donaciones realizadas por
                        los usuarios.
                    </p>
                </div>

                <Link
                    :href="
                        admin.donations
                            .create()
                            .url
                    "
                    class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                >
                    Nueva donación
                </Link>
            </div>
        </div>

        <!-- Detalle de donación -->
        <div
            v-if="hasDonation && donation"
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div>
                    <div
                        class="flex flex-wrap items-center gap-2"
                    >
                        <span
                            class="rounded-full border border-sidebar-border px-2.5 py-1 text-xs font-medium"
                        >
                            DON-{{
                                String(
                                    donation.id,
                                ).padStart(
                                    4,
                                    '0',
                                )
                            }}
                        </span>

                        <span
                            v-if="
                                donation.status ===
                                'completed'
                            "
                            class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                        >
                            Completada
                        </span>

                        <span
                            v-else-if="
                                donation.status ===
                                'cancelled'
                            "
                            class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                        >
                            Cancelada
                        </span>

                        <span
                            v-else
                            class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {{
                                statusLabel(
                                    donation.status,
                                )
                            }}
                        </span>
                    </div>

                    <h2
                        class="mt-3 text-xl font-semibold"
                    >
                        Donación registrada
                    </h2>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Detalle de la donación
                        seleccionada.
                    </p>
                </div>

                <Link
                    :href="
                        admin.donations
                            .index()
                            .url
                    "
                    class="rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Volver al listado
                </Link>
            </div>

            <div
                class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Usuario
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            donation.user
                                ?.name ??
                            '—'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{
                            donation.user
                                ?.email ??
                            'Sin correo'
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Monto
                    </p>

                    <p
                        class="mt-1 text-lg font-semibold"
                    >
                        {{
                            formatCurrency(
                                donation.amount,
                            )
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Método de pago
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            donation
                                .payment_method
                                ?.name ??
                            '—'
                        }}
                    </p>

                    <p
                        v-if="
                            donation
                                .payment_method
                                ?.code
                        "
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{
                            donation
                                .payment_method
                                .code
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Vendedor
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            donation.seller
                                ?.name ??
                            '—'
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Referencia
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            donation.reference ??
                            'Sin referencia'
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs text-muted-foreground"
                    >
                        Fecha
                    </p>

                    <p
                        class="mt-1 text-sm font-medium"
                    >
                        {{
                            formatDate(
                                donation.created_at,
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Listado -->
        <div
            v-if="donations"
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <!-- Buscador -->
            <div
                class="flex flex-col gap-3 border-b border-sidebar-border/70 p-4 md:flex-row md:items-center md:justify-between dark:border-sidebar-border"
            >
                <form
                    class="flex w-full gap-2 md:max-w-md"
                    @submit.prevent="
                        submitSearch
                    "
                >
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Buscar donación..."
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <button
                        type="submit"
                        class="rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                    >
                        Buscar
                    </button>
                </form>

                <div
                    class="text-sm text-muted-foreground"
                >
                    {{ donations.total }}

                    {{
                        donations.total ===
                        1
                            ? 'donación'
                            : 'donaciones'
                    }}
                </div>
            </div>

            <!-- Tabla -->
            <div
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
                                class="px-6 py-4 font-semibold"
                            >
                                ID
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Usuario
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Monto
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Método de pago
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Vendedor
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Estado
                            </th>

                            <th
                                class="px-6 py-4 font-semibold"
                            >
                                Fecha
                            </th>

                            <th
                                class="px-6 py-4 text-center font-semibold"
                            >
                                Acción
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-sidebar-border/70 dark:divide-sidebar-border"
                    >
                        <tr
                            v-for="
                                donation in donations.data
                            "
                            :key="
                                donation.id
                            "
                            class="transition hover:bg-muted/30"
                        >
                            <!-- ID -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    DON-{{
                                        String(
                                            donation.id,
                                        ).padStart(
                                            4,
                                            '0',
                                        )
                                    }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    ID:
                                    {{
                                        donation.id
                                    }}
                                </div>
                            </td>

                            <!-- Usuario -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        donation
                                            .user
                                            ?.name ??
                                        '—'
                                    }}
                                </div>

                                <div
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        donation
                                            .user
                                            ?.email ??
                                        'Sin correo'
                                    }}
                                </div>
                            </td>

                            <!-- Monto -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    class="font-semibold"
                                >
                                    {{
                                        formatCurrency(
                                            donation.amount,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Método -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        donation
                                            .payment_method
                                            ?.name ??
                                        '—'
                                    }}
                                </div>

                                <div
                                    v-if="
                                        donation
                                            .payment_method
                                            ?.code
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        donation
                                            .payment_method
                                            .code
                                    }}
                                </div>
                            </td>

                            <!-- Vendedor -->
                            <td
                                class="px-6 py-4"
                            >
                                <div
                                    class="font-medium"
                                >
                                    {{
                                        donation
                                            .seller
                                            ?.name ??
                                        '—'
                                    }}
                                </div>
                            </td>

                            <!-- Estado -->
                            <td
                                class="px-6 py-4"
                            >
                                <span
                                    v-if="
                                        donation.status ===
                                        'completed'
                                    "
                                    class="rounded-full border border-green-500/30 bg-green-500/10 px-2.5 py-1 text-xs font-medium text-green-600 dark:text-green-400"
                                >
                                    {{
                                        statusLabel(
                                            donation.status,
                                        )
                                    }}
                                </span>

                                <span
                                    v-else-if="
                                        donation.status ===
                                        'cancelled'
                                    "
                                    class="rounded-full border border-red-500/30 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-600 dark:text-red-400"
                                >
                                    {{
                                        statusLabel(
                                            donation.status,
                                        )
                                    }}
                                </span>

                                <span
                                    v-else
                                    class="rounded-full border border-sidebar-border bg-muted/40 px-2.5 py-1 text-xs font-medium text-muted-foreground"
                                >
                                    {{
                                        statusLabel(
                                            donation.status,
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- Fecha -->
                            <td
                                class="px-6 py-4"
                            >
                                {{
                                    formatDate(
                                        donation.created_at,
                                    )
                                }}
                            </td>

                            <!-- Acción -->
                            <td
                                class="px-6 py-4 text-center"
                            >
                                <Link
                                    :href="
                                        admin
                                            .donations
                                            .show(
                                                donation.id,
                                            )
                                            .url
                                    "
                                    class="text-sm font-medium text-primary hover:underline"
                                >
                                    Ver
                                </Link>
                            </td>
                        </tr>

                        <!-- Sin resultados -->
                        <tr
                            v-if="
                                donations.data
                                    .length ===
                                0
                            "
                        >
                            <td
                                colspan="8"
                                class="px-6 py-12 text-center text-sm text-muted-foreground"
                            >
                                No se encontraron
                                donaciones.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                v-if="
                    donations.last_page >
                    1
                "
                class="flex flex-wrap items-center justify-center gap-1 border-t border-sidebar-border/70 p-4 dark:border-sidebar-border"
            >
                <template
                    v-for="(
                        link, index
                    ) in donations.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-state
                        preserve-scroll
                        class="rounded-lg border px-3 py-2 text-sm transition"
                        :class="
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-sidebar-border hover:bg-accent'
                        "
                        v-html="link.label"
                    />

                    <span
                        v-else
                        class="rounded-lg border border-sidebar-border px-3 py-2 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
