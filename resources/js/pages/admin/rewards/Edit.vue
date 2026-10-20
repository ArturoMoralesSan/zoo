<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Coins,
    Image,
    Save,
    ToggleLeft,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

interface Reward {
    id: number;
    name: string;
    description: string | null;
    points: number;
    stock: number;
    image: string | null;
    is_active: boolean;
}

const props = defineProps<{
    reward: Reward;
}>();

const form = useForm({
    name: props.reward.name,
    points: props.reward.points,
    stock: props.reward.stock,
    description: props.reward.description ?? '',
    image: props.reward.image ?? '',
    is_active: props.reward.is_active,
});

const submit = (): void => {
    form.put(admin.rewards.update(props.reward.id).url);
};
</script>

<template>
    <Head title="Editar recompensa" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Cabecera -->
        <PageHeader
            title="Editar recompensa"
            description="Modifica la configuración de esta recompensa."
            section="Recompensas"
            :icon="Coins"
            :action-icon="ArrowLeft"
            action-label="Volver a recompensas"
            :action-href="admin.rewards.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <!-- Información general -->
            <AdminCard
                title="Información de la recompensa"
                description="Actualiza los datos principales y las condiciones para canjearla."
                :icon="Coins"
            >
                <div class="form-grid">
                    <!-- Nombre -->
                    <div class="form-field form-field-full">
                        <label for="name" class="form-label">
                            Nombre
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Puntos -->
                    <div class="form-field">
                        <label for="points" class="form-label">
                            Puntos necesarios
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="points"
                            v-model.number="form.points"
                            type="number"
                            min="1"
                            step="1"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.points,
                            }"
                        />

                        <p class="form-help">
                            Cantidad de puntos necesarios para canjear la recompensa.
                        </p>

                        <p v-if="form.errors.points" class="form-error">
                            {{ form.errors.points }}
                        </p>
                    </div>

                    <!-- Stock -->
                    <div class="form-field">
                        <label for="stock" class="form-label">
                            Stock
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="stock"
                            v-model.number="form.stock"
                            type="number"
                            min="0"
                            step="1"
                            required
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.stock,
                            }"
                        />

                        <p class="form-help">
                            Cantidad disponible para canje.
                        </p>

                        <p v-if="form.errors.stock" class="form-error">
                            {{ form.errors.stock }}
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field form-field-full">
                        <label for="description" class="form-label">
                            Descripción
                        </label>

                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
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
                </div>
            </AdminCard>

            <!-- Imagen -->
            <AdminCard
                title="Imagen de la recompensa"
                description="Actualiza la ruta de la imagen asociada a esta recompensa."
                :icon="Image"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <label for="image" class="form-label">
                            Ruta de la imagen
                        </label>

                        <input
                            id="image"
                            v-model="form.image"
                            type="text"
                            placeholder="rewards/entrada-gratis.jpg"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.image,
                            }"
                        />

                        <p class="form-help">
                            Introduce la ruta de la imagen asociada a la recompensa.
                        </p>

                        <p v-if="form.errors.image" class="form-error">
                            {{ form.errors.image }}
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Controla si la recompensa estará disponible para realizar canjes."
                :icon="ToggleLeft"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <ActiveSwitch
                            v-model="form.is_active"
                            label="Recompensa activa"
                            description="Las recompensas activas podrán ser seleccionadas para realizar canjes."
                            :error="form.errors.is_active"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Información -->
            <div class="form-info">
                <div class="form-info-icon">
                    <Check class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    Verifica el nombre, los puntos necesarios y las existencias
                    antes de guardar. Solo las recompensas activas estarán
                    disponibles para nuevos canjes.
                </p>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.rewards.index().url"
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