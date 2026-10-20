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

const form = useForm({
    name: '',
    description: '',
    points: 50,
    stock: 0,
    image: '',
    is_active: true,
});

const submit = (): void => {
    form.post(admin.rewards.store().url);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Recompensas',
                href: admin.rewards.index(),
            },
            {
                title: 'Nueva recompensa',
                href: admin.rewards.create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nueva recompensa" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <!-- Cabecera -->
        <PageHeader
            title="Nueva recompensa"
            description="Define una recompensa que los usuarios podrán canjear utilizando sus puntos."
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
                description="Configura los datos principales y los puntos necesarios para canjearla."
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
                            class="form-input"
                            :class="{ 'form-input-error': form.errors.name }"
                            placeholder="Ej. Entrada gratis"
                            autocomplete="off"
                            required
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>

                        <p v-else class="form-help">
                            Nombre con el que se identificará la recompensa en el sistema.
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
                            class="form-input"
                            :class="{ 'form-input-error': form.errors.points }"
                            required
                        />

                        <p v-if="form.errors.points" class="form-error">
                            {{ form.errors.points }}
                        </p>

                        <p v-else class="form-help">
                            Cantidad de puntos que debe tener el usuario para canjear la recompensa.
                        </p>
                    </div>

                    <!-- Stock -->
                    <div class="form-field">
                        <label for="stock" class="form-label">
                            Stock disponible
                            <span class="form-required">*</span>
                        </label>

                        <input
                            id="stock"
                            v-model.number="form.stock"
                            type="number"
                            min="0"
                            step="1"
                            class="form-input"
                            :class="{ 'form-input-error': form.errors.stock }"
                            required
                        />

                        <p v-if="form.errors.stock" class="form-error">
                            {{ form.errors.stock }}
                        </p>

                        <p v-else class="form-help">
                            Cantidad disponible para canje. Usa 0 si no hay existencias.
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
                                'form-textarea-error': form.errors.description,
                            }"
                            placeholder="Describe la recompensa y las condiciones para utilizarla..."
                        />

                        <p v-if="form.errors.description" class="form-error">
                            {{ form.errors.description }}
                        </p>

                        <p v-else class="form-help">
                            Agrega información adicional sobre lo que recibirá el usuario al canjearla.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Imagen -->
            <AdminCard
                title="Imagen de la recompensa"
                description="Configura la imagen que identificará visualmente la recompensa."
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
                            class="form-input"
                            :class="{ 'form-input-error': form.errors.image }"
                            placeholder="Ej. rewards/entrada-gratis.jpg"
                            autocomplete="off"
                        />

                        <p v-if="form.errors.image" class="form-error">
                            {{ form.errors.image }}
                        </p>

                        <p v-else class="form-help">
                            Introduce la ruta de la imagen asociada a la recompensa.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define si la recompensa estará disponible para realizar canjes."
                :icon="ToggleLeft"
            >
                <div class="form-grid">
                    <div class="form-field form-field-full">
                        <ActiveSwitch
                            v-model="form.is_active"
                            label="Recompensa activa"
                            description="Permite que los usuarios puedan seleccionar esta recompensa para realizar canjes."
                            :error="form.errors.is_active"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Información adicional -->
            <div class="form-info">
                <div class="form-info-icon">
                    <Check class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    Verifica el nombre, los puntos necesarios y las existencias antes de guardar.
                    Solo las recompensas activas estarán disponibles para nuevos canjes.
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
                    Guardar recompensa
                </AdminButton>
            </div>
        </form>
    </div>
</template>