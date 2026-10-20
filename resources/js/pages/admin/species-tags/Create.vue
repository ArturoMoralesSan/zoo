<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    Save,
    Tags,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

const form = useForm({
    name: '',
    is_active: true,
});

const submit = () => {
    form.post(admin.speciesTags.store().url);
};
</script>

<template>
    <Head title="Nueva etiqueta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nueva etiqueta"
            description="Crea una etiqueta para utilizarla en las especies."
            section="Catálogo"
            :icon="Tags"
            :action-icon="ArrowLeft"
            action-label="Volver a etiquetas"
            :action-href="admin.speciesTags.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <AdminCard
                title="Información de la etiqueta"
                description="Define el nombre y la disponibilidad de la etiqueta para clasificar las especies."
                :icon="Info"
            >
                <div class="flex flex-col gap-6">
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre de la etiqueta
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ejemplo: Felino"
                            autocomplete="off"
                            required
                            :class="[
                                'form-input',
                                form.errors.name && 'form-input-error',
                            ]"
                        />

                        <p class="form-help">
                            El slug se generará automáticamente.
                        </p>

                        <p
                            v-if="form.errors.name"
                            class="form-error"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <ActiveSwitch
                        v-model="form.is_active"
                        label="Etiqueta activa"
                        description="Determina si la etiqueta estará disponible para asignarla a las especies."
                        :error="form.errors.is_active"
                    />
                </div>
            </AdminCard>

            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-end"
            >
                <CancelButton
                    :href="admin.speciesTags.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Creando..."
                >
                    Crear etiqueta
                </AdminButton>
            </div>
        </form>
    </div>
</template>