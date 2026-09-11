<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

const form = useForm({
    name: '',
    description: '',
    points: 50,
    stock: 0,
    image: '',
    is_active: true,
});

const submit = (): void => {
    form.post(
        admin.rewards.store().url,
    );
};
</script>

<template>
    <Head title="Nueva recompensa" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <h1
                class="text-xl font-semibold"
            >
                Nueva recompensa
            </h1>

            <p
                class="mt-1 text-sm text-muted-foreground"
            >
                Define una recompensa que los usuarios podrán
                canjear utilizando sus puntos.
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
                        placeholder="Entrada gratis"
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
                        Usa 0 si no hay existencias actualmente.
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
                        placeholder="Entrada gratuita para visitar el Zoológico Sahuatoba."
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
                            : 'Guardar recompensa'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>