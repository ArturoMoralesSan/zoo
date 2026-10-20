
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

interface Category {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    category: Category;
}>();

const form = useForm({
    name: props.category.name,
    description: props.category.description ?? '',
    is_active: props.category.is_active,
});

const submit = () => {
    form.put(admin.speciesCategories.update(props.category.id).url);
};
</script>

<template>
    <Head title="Editar categoría" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Encabezado -->
        <PageHeader
            title="Editar categoría"
            :description="`Modifica la información de ${category.name}.`"
            section="Catálogo"
            :icon="Leaf"
            :action-icon="ArrowLeft"
            action-label="Volver a categorías"
            :action-href="admin.speciesCategories.index().url"
        />

        <!-- Tarjeta del formulario -->
        <AdminCard
            title="Información de la categoría"
            description="Modifica el nombre y la descripción de la categoría."
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

                    <!-- Información del registro -->
                    <div
                        class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-5"
                    >
                        <div class="flex items-center gap-2">
                            <Info class="h-4 w-4 text-[#187744]" />

                            <h3
                                class="text-sm font-semibold text-[#244334]"
                            >
                                Información del registro
                            </h3>
                        </div>

                        <div class="mt-4">
                            <p class="text-xs text-[#718477]">
                                ID de la categoría
                            </p>

                            <p
                                class="mt-1 text-sm font-semibold text-[#244334]"
                            >
                                {{ category.id }}
                            </p>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Pie de página -->
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
                    loading-text="Guardando..."
                    @click="submit"
                >
                    Guardar cambios
                </AdminButton>
            </template>
        </AdminCard>
    </div>
</template>
