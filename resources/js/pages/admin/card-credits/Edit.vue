<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Copyright, Save } from 'lucide-vue-next';
import { computed } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import CardCreditForm from '@/components/admin/cards/CardCreditForm.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import type { CardSummary } from '@/lib/cardQrCode';
import { creditForm } from '@/lib/credits/types';
import type {
    AssetSourceOption,
    CreditAssetOption,
    License,
    StoredCredit,
} from '@/lib/credits/types';
import admin from '@/routes/admin';

const props = defineProps<{
    card: CardSummary;
    credit: StoredCredit;
    assets: CreditAssetOption[];
    licenses: License[];
    assetSources: AssetSourceOption[];
    allowRestrictedLicenses: boolean;
}>();

const form = useForm({
    ...creditForm(props.credit),
    sort_order: props.credit.sort_order,
    then: null as string | null,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const submit = () => {
    form.put(
        admin.cards.credits.update({
            card: props.card.id,
            credit: props.credit.id,
        }).url,
        { preserveScroll: true },
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
                title: 'Créditos',
                href: admin.cards.index(),
            },
            {
                title: 'Editar crédito',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Editar crédito" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Editar crédito"
            :description="`Actualiza un crédito de la tarjeta ${card.name}.`"
            section="Créditos de tarjeta"
            :icon="Copyright"
            :action-icon="ArrowLeft"
            action-label="Volver a los créditos"
            :action-href="admin.cards.credits.index(card.id).url"
        />

        <CardContextInfo
            :card="card"
            message="Los cambios se verán en la pantalla de créditos de la app."
        />

        <form class="form-container" @submit.prevent="submit">
            <CardCreditForm
                v-model="form"
                v-model:then="form.then"
                :assets="assets"
                :licenses="licenses"
                :sources="assetSources"
                :allow-restricted="allowRestrictedLicenses"
                :errors="errors"
                :editing-id="credit.id"
                :stored-license-id="credit.license_id"
            />

            <div
                class="flex flex-col-reverse gap-3 border-t border-[#DCE7DF] pt-5 sm:flex-row sm:justify-end"
            >
                <CancelButton
                    :href="admin.cards.credits.index(card.id).url"
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
