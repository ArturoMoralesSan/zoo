<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    KeyRound,
    Save,
} from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';
import permissions from '@/routes/admin/permissions';

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(permissions.store().url);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Permisos',
                href: permissions.index(),
            },
            {
                title: 'Nuevo permiso',
                href: permissions.create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo permiso" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo permiso"
            description="Crea un nuevo permiso para asignarlo posteriormente a un rol."
            section="Seguridad y acceso"
            :icon="KeyRound"
            :action-icon="ArrowLeft"
            action-label="Volver a permisos"
            :action-href="permissions.index().url"
        />

        <form
            class="space-y-5"
            @submit.prevent="submit"
        >
            <!-- Información del permiso -->
            <AdminCard
                title="Información del permiso"
                description="Define el nombre que identificará este permiso dentro del sistema."
                :icon="KeyRound"
            >
                <div class="form-field">
                    <label for="name" class="form-label">
                        Nombre del permiso
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-input"
                        :class="{
                            'form-input-error': form.errors.name,
                        }"
                        placeholder="Ejemplo: users.view"
                        autocomplete="off"
                        required
                    />

                    <p
                        v-if="form.errors.name"
                        class="form-error"
                    >
                        {{ form.errors.name }}
                    </p>

                    <p v-else class="form-help">
                        Se recomienda utilizar el formato
                        <code
                            class="rounded bg-[#E8F0EA] px-1.5 py-0.5 text-xs text-[#187744]"
                        >
                            modulo.accion
                        </code>,
                        por ejemplo: users.view, users.create o users.delete.
                    </p>
                </div>
            </AdminCard>

            <!-- Recomendación -->
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
                        Recomendación
                    </p>

                    <p class="mt-1 text-sm leading-6 text-[#718477]">
                        Utiliza nombres claros y consistentes para facilitar la
                        asignación de permisos a los roles y el mantenimiento
                        de las reglas de acceso.
                    </p>
                </div>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="permissions.index().url"
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
                    Guardar permiso
                </AdminButton>
            </div>
        </form>
    </div>
</template>