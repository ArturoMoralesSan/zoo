
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    Leaf,
    Save,
} from 'lucide-vue-next';

import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import admin from '@/routes/admin';

const form = useForm({
    name: '',
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(admin.speciesCategories.store().url);
};
</script>

<template>
    <Head title="Nueva categoría" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado -->
        <PageHeader
            title="Nueva categoría"
            description="Crea una nueva categoría para las especies del zoológico."
            section="Catálogo"
            :icon="Leaf"
            :action-icon="ArrowLeft"
            action-label="Volver a categorías"
            :action-href="admin.speciesCategories.index().url"
        />

        <!-- Tarjeta del formulario -->
        <AdminCard
            title="Información de la categoría"
            description="Define el nombre y la descripción de la categoría."
            :icon="Info"
            :padding="false"
        >
            <!-- Formulario -->
            <form
                id="category-form"
                @submit.prevent="submit"
            >
                <div class="space-y-6 p-5 sm:p-6">
                    <!-- Nombre -->
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Ej. Mamíferos"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                        />

                        <p
                            v-if="form.errors.name"
                            class="form-error"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field">
                        <label
                            for="description"
                            class="form-label"
                        >
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Descripción de la categoría..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error':
                                    form.errors.description,
                            }"
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Estado -->
                    <ActiveSwitch
                        id="is_active"
                        v-model="form.is_active"
                        label="Categoría activa"
                        description="Determina si la categoría estará disponible para su uso en el sistema."
                        :error="form.errors.is_active"
                    />
                </div>
            </form>

            <!-- Pie de página opcional -->
            <template #footer>
                <CancelButton
                    :href="admin.speciesCategories.index().url"
                    :icon="ArrowLeft"
                >
                    Cancelar
                </CancelButton>

                <AdminButton
                    type="submit"
                    :disabled="form.processing"
                    :loading="form.processing"
                    :icon="Save"
                    loading-text="Agregando..."
                    @click="submit"
                >
                    Agregar categoría
                </AdminButton>
            </template>
        </AdminCard>
    </div>
</template>
