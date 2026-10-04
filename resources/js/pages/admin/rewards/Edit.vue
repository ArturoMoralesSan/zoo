<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Coins,
    FileText,
    Image,
    Package,
    Save,
    ToggleLeft,
    Type,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

interface Reward {
    id: number;
    name: string;
    description: string | null;
    points: number;
    stock: number;
    image: string | null;
    is_active: boolean;
}

const props = defineProps<{
    reward: Reward;
}>();

const form = useForm({
    name: props.reward.name,
    points: props.reward.points,
    stock: props.reward.stock,
    description: props.reward.description ?? '',
    image: props.reward.image ?? '',
    is_active: props.reward.is_active,
});

const submit = (): void => {
    form.put(
        admin.rewards.update(props.reward.id).url,
    );
};
</script>

<template>
    <Head title="Editar recompensa" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Header -->
        <div class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <Coins class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar recompensa
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica la configuración de esta recompensa.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.rewards.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border">
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <!-- Información general -->
                <div class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border">
                    <div class="flex items-center gap-2">
                        <Coins class="h-4 w-4 text-primary" />

                        <h2 class="text-base font-semibold">
                            Información de la recompensa
                        </h2>
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Actualiza los datos principales de la recompensa y las condiciones para canjearla.
                    </p>
                </div>

                <!-- Nombre -->
                <div class="space-y-2">
                    <label
                        for="name"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Type class="h-4 w-4 text-muted-foreground" />
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

                <!-- Puntos -->
                <div class="space-y-2">
                    <label
                        for="points"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Coins class="h-4 w-4 text-muted-foreground" />
                        Puntos necesarios
                    </label>

                    <input
                        id="points"
                        v-model.number="form.points"
                        type="number"
                        min="1"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Cantidad de puntos necesarios para canjear la recompensa.
                    </p>

                    <p
                        v-if="form.errors.points"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.points }}
                    </p>
                </div>

                <!-- Stock -->
                <div class="space-y-2">
                    <label
                        for="stock"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Package class="h-4 w-4 text-muted-foreground" />
                        Stock
                    </label>

                    <input
                        id="stock"
                        v-model.number="form.stock"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Cantidad disponible para canje.
                    </p>

                    <p
                        v-if="form.errors.stock"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.stock }}
                    </p>
                </div>

                <!-- Descripción -->
                <div class="space-y-2">
                    <label
                        for="description"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <FileText class="h-4 w-4 text-muted-foreground" />
                        Descripción
                    </label>

                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Imagen -->
                <div class="space-y-2">
                    <label
                        for="image"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Image class="h-4 w-4 text-muted-foreground" />
                        Imagen
                    </label>

                    <input
                        id="image"
                        v-model="form.image"
                        type="text"
                        placeholder="rewards/entrada-gratis.jpg"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Ruta de la imagen asociada a la recompensa.
                    </p>

                    <p
                        v-if="form.errors.image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.image }}
                    </p>
                </div>

                <!-- Estado -->
                <div class="flex items-center justify-between rounded-xl border border-sidebar-border bg-muted/20 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ToggleLeft class="h-4 w-4" />
                        </div>

                        <div>
                            <p class="text-sm font-medium">
                                Recompensa activa
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Las recompensas activas podrán ser seleccionadas para realizar canjes.
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
                <div class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end">
                    <Link
                        :href="admin.rewards.index().url"
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
                                : 'Guardar cambios'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>