<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface TicketType {
    id: number;
    name: string;
    price: number | string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    ticketType: TicketType;
}>();

const form = useForm({
    _method: 'put',

    name: props.ticketType.name,
    price: String(props.ticketType.price),
    description: props.ticketType.description ?? '',
    is_active: props.ticketType.is_active,
});

const submit = () => {
    form.post(
        admin.ticketTypes.update(
            props.ticketType.id,
        ).url,
    );
};
</script>

<template>
    <Head title="Editar tipo de boleto" />

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
                        Editar tipo de boleto
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modifica la información y tarifa del tipo de boleto.
                    </p>
                </div>

                <Link
                    :href="
                        admin.ticketTypes.index().url
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <!-- Nombre -->
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
                        placeholder="Ej. Adulto"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Precio -->
                <div class="space-y-2">
                    <label
                        for="price"
                        class="text-sm font-medium"
                    >
                        Precio
                    </label>

                    <div class="relative">
                        <span
                            class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                        >
                            $
                        </span>

                        <input
                            id="price"
                            v-model="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="80.00"
                            class="w-full rounded-lg border border-sidebar-border bg-background py-2.5 pl-8 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />
                    </div>

                    <p
                        v-if="form.errors.price"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.price }}
                    </p>
                </div>

                <!-- Descripción -->
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
                        placeholder="Describe este tipo de boleto..."
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Estado -->
                <div
                    class="flex items-center justify-between rounded-lg border border-sidebar-border p-4"
                >
                    <div>
                        <div class="text-sm font-medium">
                            Estado
                        </div>

                        <div
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Determina si este tipo de boleto puede utilizarse.
                        </div>
                    </div>

                    <label
                        class="relative inline-flex cursor-pointer items-center"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="peer sr-only"
                        />

                        <div
                            class="h-6 w-11 rounded-full bg-muted transition peer-checked:bg-primary peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/20"
                        >
                            <div
                                class="absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"
                            />
                        </div>
                    </label>
                </div>

                <p
                    v-if="form.errors.is_active"
                    class="text-sm text-red-500"
                >
                    {{ form.errors.is_active }}
                </p>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.ticketTypes.index().url
                        "
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
                                : 'Actualizar tipo de boleto'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
