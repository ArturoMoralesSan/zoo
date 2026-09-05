<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Editar categoría
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Modifica la información de la categoría.
                    </p>
                </div>

                <Link
                    :href="admin.speciesCategories.index().url"
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
                <div class="space-y-2">
                    <label class="text-sm font-medium">
                        Estado
                    </label>

                    <div
                        class="flex items-center justify-between rounded-lg border border-sidebar-border p-4"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                Categoría activa
                            </p>

                            <p class="text-xs text-muted-foreground">
                                Determina si la categoría está disponible para su uso.
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
                </div>

                <!-- Información -->
                <div
                    class="rounded-lg border border-sidebar-border bg-muted/30 p-4"
                >
                    <div class="text-xs text-muted-foreground">
                        ID de la categoría
                    </div>

                    <div class="mt-1 text-sm font-medium">
                        {{ category.id }}
                    </div>
                </div>

                <!-- Botones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="admin.speciesCategories.index().url"
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
                                : 'Guardar cambios'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>