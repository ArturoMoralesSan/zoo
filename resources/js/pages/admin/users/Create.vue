<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface Role {
    id: number;
    name: string;
}

const props = defineProps<{
    roles: Role[];
}>();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: props.roles[0]?.name ?? '',
});

const submit = () => {
    form.post(
        admin.users.store().url,
    );
};
</script>

<template>
    <Head title="Nuevo usuario" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <!-- Encabezado -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        Nuevo usuario
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Crea un nuevo usuario y asígnale un rol.
                    </p>
                </div>

                <Link
                    :href="
                        admin.users.index().url
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-sidebar-border px-4 py-2.5 text-sm font-medium transition hover:bg-accent"
                >
                    Regresar
                </Link>
            </div>
        </div>

        <!-- Formulario -->
        <div
            class="relative rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border"
        >
            <form
                class="space-y-6"
                @submit.prevent="submit"
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

                <!-- Contraseña -->
                <div class="space-y-2">
                    <label
                        for="password"
                        class="text-sm font-medium"
                    >
                        Contraseña
                    </label>

                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        placeholder="Mínimo 8 caracteres"
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
                        Confirmar contraseña
                    </label>

                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Repite la contraseña"
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

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.users.index().url
                        "
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
                                ? 'Creando...'
                                : 'Crear usuario'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
