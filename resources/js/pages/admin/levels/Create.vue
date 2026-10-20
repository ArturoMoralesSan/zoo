<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    Info,
    Save,
    Tag,
    X,
} from 'lucide-vue-next';

import PageHeader from '@/components/admin/PageHeader.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import admin from '@/routes/admin';

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
                title: 'Nuevo nivel',
                href: admin.levels.create,
            },
        ],
    },
});

const form = useForm({
    name: '',
    min_points: 0,
    max_points: null as number | null,
    description: '',
});

const submit = () => {
    form.post(admin.levels.store().url);
};
</script>

<template>
    <Head title="Nuevo nivel" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo nivel"
            description="Crea un nuevo nivel para los visitantes."
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
                description="Define el nombre y la descripción del nivel."
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
                            :class="[
                                'form-input',
                                form.errors.name && 'form-input-error',
                            ]"
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
                            :class="[
                                'form-textarea',
                                form.errors.description &&
                                    'form-textarea-error',
                            ]"
                        />

                        <p class="form-help">
                            Explica qué representa este nivel para los
                            visitantes.
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
                <div class="space-y-5">
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
                                placeholder="Ej. 0"
                                required
                                :class="[
                                    'form-input',
                                    form.errors.min_points &&
                                        'form-input-error',
                                ]"
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
                                placeholder="Ej. 499"
                                :class="[
                                    'form-input',
                                    form.errors.max_points &&
                                        'form-input-error',
                                ]"
                            />

                            <p class="form-help">
                                Opcional. Déjalo vacío si el nivel no tiene un
                                límite máximo.
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
                    <div class="form-info">
                        <Info :size="18" class="form-info-icon" />

                        <p class="form-info-text">
                            El rango define los puntos que necesita acumular un
                            visitante para pertenecer a este nivel. Comprueba
                            que no se traslape con los rangos de otros niveles.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col gap-4 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center"
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
                        loading-text="Guardando..."
                        :disabled="form.processing"
                    >
                        Guardar nivel
                    </AdminButton>
                </div>
            </div>
        </form>
    </div>
</template>