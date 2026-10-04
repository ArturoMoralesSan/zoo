<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, KeyRound, Save } from 'lucide-vue-next';

import admin from '@/routes/admin';
import permissions from '@/routes/admin/permissions';

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(
        permissions.store().url,
    );
};

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
});
</script>

<template>
    <Head title="Nuevo permiso" />

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
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <KeyRound class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Nuevo permiso
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Crea un nuevo permiso para asignarlo posteriormente
                            a un rol.
                        </p>
                    </div>
                </div>

                <Link
                    :href="permissions.index().url"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 bg-background p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
            >
                <!-- Información general -->
                <div
                    class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                >
                    <div class="flex items-center gap-2">
                        <KeyRound class="h-4 w-4 text-primary" />

                        <h2 class="text-base font-semibold">
                            Información del permiso
                        </h2>
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Define el nombre que identificará este permiso dentro
                        del sistema.
                    </p>
                </div>

                <!-- Nombre -->
                <div class="space-y-2">
                    <label
                        for="name"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <KeyRound class="h-4 w-4 text-muted-foreground" />
                        Nombre del permiso
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ejemplo: users.view"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Recomendado: modulo.accion
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="permissions.index().url"
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
                                : 'Guardar permiso'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>