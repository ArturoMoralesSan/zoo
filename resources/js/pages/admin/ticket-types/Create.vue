<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Info,
    Save,
    Ticket,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

const form = useForm({
    name: '',
    price: '',
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(
        admin.ticketTypes.store().url,
    );
};
</script>

<template>
    <Head title="Nuevo tipo de boleto" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Ticket class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Nuevo tipo de boleto
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Registra un nuevo tipo de boleto para el zoológico.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.ticketTypes.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <form
                class="flex flex-col"
                @submit.prevent="submit"
            >
                <div class="space-y-6 p-6">
                    <!-- Información -->
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <Info class="h-5 w-5" />
                            </div>

                            <div>
                                <h2 class="text-base font-semibold">
                                    Información del boleto
                                </h2>

                                <p class="mt-1 text-sm text-muted-foreground">
                                    Define los datos principales y el precio
                                    del tipo de boleto.
                                </p>
                            </div>
                        </div>
                    </div>

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
                            class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
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
                        class="flex items-center justify-between rounded-xl border border-sidebar-border bg-muted/10 p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <Check class="h-4 w-4" />
                            </div>

                            <div>
                                <div class="text-sm font-medium">
                                    Estado
                                </div>

                                <div
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Determina si este tipo de boleto puede
                                    utilizarse.
                                </div>
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
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border/70 px-6 py-5 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-end"
                >
                    <Link
                        :href="admin.ticketTypes.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Save class="h-4 w-4" />

                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar tipo de boleto'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>