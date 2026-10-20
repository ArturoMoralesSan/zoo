<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    KeyRound,
    LockKeyhole,
    Save,
    ShieldCheck,
} from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

const togglePermission = (permission: string): void => {
    const index = form.permissions.indexOf(permission);

    if (index === -1) {
        form.permissions.push(permission);
    } else {
        form.permissions.splice(index, 1);
    }
};

const toggleGroup = (permissions: Permission[]): void => {
    const names = permissions.map((permission) => permission.name);

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

const submit = (): void => {
    form.put(admin.roles.update(props.role.id).url);
};
</script>

<template>
    <Head title="Editar rol" />

    <div class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5">
        <PageHeader
            title="Editar rol"
            description="Modifica el nombre y los permisos asignados a este rol."
            section="Administración"
            :icon="ShieldCheck"
            :action-icon="ArrowLeft"
            action-label="Volver a roles"
            :action-href="admin.roles.index().url"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información del rol"
                description="Modifica el nombre y la configuración de este rol."
                :icon="ShieldCheck"
            >
                <div class="form-field">
                    <label for="name" class="form-label">
                        Nombre del rol
                        <span class="form-required">*</span>
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        :disabled="props.role.name === 'admin'"
                        placeholder="Ej. editor"
                        class="form-input"
                        :class="{
                            'form-input-error': form.errors.name,
                            'cursor-not-allowed opacity-60':
                                props.role.name === 'admin',
                        }"
                    />

                    <p class="form-help">
                        Ejemplos: admin, staff, editor.
                    </p>

                    <div
                        v-if="props.role.name === 'admin'"
                        class="mt-2 flex items-center gap-2 rounded-lg border border-[#DCE7DF] bg-[#F8FBF9] px-3 py-2.5"
                    >
                        <LockKeyhole class="h-4 w-4 shrink-0 text-[#718477]" />

                        <p class="text-xs text-[#718477]">
                            El rol admin está protegido y su nombre no se puede modificar.
                        </p>
                    </div>

                    <p v-if="form.errors.name" class="form-error">
                        {{ form.errors.name }}
                    </p>
                </div>
            </AdminCard>

            <!-- Permisos -->
            <AdminCard
                title="Permisos del rol"
                description="Administra los permisos disponibles para este rol. Los permisos actuales aparecen seleccionados."
                :icon="KeyRound"
            >
                <div class="mb-5 flex flex-col gap-3 rounded-xl border border-[#DCE7DF] bg-[#F8FBF9] p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-[#244334]">
                            Permisos seleccionados
                        </p>

                        <p class="mt-1 text-sm text-[#718477]">
                            Selecciona o desmarca permisos individualmente o por módulo.
                        </p>
                    </div>

                    <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full bg-[#E2F3E8] px-3 py-1.5 text-sm font-semibold text-[#187744]">
                        <Check class="h-4 w-4" />
                        {{ form.permissions.length }} seleccionados
                    </span>
                </div>

                <div
                    v-if="Object.keys(groupedPermissions()).length > 0"
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="(permissions, module) in groupedPermissions()"
                        :key="module"
                        class="overflow-hidden rounded-xl border border-[#DCE7DF] bg-white transition-colors hover:border-[#BFCFC6]"
                    >
                        <div class="flex items-center justify-between gap-3 border-b border-[#E5ECE7] bg-[#F8FBF9] px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E2F3E8] text-[#187744]">
                                    <KeyRound class="h-4 w-4" />
                                </div>

                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold capitalize text-[#244334]">
                                        {{ module }}
                                    </h3>

                                    <p class="text-xs text-[#718477]">
                                        {{ permissions.length }}
                                        {{ permissions.length === 1 ? 'permiso' : 'permisos' }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#DCE7DF] bg-white px-2.5 py-1.5 text-xs font-medium text-[#435D4C] transition hover:border-[#BFCFC6] hover:bg-[#EDF5EF]"
                                @click="toggleGroup(permissions)"
                            >
                                <Check class="h-3.5 w-3.5" />

                                {{
                                    permissions.every((permission) =>
                                        form.permissions.includes(permission.name),
                                    )
                                        ? 'Quitar todos'
                                        : 'Seleccionar todos'
                                }}
                            </button>
                        </div>

                        <div class="space-y-1 p-3">
                            <label
                                v-for="permission in permissions"
                                :key="permission.id"
                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-transparent px-3 py-3 transition hover:border-[#DCE7DF] hover:bg-[#F8FBF9]"
                                :class="{
                                    'border-[#DCE7DF] bg-[#F2F7F4]':
                                        form.permissions.includes(permission.name),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.permissions.includes(permission.name)"
                                    class="form-checkbox mt-0.5 shrink-0"
                                    @change="togglePermission(permission.name)"
                                />

                                <span class="min-w-0 flex-1">
                                    <span class="block break-words text-sm font-medium text-[#244334]">
                                        {{ permission.name }}
                                    </span>

                                    <span class="mt-1 block text-xs text-[#718477]">
                                        Guard: {{ permission.guard_name }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#BFCFC6] bg-[#F8FBF9] px-5 py-10 text-center"
                >
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#E2F3E8] text-[#187744]">
                        <KeyRound class="h-5 w-5" />
                    </div>

                    <h3 class="text-sm font-semibold text-[#244334]">
                        No hay permisos disponibles
                    </h3>

                    <p class="mt-1 max-w-sm text-sm text-[#718477]">
                        Los permisos registrados aparecerán aquí agrupados por módulo.
                    </p>
                </div>

                <p v-if="form.errors.permissions" class="form-error mt-3">
                    {{ form.errors.permissions }}
                </p>
            </AdminCard>

            <!-- Información -->
            <div class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-white p-4">
                <ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-[#187744]" />

                <p class="text-sm leading-6 text-[#718477]">
                    Revisa los permisos antes de guardar. Los cambios afectarán
                    las acciones que pueden realizar los usuarios que tengan
                    asignado este rol.
                </p>
            </div>

            <!-- Acciones -->
            <div class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-4 sm:flex-row sm:items-center sm:justify-end">
                <CancelButton
                    :href="admin.roles.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Guardando..."
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>