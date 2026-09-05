<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import admin from '@/routes/admin';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

const props = defineProps<{
    permissions: Permission[];
}>();

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const groupedPermissions = () => {
    const groups: Record<string, Permission[]> = {};

    props.permissions.forEach((permission) => {
        const module = permission.name.split('.')[0] ?? 'otros';

        if (!groups[module]) {
            groups[module] = [];
        }

        groups[module].push(permission);
    });

    return groups;
};

const togglePermission = (permission: string) => {
    const index = form.permissions.indexOf(permission);

    if (index === -1) {
        form.permissions.push(permission);
    } else {
        form.permissions.splice(index, 1);
    }
};

const toggleGroup = (permissions: Permission[]) => {
    const names = permissions.map(
        (permission) => permission.name,
    );

    const allSelected = names.every((name) =>
        form.permissions.includes(name),
    );

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (permission) => !names.includes(permission),
        );
    } else {
        names.forEach((name) => {
            if (!form.permissions.includes(name)) {
                form.permissions.push(name);
            }
        });
    }
};

const submit = () => {
    form.post(
        admin.roles.store().url,
    );
};
</script>

<template>
    <Head title="Nuevo rol" />

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
                        Nuevo rol
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Crea un rol y selecciona los permisos que tendrá.
                    </p>
                </div>

                <Link
                    :href="
                        admin.roles.index().url
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
                        Nombre del rol
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="Ejemplo: editor"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                    />

                    <p class="text-xs text-muted-foreground">
                        Ejemplos: admin, staff, editor.
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Permisos -->
                <div>
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">
                            Permisos
                        </h2>

                        <p
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Selecciona las acciones que podrá realizar este
                            rol.
                        </p>
                    </div>

                    <div
                        class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(
                                permissions,
                                module
                            ) in groupedPermissions()"
                            :key="module"
                            class="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <!-- Encabezado módulo -->
                            <div
                                class="mb-4 flex items-center justify-between border-b border-sidebar-border/70 pb-3 dark:border-sidebar-border"
                            >
                                <h3
                                    class="font-semibold capitalize"
                                >
                                    {{ module }}
                                </h3>

                                <button
                                    type="button"
                                    class="text-xs text-primary hover:underline"
                                    @click="
                                        toggleGroup(
                                            permissions,
                                        )
                                    "
                                >
                                    Seleccionar
                                </button>
                            </div>

                            <!-- Permisos -->
                            <div class="space-y-3">
                                <label
                                    v-for="permission in permissions"
                                    :key="permission.id"
                                    class="flex cursor-pointer items-center gap-3"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            form.permissions.includes(
                                                permission.name,
                                            )
                                        "
                                        class="h-4 w-4 rounded border-sidebar-border"
                                        @change="
                                            togglePermission(
                                                permission.name,
                                            )
                                        "
                                    />

                                    <span class="text-sm">
                                        {{ permission.name }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.permissions"
                        class="mt-3 text-sm text-red-500"
                    >
                        {{ form.errors.permissions }}
                    </p>
                </div>

                <!-- Acciones -->
                <div
                    class="flex flex-col-reverse gap-3 border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border sm:flex-row sm:justify-end"
                >
                    <Link
                        :href="
                            admin.roles.index().url
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
                                : 'Crear rol'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
