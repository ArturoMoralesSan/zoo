<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import admin from '@/routes/admin';

interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

interface Role {
    id: number;
    name: string;
    permissions: Permission[];
}

const props = defineProps<{
    role: Role;
    permissions: Permission[];
}>();

const form = useForm({
    name: props.role.name,
    permissions: props.role.permissions.map(
        (permission) => permission.name
    ),
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
    const names = permissions.map((permission) => permission.name);

    const allSelected = names.every((name) =>
        form.permissions.includes(name)
    );

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (permission) => !names.includes(permission)
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
    form.put(admin.roles.update(props.role.id).url);
};
</script>

<template>
    <Head title="Editar rol" />

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
                        Editar rol
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Modifica el nombre y los permisos del rol.
                    </p>
                </div>

                <Link
                    :href="admin.roles.index().url"
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
                class="space-y-8"
            >
                <!-- Nombre -->
                <div class="max-w-2xl">
                    <label
                        for="name"
                        class="mb-2 block text-sm font-medium"
                    >
                        Nombre del rol
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        :disabled="role.name === 'admin'"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                    />

                    <p
                        v-if="role.name === 'admin'"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        El rol admin está protegido.
                    </p>

                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-sm text-red-500"
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

                        <p class="mt-1 text-sm text-muted-foreground">
                            Selecciona las acciones disponibles para este rol.
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="(permissions, module) in groupedPermissions()"
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
                                    @click="toggleGroup(permissions)"
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
                                                permission.name
                                            )
                                        "
                                        class="h-4 w-4 rounded border-sidebar-border"
                                        @change="
                                            togglePermission(
                                                permission.name
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

                <!-- Botones -->
                <div class="flex items-center gap-3 pt-2">
                    <Link
                        :href="admin.roles.index().url"
                        class="rounded-lg border border-sidebar-border px-5 py-2.5 text-sm font-medium transition hover:bg-accent"
                    >
                        Cancelar
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
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