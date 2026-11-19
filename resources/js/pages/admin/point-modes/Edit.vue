<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange, Info, Save, Zap } from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import PointModeForm from '@/components/admin/point-modes/PointModeForm.vue';
import { formRules } from '@/lib/pointModes';
import type { PointMode, PointRuleRow } from '@/lib/pointModes';
import admin from '@/routes/admin';

const props = defineProps<{
    mode: PointMode;
    rules: PointRuleRow[];
}>();

const form = useForm({
    name: props.mode.name,
    description: props.mode.description ?? '',
    is_public: props.mode.is_public,
    public_title: props.mode.public_title ?? '',
    public_message: props.mode.public_message ?? '',
    icon: props.mode.icon,
    color: props.mode.color,
    rules: formRules(props.rules, props.mode.overrides),
});

const submit = () => {
    form.put(admin.pointModes.update(props.mode.id).url, {
        preserveScroll: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: admin.dashboard() },
            { title: 'Modos de puntos', href: admin.pointModes.index() },
            { title: 'Editar modo', href: admin.pointModes.index() },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${mode.name}`" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            :title="`Editar ${mode.name}`"
            description="Cambia cuántos puntos da cada regla en este modo."
            section="Gamificación"
            :icon="CalendarRange"
            :action-icon="ArrowLeft"
            action-label="Volver a los modos"
            :action-href="admin.pointModes.index().url"
        />

        <div
            v-if="mode.is_active"
            class="flex items-start gap-3 rounded-2xl border border-[#BFE3CB] bg-[#E2F3E8] px-5 py-4 text-sm text-[#244334]"
        >
            <Zap
                class="mt-0.5 h-5 w-5 shrink-0 text-[#187744]"
                aria-hidden="true"
            />
            <p>
                <span class="font-semibold">Este modo está activo.</span>
                Al guardar, los puntos nuevos usarán estos valores de inmediato.
            </p>
        </div>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <PointModeForm
                v-model:name="form.name"
                v-model:description="form.description"
                v-model:is-public="form.is_public"
                v-model:public-title="form.public_title"
                v-model:public-message="form.public_message"
                v-model:icon="form.icon"
                v-model:color="form.color"
                v-model:items="form.rules"
                :rules="rules"
                :errors="form.errors"
            />

            <div class="form-info">
                <div class="form-info-icon">
                    <Info class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    Los puntos ya otorgados no cambian; solo afecta a los que se
                    den después.
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
                    :disabled="!form.isDirty"
                >
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>
