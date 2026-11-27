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
import { emptyCredit } from '@/lib/credits/types';
import type {
    AssetSourceOption,
    AssetSourceValue,
    CreditRole,
    License,
} from '@/lib/credits/types';
import admin from '@/routes/admin';

const props = defineProps<{
    species: SpeciesCreditSummary;
    image: SpeciesCreditImage;
    licenses: License[];
    assetSources: AssetSourceOption[];
    allowRestrictedLicenses: boolean;
    // Tipo y origen elegidos desde la lista.
    preset: {
        role: CreditRole | null;
        source: AssetSourceValue | null;
    };
}>();

// El origen es único: si ya lo tiene, se pregunta el tipo.
const presetRole =
    props.preset.role === 'origin' && props.image.has_origin
        ? null
        : props.preset.role;

const form = useForm({
    ...emptyCredit('image', presetRole),
    source_name: props.preset.source,
});

const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const backUrl = computed(
    () =>
        `${admin.species.credits.index(props.species.id).url}#image-${props.image.id}`,
);

const submit = () => {
    form.post(
        admin.species.images.credits.store({
            species: props.species.id,
            image: props.image.id,
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
                title: 'Nuevo crédito',
                href: admin.species.index(),
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
            :description="`Quién hizo esta imagen de ${species.common_name} y con qué licencia.`"
            section="Créditos de especie"
            :icon="Copyright"
            :action-icon="ArrowLeft"
            action-label="Volver a los créditos"
            :action-href="backUrl"
        />

        <CardContextInfo
            :card="{ name: species.common_name }"
            :message="`El crédito se asociará a: ${image.label}.`"
        />

        <form class="form-container" @submit.prevent="submit">
            <SpeciesImageCreditForm
                v-model="form"
                :compact="presetRole !== null"
                :image="image"
                :licenses="licenses"
                :sources="assetSources"
                :allow-restricted="allowRestrictedLicenses"
                :errors="errors"
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
                    :disabled="!form.role"
                    loading-text="Guardando..."
                >
                    Guardar crédito
                </AdminButton>
            </div>
        </form>
    </div>
</template>
