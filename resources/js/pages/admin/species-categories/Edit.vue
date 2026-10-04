<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Info,
    Leaf,
    Save,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

interface Category {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    category: Category;
}>();

const form = useForm({
    name: props.category.name,
    description: props.category.description ?? '',
    is_active: props.category.is_active,
});

const submit = () => {
    form.put(
        admin.speciesCategories.update(
            props.category.id,
        ).url,
    );
};
</script>

<template>
    <Head title="Editar categoría" />

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
                        <Leaf class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar categoría
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica la información de la categoría.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.speciesCategories.index().url"
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
                <div class="p-6">
                    <!-- Información -->
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-2">
                            <Info class="h-5 w-5 text-primary" />

                            <h2 class="text-base font-semibold">
                                Información de la categoría
                            </h2>
                        </div>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica el nombre y la descripción de la categoría.
                        </p>
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
                                placeholder="Ej. Mamíferos"
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
                                placeholder="Descripción de la categoría..."
                                class="w-full resize-y rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            ></textarea>

                            <p
                                v-if="form.errors.description"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <!-- Estado -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                Estado
                            </label>

                            <div
                                class="flex items-center justify-between rounded-xl border border-sidebar-border bg-muted/20 p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <Check class="h-4 w-4" />
                                    </div>

                                    <div>
                                        <p class="text-sm font-medium">
                                            Categoría activa
                                        </p>

                                        <p class="text-xs text-muted-foreground">
                                            Determina si la categoría está disponible para su uso.
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
                        </div>

                        <!-- Información -->
                        <div
                            class="rounded-xl border border-sidebar-border bg-muted/10 p-5"
                        >
                            <div class="flex items-center gap-2">
                                <Info class="h-4 w-4 text-primary" />

                                <p class="text-sm font-medium">
                                    Información del registro
                                </p>
                            </div>

                            <div class="mt-3">
                                <div class="text-xs text-muted-foreground">
                                    ID de la categoría
                                </div>

                                <div class="mt-1 text-sm font-medium">
                                    {{ category.id }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border/70 px-6 py-5 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-end"
                >
                    <Link
                        :href="admin.speciesCategories.index().url"
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
