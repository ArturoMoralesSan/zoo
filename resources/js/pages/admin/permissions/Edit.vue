<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    KeyRound,
    LockKeyhole,
    Save,
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
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar permiso"
            :description="`Modifica la información del permiso ${permission.name}.`"
            section="Seguridad y acceso"
            :icon="KeyRound"
            :action-icon="ArrowLeft"
            action-label="Volver a permisos"
            :action-href="admin.permissions.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información del permiso -->
            <AdminCard
                title="Información del permiso"
                description="Actualiza los datos del permiso. El guard utilizado por el sistema no puede modificarse."
                :icon="KeyRound"
            >
                <div class="grid grid-cols-1 gap-5">
                    <!-- Nombre -->
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre del permiso
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ejemplo: users.view"
                            autocomplete="off"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>

                        <p v-else class="form-help">
                            Utiliza el formato módulo.acción, por ejemplo:
                            <code
                                class="rounded bg-[#E8F0EA] px-1.5 py-0.5 text-xs text-[#187744]"
                            >
                                users.view
                            </code>
                        </p>
                    </div>

                    <!-- Guard -->
                    <div class="form-field">
                        <label for="guard_name" class="form-label">
                            Guard de autenticación
                        </label>

                        <div class="relative">
                            <LockKeyhole
                                class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-[#84958A]"
                            />

                            <input
                                id="guard_name"
                                :value="permission.guard_name"
                                type="text"
                                disabled
                                class="form-input cursor-not-allowed bg-[#EDF1EE] pl-10 opacity-80"
                            />
                        </div>

                        <p class="form-help">
                            El guard está definido por la configuración de
                            autenticación y no puede modificarse desde aquí.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Información adicional -->
            <div
                class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-white p-4"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                >
                    <Info class="h-5 w-5" />
                </div>

                <div>
                    <p class="text-sm font-semibold text-[#244334]">
                        Información importante
                    </p>

                    <p class="mt-1 text-sm leading-6 text-[#718477]">
                        El nombre del permiso puede actualizarse, pero los
                        cambios deben respetar la convención de nombres
                        utilizada por los roles y las reglas de acceso de tu
                        aplicación.
                    </p>
                </div>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.permissions.index().url"
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