<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, QrCode, RefreshCw, Save } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import CardQrImage from '@/components/admin/cards/CardQrImage.vue';
import QrCodeFormFields from '@/components/admin/cards/QrCodeFormFields.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import {
    downloadQrPng,
    formatQrDate,
    qrFilename,
    toDateTimeLocal,
} from '@/lib/cardQrCode';
import type { CardQrCode, QrExpiryOption } from '@/lib/cardQrCode';
import admin from '@/routes/admin';

const props = defineProps<{
    card: { id: number; name: string };
    qrCode: CardQrCode;
}>();

const form = useForm({
    label: props.qrCode.label ?? '',
    is_active: props.qrCode.is_active,
    expires: (props.qrCode.expires_at ? 'date' : 'never') as QrExpiryOption,
    expires_at: toDateTimeLocal(props.qrCode.expires_at),
});

const isRegenerating = ref(false);

const routeParams = () => ({
    card: props.card.id,
    qrCode: props.qrCode.id,
});

const submit = () => {
    form.put(admin.cards.qrCodes.update(routeParams()).url, {
        preserveScroll: true,
    });
};

const download = () =>
    downloadQrPng(props.qrCode.token, qrFilename(props.card.name, props.qrCode.id));

const regenerate = async () => {
    const result = await Swal.fire({
        title: '¿Regenerar código QR?',
        text: 'Se generará un código nuevo. Los QR impresos con el código actual dejarán de funcionar.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, regenerar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.post(
        admin.cards.qrCodes.regenerate(routeParams()).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (isRegenerating.value = true),
            onFinish: () => (isRegenerating.value = false),
        },
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
                title: 'Tarjetas',
                href: admin.cards.index(),
            },
            {
                title: 'Códigos QR',
                href: admin.cards.index(),
            },
            {
                title: 'Editar QR',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar código QR" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar código QR"
            :description="`Código QR de la tarjeta ${card.name}.`"
            section="Colección"
            :icon="QrCode"
            :action-icon="ArrowLeft"
            action-label="Volver a los QR"
            :action-href="admin.cards.qrCodes.index(card.id).url"
        />

        <!-- Vista previa -->
        <AdminCard
            title="Código QR"
            description="Descárgalo en PNG para imprimirlo y colocarlo en el zoológico."
            :icon="QrCode"
        >
            <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                <CardQrImage
                    :token="qrCode.token"
                    :size="200"
                    :alt="`Código QR ${qrCode.label ?? qrCode.id}`"
                />

                <div class="w-full min-w-0 flex-1 space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border border-[#E5ECE7] p-3">
                            <p class="text-xs text-[#718477]">Escaneos</p>
                            <p class="mt-1 text-sm font-semibold text-[#244334]">
                                {{ qrCode.scans_count.toLocaleString('es-MX') }}
                            </p>
                        </div>

                        <div class="rounded-lg border border-[#E5ECE7] p-3">
                            <p class="text-xs text-[#718477]">Último escaneo</p>
                            <p class="mt-1 text-sm font-semibold text-[#244334]">
                                {{ formatQrDate(qrCode.last_scanned_at) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <AdminButton
                            variant="secondary"
                            :icon="Download"
                            @click="download"
                        >
                            Descargar PNG
                        </AdminButton>

                        <AdminButton
                            variant="secondary"
                            :icon="RefreshCw"
                            :loading="isRegenerating"
                            loading-text="Regenerando..."
                            @click="regenerate"
                        >
                            Regenerar código
                        </AdminButton>
                    </div>

                    <p class="text-xs text-[#718477]">
                        Regenera el código si un QR impreso se extravió o fue
                        copiado. El anterior dejará de funcionar.
                    </p>
                </div>
            </div>
        </AdminCard>

        <form class="space-y-5" @submit.prevent="submit">
            <QrCodeFormFields
                v-model:label="form.label"
                v-model:is-active="form.is_active"
                v-model:expires="form.expires"
                v-model:expires-at="form.expires_at"
                :errors="form.errors"
            />

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
                    Guardar cambios
                </AdminButton>
            </div>
        </form>
    </div>
</template>
