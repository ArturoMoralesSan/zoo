<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    KeyRound,
    LockKeyhole,
    Save,
} from 'lucide-vue-next';

import admin from '@/routes/admin';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

const props = defineProps<{
    permission: Permission;
}>();

const form = useForm({
    name: props.permission.name,
});

const submit = () => {
    form.put(admin.permissions.update(props.permission.id).url);
};
</script>

<template>
    <Head title="Editar permiso" />

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
                            Editar permiso
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica la información del permiso.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.permissions.index().url"
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
                        Actualiza los datos del permiso. El guard utilizado
                        por el sistema no puede modificarse.
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
                        Utiliza el formato módulo.acción, por ejemplo:
                        <strong>users.view</strong>
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Guard -->
                <div class="space-y-2">
                    <label
                        for="guard_name"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <LockKeyhole class="h-4 w-4 text-muted-foreground" />
                        Guard
                    </label>

                    <input
                        id="guard_name"
                        :value="permission.guard_name"
                        type="text"
                        disabled
                        class="w-full rounded-lg border border-sidebar-border bg-muted px-4 py-2.5 text-sm outline-none transition disabled:cursor-not-allowed disabled:opacity-60"
                    />

                    <p class="text-xs text-muted-foreground">
                        El guard está definido por la configuración de
                        autenticación y no puede modificarse desde aquí.
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="admin.permissions.index().url"
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