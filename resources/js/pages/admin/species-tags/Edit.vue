<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface Tag {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
}

const props = defineProps<{
    tag: Tag;
}>();

const form = useForm({
    name: props.tag.name,
    is_active: props.tag.is_active,
});

const submit = () => {
    form.put(
        admin.speciesTags.update(props.tag.id).url
    );
};
</script>

<template>
    <Head title="Editar etiqueta" />

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
                        Editar etiqueta
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Modifica la información de la etiqueta.
                    </p>
                </div>

                <Link
                    :href="admin.speciesTags.index().url"
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
                class="space-y-8"
            >
                <!-- Nombre -->
                <div class="max-w-2xl">
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
                    >
                        Nombre de la etiqueta
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="mt-2 text-xs text-muted-foreground">
                        El slug se actualizará automáticamente.
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Slug -->
                <div class="max-w-2xl">
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Slug actual
                    </label>

                    <div
                        class="rounded-lg border border-sidebar-border bg-muted/40 px-4 py-2.5 text-sm text-muted-foreground"
                    >
                        {{ tag.slug }}
                    </div>
                </div>

                <!-- Estado -->
                <div>
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-sidebar-border"
                        />

                        <span class="text-sm font-medium">
                            Etiqueta activa
                        </span>
                    </label>
                </div>

                <!-- Botones -->
                <div class="flex items-center gap-3 pt-2">
                    <Link
                        :href="admin.speciesTags.index().url"
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