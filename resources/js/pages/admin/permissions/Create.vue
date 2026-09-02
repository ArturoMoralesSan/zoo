<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import permissions from '@/routes/admin/permissions'
import admin from '@/routes/admin'

const form = useForm({
    name: '',
})

const submit = () => {
    form.post(permissions.store().url)
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Permisos',
                href: permissions.index(),
            },
            {
                title: 'Nuevo permiso',
                href: permissions.create(),
            },
        ],
    },
})
</script>

<template>
    <Head title="Nuevo permiso" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="relative flex-1 rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div class="mb-6">
                <h1 class="text-xl font-semibold text-foreground">
                    Nuevo permiso
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Crea un nuevo permiso para asignarlo posteriormente a un rol.
                </p>
            </div>

            <form
                class="max-w-2xl space-y-6"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium text-foreground"
                    >
                        Nombre del permiso
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ejemplo: users.view"
                        class="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2.5 text-sm text-foreground outline-none transition focus:border-foreground dark:border-sidebar-border"
                        :class="{
                            'border-red-500': form.errors.name,
                        }"
                    />

                    <p class="mt-1.5 text-xs text-muted-foreground">
                        Recomendado: modulo.accion
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 sm:flex-row sm:justify-end dark:border-sidebar-border"
                >
                    <Link
                        :href="permissions.index()"
                        class="rounded-lg border border-sidebar-border/70 px-4 py-2 text-center text-sm font-medium text-foreground transition hover:bg-muted dark:border-sidebar-border"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar permiso' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>