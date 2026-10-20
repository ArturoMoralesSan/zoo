<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    Info,
    LockKeyhole,
    Mail,
    Save,
    UserRound,
    Users,
} from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
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

const submit = (): void => {
    form.post(admin.users.store().url);
};
</script>

<template>
    <Head title="Nuevo usuario" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo usuario"
            description="Crea un nuevo usuario y asígnale un rol."
            section="Usuarios"
            :icon="Users"
            :action-icon="ArrowLeft"
            action-label="Volver a usuarios"
            :action-href="admin.users.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <AdminCard
                title="Información del usuario"
                description="Ingresa los datos de acceso y asigna el rol correspondiente."
                :icon="Info"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Nombre completo -->
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre completo
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">
                            <UserRound
                                class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                            />

                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                placeholder="Nombre completo"
                                required
                                :class="[
                                    'form-input pl-11',
                                    form.errors.name && 'form-input-error',
                                ]"
                            />
                        </div>

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Correo electrónico -->
                    <div class="form-field">
                        <label for="email" class="form-label">
                            Correo electrónico
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">
                            <Mail
                                class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                            />

                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                placeholder="usuario@ejemplo.com"
                                required
                                :class="[
                                    'form-input pl-11',
                                    form.errors.email && 'form-input-error',
                                ]"
                            />
                        </div>

                        <p v-if="form.errors.email" class="form-error">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <!-- Contraseña -->
                    <div class="form-field">
                        <label for="password" class="form-label">
                            Contraseña
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">
                            <LockKeyhole
                                class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                            />

                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Mínimo 8 caracteres"
                                required
                                :class="[
                                    'form-input pl-11',
                                    form.errors.password && 'form-input-error',
                                ]"
                            />
                        </div>

                        <p class="form-help">
                            Utiliza una contraseña segura para la cuenta.
                        </p>

                        <p v-if="form.errors.password" class="form-error">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Confirmar contraseña -->
                    <div class="form-field">
                        <label
                            for="password_confirmation"
                            class="form-label"
                        >
                            Confirmar contraseña
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">
                            <LockKeyhole
                                class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#718477]"
                            />

                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Repite la contraseña"
                                required
                                class="form-input pl-11"
                            />
                        </div>
                    </div>

                    <!-- Rol -->
                    <div class="form-field md:col-span-2">
                        <label for="role" class="form-label">
                            Rol del usuario
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="form-select-wrapper">
                            <select
                                id="role"
                                v-model="form.role"
                                required
                                :class="[
                                    'form-select',
                                    form.errors.role && 'form-select-error',
                                ]"
                            >
                                <option value="" disabled>
                                    Selecciona un rol
                                </option>

                                <option
                                    v-for="role in props.roles"
                                    :key="role.id"
                                    :value="role.name"
                                >
                                    {{ role.name }}
                                </option>
                            </select>

                            <ChevronDown
                                class="form-select-icon h-4 w-4"
                            />
                        </div>

                        <p class="form-help">
                            El rol determina los permisos y las funciones
                            disponibles para el usuario.
                        </p>

                        <p v-if="form.errors.role" class="form-error">
                            {{ form.errors.role }}
                        </p>

                        <p
                            v-if="props.roles.length === 0"
                            class="form-error"
                        >
                            No hay roles disponibles. Crea un rol antes de
                            registrar el usuario.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-end"
            >
                <CancelButton
                    :href="admin.users.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Creando..."
                    :disabled="props.roles.length === 0"
                >
                    Crear usuario
                </AdminButton>
            </div>
        </form>
    </div>
</template>