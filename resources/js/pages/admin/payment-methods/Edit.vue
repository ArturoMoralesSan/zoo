<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CreditCard,
    Hash,
    Info,
    Save,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{
    paymentMethod: PaymentMethod;
}>();

const form = useForm({
    name: props.paymentMethod.name,
    code: props.paymentMethod.code,
    description: props.paymentMethod.description ?? '',
    is_active: props.paymentMethod.is_active,
    sort_order: props.paymentMethod.sort_order,
});

const submit = () => {
    form.put(
        admin.paymentMethods.update(props.paymentMethod.id).url,
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Métodos de pago',
                href: admin.paymentMethods.index(),
            },
            {
                title: 'Editar método de pago',
                href: '#',
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar método de pago" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar método de pago"
            :description="`Actualiza la información de ${paymentMethod.name}.`"
            section="Taquilla"
            :icon="CreditCard"
            :action-icon="ArrowLeft"
            action-label="Volver a métodos de pago"
            :action-href="admin.paymentMethods.index().url"
        />

        <form
            class="space-y-5"
            @submit.prevent="submit"
        >
            <!-- Información general -->
            <AdminCard
                title="Información general"
                description="Actualiza los datos principales del método de pago."
                :icon="CreditCard"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
                            placeholder="Ej. Efectivo"
                            autocomplete="off"
                            required
                        />

                        <p v-if="form.errors.name" class="form-error">
                            {{ form.errors.name }}
                        </p>

                        <p v-else class="form-help">
                            Nombre con el que se identificará el método de pago.
                        </p>
                    </div>

                    <!-- Código -->
                    <div class="form-field">
                        <label for="code" class="form-label">
                            Código
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="form-input"
                            :class="{
                                'form-input-error': form.errors.code,
                            }"
                            placeholder="Ej. cash"
                            autocomplete="off"
                            required
                        />

                        <p v-if="form.errors.code" class="form-error">
                            {{ form.errors.code }}
                        </p>

                        <p v-else class="form-help">
                            El código debe ser único y solo puede contener
                            letras, números, guiones y guiones bajos.
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
                            placeholder="Describe el método de pago..."
                        />

                        <p
                            v-if="form.errors.description"
                            class="form-error"
                        >
                            {{ form.errors.description }}
                        </p>

                        <p v-else class="form-help">
                            Agrega información adicional sobre este método.
                        </p>
                    </div>
                </div>
            </AdminCard>

            <!-- Configuración -->
            <AdminCard
                title="Configuración"
                description="Define el orden de visualización y la disponibilidad del método de pago."
                :icon="Hash"
            >
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Orden -->
                    <div class="form-field">
                        <label for="sort_order" class="form-label">
                            Orden de visualización
                        </label>

                        <input
                            id="sort_order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="form-input"
                            :class="{
                                'form-input-error':
                                    form.errors.sort_order,
                            }"
                        />

                        <p
                            v-if="form.errors.sort_order"
                            class="form-error"
                        >
                            {{ form.errors.sort_order }}
                        </p>

                        <p v-else class="form-help">
                            Define el orden en que aparecerá el método de pago.
                        </p>
                    </div>

                    <!-- Estado -->
                    <div class="flex items-center">
                        <ActiveSwitch
                            v-model="form.is_active"
                            label="Método activo"
                            description="Los métodos inactivos no estarán disponibles para nuevas órdenes."
                            :error="form.errors.is_active"
                        />
                    </div>
                </div>
            </AdminCard>

            <!-- Información adicional -->
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
                        Antes de actualizar
                    </p>

                    <p class="mt-1 text-sm leading-6 text-[#718477]">
                        Los cambios se aplicarán al actualizar el método de
                        pago. Verifica que el código siga siendo compatible con
                        la lógica que utiliza tu sistema para procesar los pagos.
                    </p>
                </div>
            </div>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.paymentMethods.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    variant="primary"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Actualizando..."
                >
                    Actualizar método de pago
                </AdminButton>
            </div>
        </form>
    </div>
</template>