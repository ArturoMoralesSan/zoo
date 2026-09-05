<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import admin from '@/routes/admin';

interface Level {
    id: number;
    name: string;
    min_points: number;
    max_points: number | null;
    description: string | null;
}

const props = defineProps<{
    level: Level;
}>();

const form = useForm({
    name: props.level.name,
    min_points: props.level.min_points,
    max_points: props.level.max_points,
    description: props.level.description ?? '',
});

const submit = () => {
    form.put(admin.levels.update(props.level.id).url);
};
</script>

<template>
    <Head title="Editar nivel" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div>
                <h1 class="text-2xl font-semibold">
                    Editar nivel
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Modifica la información del nivel
                    <strong>{{ level.name }}</strong>.
                </p>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                @submit.prevent="submit"
                class="space-y-6"
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

                <!-- Puntos -->
                <div class="grid gap-6 md:grid-cols-2">
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
                            placeholder="Sin límite"
                            class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                        />

                        <p class="text-xs text-muted-foreground">
                            Déjalo vacío si el nivel no tiene límite.
                        </p>

                        <p
                            v-if="form.errors.max_points"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.max_points }}
                        </p>
                    </div>
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

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-2 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="admin.levels.index().url"
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
                                ? 'Actualizando...'
                                : 'Actualizar nivel'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>