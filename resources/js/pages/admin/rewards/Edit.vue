<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h1
                class="text-xl font-semibold"
            >
                Editar recompensa
            </h1>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Modifica la configuración de esta recompensa.
            </p>
        </div>

        <form
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            @submit.prevent="submit"
        >
            <div
                class="grid gap-6 p-6"
            >
                <!-- Nombre -->
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

                <!-- Puntos -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Puntos necesarios
                    </label>

                    <input
                        v-model.number="form.points"
                        type="number"
                        min="1"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Cantidad de puntos necesarios para
                        canjear la recompensa.
                    </p>

                    <p
                        v-if="form.errors.points"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.points }}
                    </p>
                </div>

                <!-- Stock -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Stock
                    </label>

                    <input
                        v-model.number="form.stock"
                        type="number"
                        min="0"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Cantidad disponible para canje.
                    </p>

                    <p
                        v-if="form.errors.stock"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.stock }}
                    </p>
                </div>

                <!-- Descripción -->
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

                <!-- Imagen -->
                <div>
                    <label
                        class="mb-2 block text-sm font-medium"
                    >
                        Imagen
                    </label>

                    <input
                        v-model="form.image"
                        type="text"
                        placeholder="rewards/entrada-gratis.jpg"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                    />

                    <p
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Ruta de la imagen asociada a la recompensa.
                    </p>

                    <p
                        v-if="form.errors.image"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.image }}
                    </p>
                </div>

                <!-- Estado -->
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
                        Recompensa activa
                    </span>
                </label>

                <p
                    v-if="form.errors.is_active"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.is_active }}
                </p>
            </div>

            <div
                class="flex items-center justify-end gap-3 border-t border-sidebar-border/70 p-6 dark:border-sidebar-border"
            >
                <Link
                    :href="admin.rewards.index().url"
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