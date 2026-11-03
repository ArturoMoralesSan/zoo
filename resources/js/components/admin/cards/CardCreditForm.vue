<script setup lang="ts">
import { Box, FileImage, Image, Link2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import AssetCreditFields from '@/components/admin/cards/AssetCreditFields.vue';
import { CREDIT_ROLES, DEFAULT_MODIFICATION_NOTES } from '@/lib/credits/types';
import type {
    AssetSourceOption,
    CreditAsset,
    CreditAssetOption,
    CreditForm,
    CreditRole,
    License,
} from '@/lib/credits/types';

/*
 * Formulario de un crédito de la tarjeta, en pasos:
 * 1. Recurso (ilustración, miniatura, modelo).
 * 2. Tipo de crédito (origen, basado en, incluye).
 * 3. Solo el origen: si se hizo a partir de otro recurso de la tarjeta
 *    (render del modelo, miniatura recortada de la ilustración…) o de un
 *    modelo de otro sitio.
 * 4. Origen, licencia y datos (AssetCreditFields).
 */
const props = withDefaults(
    defineProps<{
        assets: CreditAssetOption[];
        licenses: License[];
        sources: AssetSourceOption[];
        allowRestricted?: boolean;
        errors: Record<string, string | undefined>;
        // Al editar: el crédito y su licencia guardada.
        editingId?: number | null;
        storedLicenseId?: number | null;
    }>(),
    {
        allowRestricted: false,
        editingId: null,
        storedLicenseId: null,
    },
);

const credit = defineModel<CreditForm>({ required: true });

// "add_based_on_model": después de guardar, registrar el modelo de otro sitio.
const then = defineModel<string | null>('then', { default: null });

const ASSET_ICONS = { artwork: FileImage, thumbnail: Image, model: Box };

const currentAsset = computed(
    () =>
        props.assets.find((item) => item.value === credit.value.asset) ?? null,
);

/*
 * El origen es único por recurso: no se ofrece si ya hay otro.
 */
const originTaken = computed(
    () =>
        currentAsset.value?.credits.some(
            (item) => item.role === 'origin' && item.id !== props.editingId,
        ) ?? false,
);

const selectAsset = (asset: CreditAsset) => {
    credit.value.asset = asset;

    if (credit.value.role === 'origin' && originTaken.value) {
        credit.value.role = null;
    }

    credit.value.based_on_asset = null;
    basisChoice.value = 'none';
};

const selectRole = (role: CreditRole) => {
    credit.value.role = role;

    // El archivo final (origen) se recorta o escala para la tarjeta.
    if (role === 'origin' && credit.value.asset && props.editingId === null) {
        credit.value.modified = true;
        credit.value.modification_note ||=
            DEFAULT_MODIFICATION_NOTES[credit.value.asset];
    }

    if (role !== 'origin') {
        credit.value.based_on_asset = null;
        basisChoice.value = 'none';
    }
};

/*
 * Paso 3: de qué se hizo el recurso.
 */
type BasisChoice = 'none' | CreditAsset | 'other_model';

const basisChoice = ref<BasisChoice>(
    credit.value.based_on_asset ?? (then.value ? 'other_model' : 'none'),
);

const basisLabel = (target: CreditAsset): string => {
    if (target === 'model') {
        return 'Es un render del modelo 3D de esta tarjeta';
    }

    if (target === 'artwork') {
        return credit.value.asset === 'model'
            ? 'El modelo se generó a partir de la ilustración de esta tarjeta'
            : 'Se recortó o adaptó de la ilustración de esta tarjeta';
    }

    return 'Se hizo a partir de la miniatura de esta tarjeta';
};

const basisOptions = computed(() => {
    const asset = credit.value.asset;

    if (!asset) {
        return [];
    }

    const options: {
        value: BasisChoice;
        label: string;
        disabled?: boolean;
        hint?: string;
    }[] = [{ value: 'none', label: 'No, es un archivo independiente' }];

    props.assets
        .filter((item) => item.value !== asset)
        .forEach((item) =>
            options.push({
                value: item.value,
                label: basisLabel(item.value),
                disabled: !item.has_file,
                hint: item.has_file
                    ? undefined
                    : 'La tarjeta no tiene este recurso',
            }),
        );

    // Las imágenes también pueden ser renders de un modelo de otro sitio.
    if (asset !== 'model') {
        options.push({
            value: 'other_model',
            label: 'Es un render de un modelo 3D de otro sitio (Sketchfab, Meshy…)',
            hint: 'Después de guardar, registrarás ese modelo como "Basado en".',
        });
    }

    return options;
});

watch(basisChoice, (choice) => {
    credit.value.based_on_asset =
        choice === 'none' || choice === 'other_model' ? null : choice;

    then.value = choice === 'other_model' ? 'add_based_on_model' : null;

    // Un render lo hace el equipo: se sugiere "Propio".
    if (
        (choice === 'model' || choice === 'other_model') &&
        credit.value.source_name === null
    ) {
        credit.value.source_name = 'own';
        credit.value.license_id =
            props.licenses.find((item) => item.slug === 'proprietary')?.id ??
            null;
    }
});

const basisAsset = computed(
    () =>
        props.assets.find(
            (item) => item.value === credit.value.based_on_asset,
        ) ?? null,
);

/*
 * Obligaciones que hereda de la licencia del recurso en que se basa.
 */
const basisLicense = computed(() => {
    const origin = basisAsset.value?.credits.find(
        (item) => item.role === 'origin',
    );

    return (
        props.licenses.find((item) => item.id === origin?.license_id) ?? null
    );
});

const ready = computed(
    () => credit.value.asset !== null && credit.value.role !== null,
);
</script>

<template>
    <div class="space-y-5">
        <!-- 1. Recurso -->
        <AdminCard
            title="1. Recurso"
            description="¿A qué archivo de la tarjeta pertenece este crédito?"
            :icon="FileImage"
        >
            <div class="grid gap-3 sm:grid-cols-3">
                <label
                    v-for="item in assets"
                    :key="item.value"
                    class="flex items-center gap-3 rounded-xl border p-3.5 transition"
                    :class="[
                        credit.asset === item.value
                            ? 'border-[#187744] bg-[#F2F7F4] text-[#187744]'
                            : 'border-[#C8DCCF] bg-white text-[#435D4C]',
                        item.has_file
                            ? 'cursor-pointer'
                            : 'cursor-not-allowed opacity-50',
                    ]"
                >
                    <input
                        type="radio"
                        class="sr-only"
                        name="asset"
                        :value="item.value"
                        :checked="credit.asset === item.value"
                        :disabled="!item.has_file"
                        @change="selectAsset(item.value)"
                    />
                    <component :is="ASSET_ICONS[item.value]" :size="20" />
                    <div>
                        <p class="text-sm font-semibold">{{ item.label }}</p>
                        <p class="text-xs text-[#718477]">
                            {{
                                item.has_file
                                    ? `${item.credits.length} ${item.credits.length === 1 ? 'crédito' : 'créditos'}`
                                    : 'Sin archivo'
                            }}
                        </p>
                    </div>
                </label>
            </div>

            <p v-if="errors.asset" class="form-error mt-2">
                {{ errors.asset }}
            </p>
        </AdminCard>

        <!-- 2. Tipo de crédito -->
        <AdminCard
            v-if="credit.asset"
            title="2. Tipo de crédito"
            description="Un recurso tiene un origen y, si hace falta, las obras en que se basa y el material que incluye."
            :icon="Link2"
        >
            <div class="grid gap-3 sm:grid-cols-3">
                <label
                    v-for="item in CREDIT_ROLES"
                    :key="item.value"
                    class="flex flex-col gap-1 rounded-xl border p-3.5 transition"
                    :class="[
                        credit.role === item.value
                            ? 'border-[#187744] bg-[#F2F7F4]'
                            : 'border-[#C8DCCF] bg-white',
                        item.value === 'origin' && originTaken
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer',
                    ]"
                >
                    <input
                        type="radio"
                        class="sr-only"
                        name="role"
                        :value="item.value"
                        :checked="credit.role === item.value"
                        :disabled="item.value === 'origin' && originTaken"
                        @change="selectRole(item.value)"
                    />
                    <span class="text-sm font-semibold text-[#244334]">
                        {{ item.label }}
                    </span>
                    <span class="text-xs text-[#718477]">
                        {{
                            item.value === 'origin' && originTaken
                                ? 'Este recurso ya tiene origen: edítalo desde la lista.'
                                : item.description
                        }}
                    </span>
                </label>
            </div>

            <p v-if="errors.role" class="form-error mt-2">
                {{ errors.role }}
            </p>
        </AdminCard>

        <!-- 3. Se hizo a partir de… (solo el origen) -->
        <AdminCard
            v-if="credit.asset && credit.role === 'origin'"
            title="3. ¿Se hizo a partir de otro recurso?"
            description="Por ejemplo, una miniatura que es un render del modelo 3D, o un modelo generado a partir de la ilustración."
            :icon="Box"
        >
            <div class="space-y-2">
                <label
                    v-for="option in basisOptions"
                    :key="option.value"
                    class="flex items-start gap-3 rounded-xl border p-3 transition"
                    :class="[
                        basisChoice === option.value
                            ? 'border-[#187744] bg-[#F2F7F4]'
                            : 'border-[#C8DCCF] bg-white',
                        option.disabled
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer',
                    ]"
                >
                    <input
                        v-model="basisChoice"
                        type="radio"
                        class="mt-0.5 accent-[#187744]"
                        :value="option.value"
                        :disabled="option.disabled"
                    />
                    <span>
                        <span class="block text-sm font-medium text-[#244334]">
                            {{ option.label }}
                        </span>
                        <span
                            v-if="option.hint"
                            class="block text-xs text-[#718477]"
                        >
                            {{ option.hint }}
                        </span>
                    </span>
                </label>
            </div>

            <!-- Créditos del recurso en que se basa -->
            <div
                v-if="basisAsset"
                class="mt-4 rounded-lg border border-[#DCE7DF] bg-[#F7FAF8] p-3"
            >
                <p class="text-xs font-semibold text-[#435D4C]">
                    Créditos de {{ basisAsset.label.toLowerCase() }} (también
                    aparecen en la app)
                </p>

                <ul
                    v-if="basisAsset.credits.length"
                    class="mt-1 space-y-1 text-sm text-[#244334]"
                >
                    <li v-for="item in basisAsset.credits" :key="item.id">
                        {{ item.attribution }}
                    </li>
                </ul>

                <p v-else class="form-error mt-1">
                    {{ basisAsset.label }} aún no tiene licencia: regístrala
                    también para poder activar la tarjeta.
                </p>

                <p
                    v-if="basisLicense?.share_alike"
                    class="mt-2 text-xs text-sky-800"
                >
                    {{ basisAsset.label }} es {{ basisLicense.short_name }}:
                    este recurso es una obra derivada y también debe compartirse
                    con esa licencia.
                </p>
            </div>

            <p v-if="errors.based_on_asset" class="form-error mt-2">
                {{ errors.based_on_asset }}
            </p>
        </AdminCard>

        <!-- 4. Origen, licencia y datos -->
        <AssetCreditFields
            v-if="ready"
            v-model="credit"
            :asset="credit.asset!"
            :role="credit.role!"
            :licenses="licenses"
            :sources="sources"
            :allow-restricted="allowRestricted"
            :stored-license-id="storedLicenseId"
            :errors="errors"
        />
    </div>
</template>
