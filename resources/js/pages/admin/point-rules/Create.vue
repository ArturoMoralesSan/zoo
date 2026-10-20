<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Coins,
    Hash,
    Save,
    Text,
    ToggleLeft,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

const form = useForm({
    type: '',
    name: '',
    points: 10,
    description: '',
    is_active: true,
});

const submit = () => {
    form.post(admin.pointRules.store().url);
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Reglas de puntos',
                href: admin.pointRules.index(),
            },
            {
                title: 'Nueva regla de puntos',
                href: admin.pointRules.create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nueva regla de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nueva regla de puntos"
            description="Define una acción que otorgará o quitará puntos."
            section="Recompensas"
            :icon="Coins"
            :action-icon="ArrowLeft"
            action-label="Volver a reglas de puntos"
            :action-href="admin.pointRules.index().url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Configura el identificador, el nombre y los puntos que se aplicarán cuando ocurra una acción."
                :icon="Coins"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Tipo -->
                    <div class="form-field">
                        <label for="type" class="form-label">
                            Tipo
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="type"
                            v-model="form.type"
                            type="text"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.type,
                            }"
                            placeholder="Ej. ticket_purchase"
                            autocomplete="off"
                            required
                        />

                        <p v-if="form.errors.type" class="form-error">
                            {{ form.errors.type }}
                        </p>

                        <p v-else class="form-help">
                            Identificador interno de la regla. Utiliza un
                            código descriptivo y consistente.
                        </p>
                    </div>

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
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.name,
                            }"
                            placeholder="Ej. Compra de boleto"
                            autocomplete="off"
                            required
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>

                        <p v-else class="form-help">
                            Nombre con el que se identificará la regla en el
                            sistema de recompensas.
                        </p>
                    </div>

                    <!-- Puntos -->
                    <div class="form-field">
                        <label for="points" class="form-label">
                            Puntos
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="points"
                            v-model.number="form.points"
                            type="number"
                            step="1"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.points,
                            }"
                            required
                        />

                        <p v-if="form.errors.points" class="form-error">
                            {{ form.errors.points }}
                        </p>

                        <p v-else class="form-help">
                            Usa valores positivos para otorgar puntos y
                            negativos para descontarlos.
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="form-field md:col-span-2">
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
                            placeholder="Describe cuándo y por qué se aplican estos puntos..."
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>

                        <p v-else class="form-help">
                            Agrega información adicional sobre el propósito
                            de la regla.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define si la regla estará disponible para aplicarse en el sistema de recompensas."
                :icon="ToggleLeft"
            >
                <div class="grid grid-cols-1 gap-5">
                    <ActiveSwitch
                        v-model="form.is_active"
                        label="Regla activa"
                        description="Permite que esta regla se aplique cuando ocurra la acción correspondiente."
                        :error="form.errors.is_active"
                    />
                </div>
            </AdminCard>

            <!-- Información adicional -->
            <div
                class="flex items-start gap-3 rounded-xl border border-[#DCE7DF] bg-white p-4"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EAF3ED] text-[#187744]"
                >
                    <Check class="h-5 w-5" />
                </div>

                <div>
                    <p class="text-sm font-semibold text-[#244334]">
                        Antes de guardar
                    </p>

                    <p class="mt-1 text-sm leading-6 text-[#718477]">
                        Verifica que el tipo sea correcto y que el valor de
                        puntos corresponda a la acción. Los valores negativos
                        permiten definir reglas que descuentan puntos.
                    </p>
                </div>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.pointRules.index().url"
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
                    Guardar regla de puntos
                </AdminButton>
            </div>
        </form>
    </div>
</template>