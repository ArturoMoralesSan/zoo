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
                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
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
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2 text-sm font-medium transition hover:bg-accent"
                >
                    Cancelar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative flex-1 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                @submit.prevent="submit"
                class="max-w-2xl space-y-6"
            >
                <!-- Nombre -->
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Descripción -->
                <div>
                    <label
                        for="description"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <!-- Estado -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Estado
                    </label>

                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-sidebar-border"
                        />

                        <span class="text-sm">
                            Categoría activa
                        </span>
                    </label>

                    <p
                        v-if="form.errors.is_active"
                        class="mt-2 text-sm text-red-500"
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
                <div class="flex items-center gap-3 pt-2">
                    <Link
                        :href="admin.speciesCategories.index().url"
                        class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
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