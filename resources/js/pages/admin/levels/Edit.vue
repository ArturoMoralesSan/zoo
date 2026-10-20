<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    Info,
    Save,
    X,
} from 'lucide-vue-next';

import admin from '@/routes/admin';
import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';

interface Level {
    id: number;
    name: string;
    min_points: number;
    max_points: number | null;
    description: string | null;
}

const props = defineProps<{
    level: Level;
}>();

const form = useForm({
    name: props.level.name,
    min_points: props.level.min_points,
    max_points: props.level.max_points,
    description: props.level.description ?? '',
});

const submit = () => {
    form.put(admin.levels.update(props.level.id).url);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Niveles',
                href: admin.levels.index,
            },
            {
                title: 'Editar nivel',
                href: admin.levels.edit,
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar nivel" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar nivel"
            :description="`Modifica la información del nivel ${props.level.name}.`"
            section="Fidelización"
            :icon="Award"
            :action-icon="ArrowLeft"
            action-label="Volver a niveles"
            :action-href="admin.levels.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información del nivel"
                description="Actualiza el nombre y la descripción del nivel."
                :icon="Info"
            >
                <div class="space-y-5">
                    <!-- Nombre -->
                    <div class="form-field">
                        <label for="name" class="form-label">
                            Nombre del nivel
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            maxlength="255"
                            placeholder="Ej. Bronce"
                            required
                            autocomplete="off"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            placeholder="Describe este nivel y sus beneficios..."
                            class="form-textarea"
                            :class="{
                                'form-textarea-error': form.errors.description,
                            }"
                        />

                        <p class="form-help">
                            Explica qué representa este nivel para los visitantes.
                        </p>

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Rango de puntos -->
            <AdminCard
                title="Rango de puntos"
                description="Define los puntos necesarios para pertenecer a este nivel."
                :icon="Award"
            >
                <div class="form-grid">
                    <!-- Puntos mínimos -->
                    <div class="form-field">
                        <label for="min_points" class="form-label">
                            Puntos mínimos
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="min_points"
                            v-model.number="form.min_points"
                            type="number"
                            min="0"
                            step="1"
                            required
                            placeholder="Ej. 0"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.min_points,
                            }"
                        />

                        <p class="form-help">
                            Cantidad mínima para alcanzar este nivel.
                        </p>

                        <p
                            v-if="form.errors.min_points"
                            class="form-error"
                        >
                            {{ form.errors.min_points }}
                        </p>
                    </div>

                    <!-- Puntos máximos -->
                    <div class="form-field">
                        <label for="max_points" class="form-label">
                            Puntos máximos
                        </label>

                        <input
                            id="max_points"
                            v-model.number="form.max_points"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Sin límite"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.max_points,
                            }"
                        />

                        <p class="form-help">
                            Opcional. Déjalo vacío si el nivel no tiene límite máximo.
                        </p>

                        <p
                            v-if="form.errors.max_points"
                            class="form-error"
                        >
                            {{ form.errors.max_points }}
                        </p>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="form-info mt-5">
                    <Info :size="18" class="form-info-icon" />

                    <p class="form-info-text">
                        Comprueba que el rango de puntos no se traslape con
                        otros niveles antes de guardar los cambios.
                    </p>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col gap-4 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="form-help sm:mr-auto">
                    <span class="form-required">*</span>
                    Campos obligatorios.
                </p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <CancelButton
                        :href="admin.levels.index().url"
                        :icon="X"
                        label="Cancelar"
                    />

                    <AdminButton
                        type="submit"
                        variant="primary"
                        :icon="Save"
                        :loading="form.processing"
                        loading-text="Actualizando..."
                        :disabled="form.processing"
                    >
                        Actualizar nivel
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>