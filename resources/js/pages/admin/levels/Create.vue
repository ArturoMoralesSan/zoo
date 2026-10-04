<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    Info,
    Save,
    Sparkles,
    Tag,
    X,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

const form = useForm({
    name: '',
    min_points: 0,
    max_points: null as number | null,
    description: '',
});

const submit = () => {
    form.post(admin.levels.store().url);
};
</script>

<template>
    <Head title="Nuevo nivel" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <!-- Encabezado -->
        <div
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Award class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Nuevo nivel
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Crea un nuevo nivel para los visitantes.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.levels.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <form
                @submit.prevent="submit"
                class="space-y-6"
            >
                <!-- Información general -->
                <div>
                    <div
                        class="flex items-center gap-3 border-b border-sidebar-border/70 pb-6 dark:border-sidebar-border"
                    >
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Tag class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Información del nivel
                            </h2>

                            <p class="text-sm text-muted-foreground">
                                Define el nombre y la descripción del nivel.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-6">
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
                                placeholder="Ej. Bronce"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.name"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.name }}
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
                                placeholder="Describe este nivel..."
                                class="w-full resize-none rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            ></textarea>

                            <p
                                v-if="form.errors.description"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Rango de puntos -->
                <div
                    class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-5 dark:border-sidebar-border"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Award class="h-5 w-5" />
                        </div>

                        <div>
                            <h2 class="text-base font-semibold">
                                Rango de puntos
                            </h2>

                            <p class="text-sm text-muted-foreground">
                                Define los puntos necesarios para pertenecer a este nivel.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <!-- Mínimos -->
                        <div class="space-y-2">
                            <label
                                for="min_points"
                                class="text-sm font-medium"
                            >
                                Puntos mínimos
                            </label>

                            <input
                                id="min_points"
                                v-model.number="form.min_points"
                                type="number"
                                min="0"
                                placeholder="0"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.min_points"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.min_points }}
                            </p>
                        </div>

                        <!-- Máximos -->
                        <div class="space-y-2">
                            <label
                                for="max_points"
                                class="text-sm font-medium"
                            >
                                Puntos máximos
                            </label>

                            <input
                                id="max_points"
                                v-model.number="form.max_points"
                                type="number"
                                min="0"
                                placeholder="Ej. 499"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <div class="flex items-start gap-2 pt-1">
                                <Info
                                    class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                                />

                                <p class="text-xs text-muted-foreground">
                                    Déjalo vacío si el nivel no tiene límite.
                                </p>
                            </div>

                            <p
                                v-if="form.errors.max_points"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.max_points }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.levels.index().url"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        <X class="h-4 w-4" />
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Sparkles
                            v-if="form.processing"
                            class="h-4 w-4 animate-pulse"
                        />

                        <Save
                            v-else
                            class="h-4 w-4"
                        />

                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Guardar nivel'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>