<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    KeyRound,
    LockKeyhole,
    Save,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';

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
        (permission) => permission.name,
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
    form.put(
        admin.roles.update(
            props.role.id,
        ).url,
    );
};
</script>

<template>
    <Head title="Editar rol" />

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
                        <ShieldCheck class="h-5 w-5" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold">
                            Editar rol
                        </h1>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Modifica el nombre y los permisos del rol.
                        </p>
                    </div>
                </div>

                <Link
                    :href="admin.roles.index().url"
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
                        <ShieldCheck class="h-4 w-4 text-primary" />

                        <h2 class="text-base font-semibold">
                            Información del rol
                        </h2>
                    </div>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Modifica la configuración y los permisos asignados a
                        este rol.
                    </p>
                </div>

                <!-- Nombre -->
                <div class="space-y-2">
                    <label
                        for="name"
                        class="flex items-center gap-2 text-sm font-medium"
                    >
                        <Users class="h-4 w-4 text-muted-foreground" />
                        Nombre del rol
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        :disabled="role.name === 'admin'"
                        placeholder="Ejemplo: editor"
                        class="w-full rounded-lg border border-sidebar-border bg-background px-4 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-60"
                    />

                    <div
                        v-if="role.name === 'admin'"
                        class="flex items-center gap-2 rounded-lg border border-sidebar-border bg-muted/30 px-3 py-2.5"
                    >
                        <LockKeyhole
                            class="h-4 w-4 shrink-0 text-muted-foreground"
                        />

                        <p class="text-xs text-muted-foreground">
                            El rol admin está protegido.
                        </p>
                    </div>

                    <p
                        v-if="form.errors.name"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Permisos -->
                <div>
                    <div
                        class="border-b border-sidebar-border/70 pb-4 dark:border-sidebar-border"
                    >
                        <div class="flex items-center gap-2">
                            <KeyRound class="h-4 w-4 text-primary" />

                            <h2 class="text-base font-semibold">
                                Permisos
                            </h2>
                        </div>

                        <p class="mt-1 text-sm text-muted-foreground">
                            Selecciona las acciones disponibles para este rol.
                        </p>
                    </div>

                    <div
                        class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="(
                                permissions,
                                module
                            ) in groupedPermissions()"
                            :key="module"
                            class="rounded-xl border border-sidebar-border bg-muted/10 p-4 dark:border-sidebar-border"
                        >
                            <!-- Encabezado módulo -->
                            <div
                                class="mb-4 flex items-center justify-between border-b border-sidebar-border/70 pb-3 dark:border-sidebar-border"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                    >
                                        <KeyRound class="h-4 w-4" />
                                    </div>

                                    <h3 class="font-semibold capitalize">
                                        {{ module }}
                                    </h3>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-sidebar-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-accent"
                                    @click="
                                        toggleGroup(
                                            permissions,
                                        )
                                    "
                                >
                                    <Check class="h-3.5 w-3.5" />
                                    Seleccionar
                                </button>
                            </div>

                            <!-- Permisos -->
                            <div class="space-y-2">
                                <label
                                    v-for="permission in permissions"
                                    :key="permission.id"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 transition hover:border-sidebar-border hover:bg-muted/30"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="
                                            form.permissions.includes(
                                                permission.name,
                                            )
                                        "
                                        class="h-4 w-4 rounded border-sidebar-border text-primary focus:ring-primary/20"
                                        @change="
                                            togglePermission(
                                                permission.name,
                                            )
                                        "
                                    />

                                    <div class="min-w-0">
                                        <span class="block text-sm font-medium">
                                            {{ permission.name }}
                                        </span>

                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ permission.guard_name }}
                                        </span>
                                    </div>
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
                        :href="admin.roles.index().url"
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