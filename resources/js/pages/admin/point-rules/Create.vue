<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Coins,
    Hash,
    Save,
    Text,
    ToggleLeft,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

const form = useForm({
    type: '',
    name: '',
    points: 10,
    description: '',
    is_active: true,
});

const submit = (): void => {
    form.post(
        admin.pointRules.store().url,
    );
};
</script>

<template>
    <Head title="Nueva regla de puntos" />

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
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Coins class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Nueva regla de puntos
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Define una acción que otorgará o quitará puntos.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.pointRules.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <!-- Información general -->
                <div
                    class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <Coins class="h-4 w-4 text-primary" />

                        <h2 class="text-base font-semibold">
                            Información de la regla
                        </h2>
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Configura la acción, los puntos y la descripción de
                        esta regla.
                    </p>
                </div>

                <!-- Tipo -->
                <div class="space-y-2">
                    <label
                        for="type"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Hash class="h-4 w-4 text-muted-foreground" />
                        Tipo
                    </label>

                    <input
                        id="type"
                        v-model="form.type"
                        type="text"
                        placeholder="ticket_purchase"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Identificador interno de la regla.
                    </p>

                    <p
                        v-if="form.errors.type"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.type }}
                    </p>
                </div>

                <!-- Nombre -->
                <div class="space-y-2">
                    <label
                        for="name"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Text class="h-4 w-4 text-muted-foreground" />
                        Nombre
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Compra de boleto"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Puntos -->
                <div class="space-y-2">
                    <label
                        for="points"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Coins class="h-4 w-4 text-muted-foreground" />
                        Puntos
                    </label>

                    <input
                        id="points"
                        v-model.number="form.points"
                        type="number"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Usa valores negativos para quitar puntos.
                    </p>

                    <p
                        v-if="form.errors.points"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.points }}
                    </p>
                </div>

                <!-- Descripción -->
                <div class="space-y-2">
                    <label
                        for="description"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Text class="h-4 w-4 text-muted-foreground" />
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        placeholder="Puntos otorgados por cada boleto comprado."
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Estado -->
                <div
                    class="flex items-center justify-between rounded-xl border border-sidebar-border bg-muted/20 p-4"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <ToggleLeft class="h-4 w-4" />
                        </div>

                        <div>
                            <p class="text-sm font-medium">
                                Regla activa
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Las reglas activas podrán aplicarse cuando
                                ocurra la acción correspondiente.
                            </p>
                        </div>
                    </div>

                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-sidebar-border text-primary focus:ring-primary/20"
                    />
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
                        :href="admin.pointRules.index().url"
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
                                : 'Guardar regla'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>