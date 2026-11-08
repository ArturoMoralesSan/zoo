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
import { emptyCredit } from '@/lib/credits/types';
import type {
    AssetSourceOption,
    AssetSourceValue,
    CreditAsset,
    CreditAssetOption,
    CreditRole,
    License,
} from '@/lib/credits/types';
import admin from '@/routes/admin';

const props = defineProps<{
    card: CardSummary;
    assets: CreditAssetOption[];
    licenses: License[];
    assetSources: AssetSourceOption[];
    allowRestrictedLicenses: boolean;
    // Recurso, tipo y origen elegidos desde la lista.
    preset: {
        asset: CreditAsset | null;
        role: CreditRole | null;
        source: AssetSourceValue | null;
    };
}>();

const presetAsset = props.assets.find(
    (item) => item.value === props.preset.asset && item.has_file,
);

const form = useForm({
    ...emptyCredit(presetAsset?.value ?? null, props.preset.role),
    source_name: props.preset.source,
    then: null as string | null,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const submit = () => {
    form.post(admin.cards.credits.store(props.card.id).url, {
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
                title: 'Créditos',
                href: admin.cards.index(),
            },
            {
                title: 'Nuevo crédito',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo crédito" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Nuevo crédito"
            :description="`Registra de dónde salió un recurso de la tarjeta ${card.name} y con qué licencia.`"
            section="Créditos de tarjeta"
            :icon="Copyright"
            :action-icon="ArrowLeft"
            action-label="Volver a los créditos"
            :action-href="admin.cards.credits.index(card.id).url"
        />

        <CardContextInfo
            :card="card"
            message="El crédito se asociará a esta tarjeta."
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
                    :disabled="!form.asset || !form.role"
                    loading-text="Guardando..."
                >
                    {{
                        form.then
                            ? 'Guardar y registrar el modelo'
                            : 'Guardar crédito'
                    }}
                </AdminButton>
            </div>
        </form>
    </div>
</template>
