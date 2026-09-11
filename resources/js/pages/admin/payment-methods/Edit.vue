<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    paymentMethod: PaymentMethod;
}>();

const form = useForm({
    name: props.paymentMethod.name,
    code: props.paymentMethod.code,
    description: props.paymentMethod.description ?? '',
    is_active: props.paymentMethod.is_active,
    sort_order: props.paymentMethod.sort_order,
});

const submit = () => {
    form.put(
        admin.paymentMethods.update(
            props.paymentMethod.id,
        ).url,
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
            {
                title: 'Editar método de pago',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar método de pago" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Editar método de pago
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Actualiza la información del método de pago.
                    </p>
                </div>

                <Link
                    :href="admin.paymentMethods.index().url"
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <div class="space-y-2">
                    <label
                        for="name"
                        class="text-sm font-medium"
                    >
                        Nombre
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        for="code"
                        class="text-sm font-medium"
                    >
                        Código
                    </label>

                    <input
                        id="code"
                        v-model="form.code"
                        type="text"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        El código debe ser único y solo puede contener letras,
                        números, guiones y guiones bajos.
                    </p>

                    <p
                        v-if="form.errors.code"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.code }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        for="description"
                        class="text-sm font-medium"
                    >
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label
                        for="sort_order"
                        class="text-sm font-medium"
                    >
                        Orden
                    </label>

                    <input
                        id="sort_order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.sort_order"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.sort_order }}
                    </p>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg border border-sidebar-border p-4"
                >
                    <div>
                        <p class="text-sm font-medium">
                            Método activo
                        </p>

                        <p class="text-xs text-muted-foreground">
                            Los métodos inactivos no estarán disponibles para
                            nuevas órdenes.
                        </p>
                    </div>

                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-sidebar-border"
                    />
                </div>

                <p
                    v-if="form.errors.is_active"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.is_active }}
                </p>

                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="admin.paymentMethods.index().url"
                        class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Actualizar método de pago'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
