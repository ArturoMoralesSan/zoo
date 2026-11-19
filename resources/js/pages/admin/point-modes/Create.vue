<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange, Info, Save } from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PointModeForm from '@/components/admin/point-modes/PointModeForm.vue';
import { formRules } from '@/lib/pointModes';
import type { PointRuleRow } from '@/lib/pointModes';
import admin from '@/routes/admin';

const props = defineProps<{
    rules: PointRuleRow[];
}>();

const form = useForm({
    name: '',
    description: '',
    is_public: false,
    public_title: '',
    public_message: '',
    rules: formRules(props.rules),
});

const submit = () => {
    form.post(admin.pointModes.store().url, {
        preserveScroll: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: admin.dashboard() },
            { title: 'Modos de puntos', href: admin.pointModes.index() },
            { title: 'Nuevo modo', href: admin.pointModes.create() },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo modo de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo modo de puntos"
            description="Define cuántos puntos da cada regla durante una temporada o evento."
            section="Gamificación"
            :icon="CalendarRange"
            :action-icon="ArrowLeft"
            action-label="Volver a los modos"
            :action-href="admin.pointModes.index().url"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <PointModeForm
                v-model:name="form.name"
                v-model:description="form.description"
                v-model:is-public="form.is_public"
                v-model:public-title="form.public_title"
                v-model:public-message="form.public_message"
                v-model:items="form.rules"
                :rules="rules"
                :errors="form.errors"
            />

            <div class="form-info">
                <div class="form-info-icon">
                    <Info class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    El modo se guarda inactivo. Actívalo desde la lista de modos
                    cuando empiece la temporada; los puntos ya otorgados no
                    cambian.
                </p>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.pointModes.index().url"
                    :icon="ArrowLeft"
                    label="Cancelar"
                />

                <AdminButton
                    type="submit"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Guardando..."
                >
                    Crear modo
                </AdminButton>
            </div>
        </form>
    </div>
</template>
