<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Copyright, Save } from 'lucide-vue-next';
import { computed } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import CancelButton from '@/components/admin/CancelButton.vue';
import CardContextInfo from '@/components/admin/cards/CardContextInfo.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import SpeciesImageCreditForm from '@/components/admin/species/SpeciesImageCreditForm.vue';
import type {
    SpeciesCreditImage,
    SpeciesCreditSummary,
} from '@/lib/credits/speciesCredits';
import { creditForm } from '@/lib/credits/types';
import type {
    AssetSourceOption,
    License,
    StoredCredit,
} from '@/lib/credits/types';
import admin from '@/routes/admin';

const props = defineProps<{
    species: SpeciesCreditSummary;
    image: SpeciesCreditImage;
    credit: StoredCredit;
    licenses: License[];
    assetSources: AssetSourceOption[];
    allowRestrictedLicenses: boolean;
}>();

const form = useForm({
    ...creditForm(props.credit),
    sort_order: props.credit.sort_order,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const backUrl = computed(
    () =>
        `${admin.species.credits.index(props.species.id).url}#image-${props.image.id}`,
);

const submit = () => {
    form.put(
        admin.species.images.credits.update({
            species: props.species.id,
            image: props.image.id,
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
                title: 'Especies',
                href: admin.species.index(),
            },
            {
                title: 'Créditos',
                href: admin.species.index(),
            },
            {
                title: 'Editar crédito',
                href: admin.species.index(),
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
            :description="`Actualiza un crédito de una imagen de ${species.common_name}.`"
            section="Créditos de especie"
            :icon="Copyright"
            :action-icon="ArrowLeft"
            action-label="Volver a los créditos"
            :action-href="backUrl"
        />

        <CardContextInfo
            :card="{ name: species.common_name }"
            :message="`${image.label}. Los cambios se verán en la pantalla de créditos de la app.`"
        />

        <form class="form-container" @submit.prevent="submit">
            <SpeciesImageCreditForm
                v-model="form"
                compact
                :image="image"
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
                    :href="backUrl"
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
