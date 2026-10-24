<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, QrCode, Save } from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import QrCodeFormFields from '@/components/admin/cards/QrCodeFormFields.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import type { QrExpiryOption } from '@/lib/cardQrCode';
import admin from '@/routes/admin';

const props = defineProps<{
    card: { id: number; name: string };
}>();

const form = useForm({
    label: '',
    is_active: true,
    expires: 'never' as QrExpiryOption,
    expires_at: '',
});

const submit = () => {
    form.post(admin.cards.qrCodes.store(props.card.id).url, {
        preserveScroll: true,
    });
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Tarjetas',
                href: admin.cards.index(),
            },
            {
                title: 'Códigos QR',
                href: admin.cards.index(),
            },
            {
                title: 'Nuevo QR',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo código QR" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo código QR"
            :description="`Genera un código QR para iniciar duelos por la tarjeta ${card.name}.`"
            section="Colección"
            :icon="QrCode"
            :action-icon="ArrowLeft"
            action-label="Volver a los QR"
            :action-href="admin.cards.qrCodes.index(card.id).url"
        />

        <form class="space-y-5" @submit.prevent="submit">
            <QrCodeFormFields
                v-model:label="form.label"
                v-model:is-active="form.is_active"
                v-model:expires="form.expires"
                v-model:expires-at="form.expires_at"
                :errors="form.errors"
            />

            <div class="form-info">
                <div class="form-info-icon">
                    <Info class="h-5 w-5" />
                </div>

                <p class="form-info-text">
                    El código se genera automáticamente al guardar. Después podrás
                    verlo en la tabla y descargarlo en PNG para imprimirlo.
                </p>
            </div>

            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.cards.qrCodes.index(card.id).url"
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
                    Crear código QR
                </AdminButton>
            </div>
        </form>
    </div>
</template>
