<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface PointRule {
    id: number;
    type: string;
    name: string;
    points: number;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    rule: PointRule;
}>();

const form = useForm({
    type: props.rule.type,
    name: props.rule.name,
    points: props.rule.points,
    description: props.rule.description ?? '',
    is_active: props.rule.is_active,
});

const submit = (): void => {
    form.put(
        admin.pointRules.update(props.rule.id).url,
    );
};
</script>

<template>
    <Head title="Editar regla de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h1
                class="text-xl font-semibold"
            >
                Editar regla de puntos
            </h1>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Modifica la configuración de esta regla.
            </p>
        </div>

        <form
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div
                class="grid gap-6 p-6"
            >
                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Tipo
                    </label>

                    <input
                        v-model="form.type"
                        type="text"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        v-if="form.errors.type"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.type }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Nombre
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Puntos
                    </label>

                    <input
                        v-model.number="form.points"
                        type="number"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        v-if="form.errors.points"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.points }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Descripción
                    </label>

                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    ></textarea>

                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>

                <label
                    class="flex items-center gap-3"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-sidebar-border"
                    />

                    <span
                        class="text-sm font-medium"
                    >
                        Regla activa
                    </span>
                </label>
            </div>

            <div
                class="flex items-center justify-end gap-3 border-t border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <Link
                    :href="admin.pointRules.index().url"
                    class="rounded-lg px-4 py-2 text-sm font-medium hover:bg-muted"
                >
                    Cancelar
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
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
</template>