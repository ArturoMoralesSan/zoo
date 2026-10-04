<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    Save,
    Users,
} from 'lucide-vue-next';
import admin from '@/routes/admin';

interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
}

interface Role {
    id: number;
    name: string;
}

const props = defineProps<{
    user: User;
    roles: Role[];
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.roles[0]?.name ?? '',
});

const submit = () => {
    form.put(
        admin.users.update(
            props.user.id,
        ).url,
    );
};
</script>

<template>
    <Head title="Editar usuario" />

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
                <div class="flex items-center gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Users class="h-6 w-6" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar usuario
                        </h1>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Modifica la información y el rol del usuario.
                        </p>
                    </div>
                </div>

                <Link
                    :href="
                        admin.users.index().url
                    "
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background dark:border-sidebar-border"
        >
            <form
                class="flex flex-col"
                @submit.prevent="submit"
            >
                <div class="p-6">
                    <!-- Información -->
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-3">
                            <Info
                                class="h-5 w-5 text-primary"
                            />

                            <div>
                                <h2 class="text-base font-semibold">
                                    Información del usuario
                                </h2>

                                <p
                                    class="mt-1 text-sm text-muted-foreground"
                                >
                                    Modifica los datos de acceso y el rol correspondiente.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-6">
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
                                placeholder="Nombre completo"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.name"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <!-- Correo electrónico -->
                        <div class="space-y-2">
                            <label
                                for="email"
                                class="text-sm font-medium"
                            >
                                Correo electrónico
                            </label>

                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="usuario@ejemplo.com"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.email"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <!-- Nueva contraseña -->
                        <div class="space-y-2">
                            <label
                                for="password"
                                class="text-sm font-medium"
                            >
                                Nueva contraseña
                            </label>

                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                placeholder="Dejar vacío para conservar la actual"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />

                            <p
                                v-if="form.errors.password"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Confirmar contraseña -->
                        <div class="space-y-2">
                            <label
                                for="password_confirmation"
                                class="text-sm font-medium"
                            >
                                Confirmar nueva contraseña
                            </label>

                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                placeholder="Repite la nueva contraseña"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            />
                        </div>

                        <!-- Rol -->
                        <div class="space-y-2">
                            <label
                                for="role"
                                class="text-sm font-medium"
                            >
                                Rol
                            </label>

                            <select
                                id="role"
                                v-model="form.role"
                                class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </option>
                            </select>

                            <p
                                v-if="form.errors.role"
                                class="text-sm text-red-500"
                            >
                                {{ form.errors.role }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col gap-3 border-t border-sidebar-border/70 px-6 py-5 dark:border-sidebar-border sm:flex-row sm:items-center sm:justify-end"
                >
                    <Link
                        :href="
                            admin.users.index().url
                        "
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
