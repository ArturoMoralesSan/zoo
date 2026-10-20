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

interface Tag {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
}

const props = defineProps<{
    tag: Tag;
}>();

const form = useForm({
    name: props.tag.name,
    is_active: props.tag.is_active,
});

const submit = () => {
    form.put(admin.speciesTags.update(props.tag.id).url);
};
</script>

<template>
    <Head title="Editar etiqueta" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar etiqueta"
            description="Modifica la información de la etiqueta."
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
                description="Modifica el nombre, consulta el identificador y configura la disponibilidad de la etiqueta."
                :icon="Info"
            >
                <div class="flex flex-col gap-6">
                    <!-- Nombre -->
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
                            El slug se actualizará automáticamente al guardar
                            los cambios.
                        </p>

                        <p
                            v-if="form.errors.name"
                            class="form-error"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Slug -->
                    <div class="form-field">
                        <label for="slug" class="form-label">
                            Slug actual
                        </label>

                        <div
                            id="slug"
                            class="flex min-h-11 items-center rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] px-4 py-2.5 text-sm text-[#718477]"
                        >
                            {{ props.tag.slug }}
                        </div>

                        <p class="form-help">
                            Identificador generado automáticamente para la
                            etiqueta.
                        </p>
                    </div>

                    <!-- Estado -->
                    <ActiveSwitch
                        v-model="form.is_active"
                        label="Etiqueta activa"
                        description="Determina si la etiqueta estará disponible para asignarla a las especies."
                        :error="form.errors.is_active"
                    />
                </div>
            </AdminCard>

            <!-- Acciones -->
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
                    loading-text="Guardando..."
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>