<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    form.put(admin.users.update(props.user.id).url);
};
</script>

<template>
    <Head title="Editar usuario" />

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
                        Editar usuario
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Modifica la información y el rol del usuario.
                    </p>
                </div>

                <Link
                    :href="admin.users.index().url"
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
                class="max-w-2xl space-y-6"
            >
                <!-- Nombre -->
                <div>
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Email -->
                <div>
                    <label
                        for="email"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <!-- Contraseña -->
                <div>
                    <label
                        for="password"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <!-- Confirmar contraseña -->
                <div>
                    <label
                        for="password_confirmation"
                        class="mb-2 block text-sm font-medium"
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
                <div>
                    <label
                        for="role"
                        class="mb-2 block text-sm font-medium"
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
                        class="mt-2 text-sm text-red-500"
                    >
                        {{ form.errors.role }}
                    </p>
                </div>

                <!-- Botones -->
                <div class="flex items-center gap-3 pt-2">
                    <Link
                        :href="admin.users.index().url"
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