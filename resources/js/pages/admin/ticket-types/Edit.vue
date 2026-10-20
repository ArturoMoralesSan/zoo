<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Info,
    Save,
    Ticket,
} from 'lucide-vue-next';

import ActiveSwitch from '@/components/admin/ActiveSwitch.vue';
import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import admin from '@/routes/admin';

interface TicketType {
    id: number;
    name: string;
    price: number | string;
    description: string | null;
    is_active: boolean;
}

const props = defineProps<{
    ticketType: TicketType;
}>();

const form = useForm({
    _method: 'put',
    name: props.ticketType.name,
    price: String(props.ticketType.price),
    description: props.ticketType.description ?? '',
    is_active: props.ticketType.is_active,
});

const submit = () => {
    form.post(
        admin.ticketTypes.update(props.ticketType.id).url,
    );
};
</script>

<template>
    <Head title="Editar tipo de boleto" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar tipo de boleto"
            description="Modifica la información y tarifa del tipo de boleto."
            section="Taquilla"
            :icon="Ticket"
            :action-icon="ArrowLeft"
            action-label="Volver a tipos de boleto"
            :action-href="admin.ticketTypes.index().url"
        />

        <form
            class="flex flex-col gap-5"
            @submit.prevent="submit"
        >
            <AdminCard
                title="Información del boleto"
                description="Actualiza los datos principales, el precio y la descripción del tipo de boleto."
                :icon="Info"
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
                            placeholder="Ej. Adulto"
                            required
                            :class="[
                                'form-input',
                                form.errors.name && 'form-input-error',
                            ]"
                        />

                        <p
                            v-if="form.errors.name"
                            class="form-error"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Precio -->
                    <div class="form-field">
                        <label for="price" class="form-label">
                            Precio
                            <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">
                            <span
                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-[#718477]"
                            >
                                $
                            </span>

                            <input
                                id="price"
                                v-model="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="80.00"
                                required
                                :class="[
                                    'form-input pl-8',
                                    form.errors.price && 'form-input-error',
                                ]"
                            />
                        </div>

                        <p class="form-help">
                            Precio expresado en pesos mexicanos (MXN).
                        </p>

                        <p
                            v-if="form.errors.price"
                            class="form-error"
                        >
                            {{ form.errors.price }}
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
                            placeholder="Describe este tipo de boleto..."
                            :class="[
                                'form-textarea',
                                form.errors.description &&
                                    'form-textarea-error',
                            ]"
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

            <!-- Estado -->
            <AdminCard
                title="Disponibilidad"
                description="Administra si este tipo de boleto puede utilizarse en las ventas."
                :icon="Ticket"
            >
                <ActiveSwitch
                    v-model="form.is_active"
                    label="Tipo de boleto activo"
                    description="Cuando está activo, puede seleccionarse al registrar ventas de taquilla."
                    :error="form.errors.is_active"
                />
            </AdminCard>

            <!-- Acciones -->
            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:items-center sm:justify-end"
            >
                <CancelButton
                    :href="admin.ticketTypes.index().url"
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
                    Actualizar tipo de boleto
                </AdminButton>
            </div>
        </form>
    </div>
</template>