<script setup lang="ts">
import {
    Box,
    Check,
    FileImage,
    GitBranch,
    Image,
    Link2,
    Pencil,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import AssetCreditFields from '@/components/admin/cards/AssetCreditFields.vue';
import {
    CREDIT_ROLES,
    DEFAULT_MODIFICATION_NOTES,
    creditRole,
} from '@/lib/credits/types';
import type {
    AssetSourceOption,
    CreditAsset,
    CreditAssetOption,
    CreditForm,
    CreditRole,
    License,
} from '@/lib/credits/types';

/*
 * Formulario de un crédito de la tarjeta:
 * 1. Archivo (ilustración, miniatura, modelo) y tipo de crédito. Si ya
 *    vienen elegidos desde la lista ("compact"), se resumen en una barra.
 * 2. Solo el origen: de dónde salió el archivo. Si se hizo a partir de otro
 *    archivo de la tarjeta (render del modelo, recorte de la ilustración…)
 *    o de un modelo de otro sitio, se pregunta aquí.
 * 3. Dónde se consiguió, licencia y datos (AssetCreditFields).
 */
const props = withDefaults(
    defineProps<{
        assets: CreditAssetOption[];
        licenses: License[];
        sources: AssetSourceOption[];
        allowRestricted?: boolean;
        errors: Record<string, string | undefined>;
        // Archivo y tipo ya elegidos: se muestran resumidos.
        compact?: boolean;
        // Al editar: el crédito y su licencia guardada.
        editingId?: number | null;
        storedLicenseId?: number | null;
    }>(),
    {
        allowRestricted: false,
        compact: false,
        editingId: null,
        storedLicenseId: null,
    },
);

const credit = defineModel<CreditForm>({ required: true });

// "add_based_on_model": después de guardar, registrar el modelo de otro sitio.
const then = defineModel<string | null>('then', { default: null });

const ASSET_ICONS = {
    artwork: FileImage,
    thumbnail: Image,
    model: Box,
    image: Image,
};

// Los errores de archivo o tipo obligan a mostrar los selectores.
const showSelectors = ref(
    !props.compact || Boolean(props.errors.asset || props.errors.role),
);

const currentAsset = computed(
    () =>
        props.assets.find((item) => item.value === credit.value.asset) ?? null,
);

/*
 * El origen es único por archivo: no se ofrece si ya hay otro.
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
 * De qué se hizo el archivo (solo el origen).
 */
type BasisChoice = 'none' | CreditAsset | 'other_model';

const basisChoice = ref<BasisChoice>(
    credit.value.based_on_asset ?? (then.value ? 'other_model' : 'none'),
);

const basisLabel = (target: CreditAsset): string => {
    if (target === 'model') {
        return 'Es un render del modelo 3D';
    }

    if (target === 'artwork') {
        return credit.value.asset === 'model'
            ? 'Se generó a partir de la ilustración'
            : 'Es un recorte o adaptación de la ilustración';
    }

    return 'Se hizo a partir de la miniatura';
};

/*
 * Otros archivos de la tarjeta de los que puede salir este.
 */
const cardBasisOptions = computed(() =>
    props.assets
        .filter((item) => item.value !== credit.value.asset)
        .map((item) => ({
            value: item.value,
            label: basisLabel(item.value),
            disabled: !item.has_file,
        })),
);

type BasisMode = 'direct' | 'card' | 'other_model';

const basisMode = computed<BasisMode>(() =>
    basisChoice.value === 'none'
        ? 'direct'
        : basisChoice.value === 'other_model'
          ? 'other_model'
          : 'card',
);

const selectMode = (mode: BasisMode) => {
    if (mode === basisMode.value) {
        return;
    }

    basisChoice.value =
        mode === 'direct'
            ? 'none'
            : mode === 'other_model'
              ? 'other_model'
              : (cardBasisOptions.value.find((item) => !item.disabled)?.value ??
                'none');
};

const MODES = computed(() => {
    const modes: {
        value: BasisMode;
        label: string;
        hint: string;
        icon: typeof Check;
        disabled?: boolean;
    }[] = [
        {
            value: 'direct',
            label: 'Lo hicimos o lo descargamos tal cual',
            hint: 'Una foto, ilustración o modelo de un autor, sitio o herramienta de IA.',
            icon: Check,
        },
        {
            value: 'card',
            label: 'Sale de otro archivo de esta tarjeta',
            hint: 'Un render del modelo 3D o un recorte de la ilustración.',
            icon: GitBranch,
            disabled: cardBasisOptions.value.every((item) => item.disabled),
        },
    ];

    // Las imágenes también pueden ser renders de un modelo de otro sitio.
    if (credit.value.asset !== 'model') {
        modes.push({
            value: 'other_model',
            label: 'Es un render de un modelo 3D de otro sitio',
            hint: 'Sketchfab, Meshy, Poly Pizza… Lo registrarás en el paso 2.',
            icon: Sparkles,
        });
    }

    return modes;
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
 * Obligaciones que hereda de la licencia del archivo en que se basa.
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
        <!-- Archivo y tipo ya elegidos -->
        <div
            v-if="!showSelectors && currentAsset && credit.role"
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#C8DCCF] bg-white p-4"
        >
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <component :is="ASSET_ICONS[currentAsset.value]" :size="22" />
            </div>

            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-semibold tracking-wide text-[#718477] uppercase"
                >
                    {{ currentAsset.label }}
                </p>
                <p class="text-base font-bold text-[#244334]">
                    {{ creditRole(credit.role).question }}
                </p>
                <p class="text-xs text-[#718477]">
                    {{ creditRole(credit.role).description }}
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#C8DCCF] bg-white px-3 py-2 text-xs font-semibold text-[#187744] transition hover:bg-[#F2F7F4]"
                @click="showSelectors = true"
            >
                <Pencil :size="14" />
                Cambiar
            </button>
        </div>

        <template v-else>
            <!-- Archivo -->
            <AdminCard
                title="¿De qué archivo es este crédito?"
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
                            <p class="text-sm font-semibold">
                                {{ item.label }}
                            </p>
                            <p class="text-xs text-[#718477]">
                                {{
                                    !item.has_file
                                        ? 'Sin archivo'
                                        : item.has_origin
                                          ? 'Con licencia'
                                          : 'Falta licencia'
                                }}
                            </p>
                        </div>
                    </label>
                </div>

                <p v-if="errors.asset" class="form-error mt-2">
                    {{ errors.asset }}
                </p>
            </AdminCard>

            <!-- Tipo de crédito -->
            <AdminCard
                v-if="credit.asset"
                title="¿Qué quieres registrar?"
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
                            {{ item.question }}
                        </span>
                        <span class="text-xs text-[#718477]">
                            {{
                                item.value === 'origin' && originTaken
                                    ? 'Este archivo ya lo tiene: edítalo desde la lista.'
                                    : item.description
                            }}
                        </span>
                        <span
                            v-if="item.value !== 'origin'"
                            class="mt-1 text-[11px] font-semibold text-[#A0B2A6]"
                        >
                            Opcional
                        </span>
                    </label>
                </div>

                <p v-if="errors.role" class="form-error mt-2">
                    {{ errors.role }}
                </p>
            </AdminCard>
        </template>

        <!-- De dónde salió el archivo (solo el origen) -->
        <AdminCard
            v-if="credit.asset && credit.role === 'origin'"
            title="¿Cómo se obtuvo este archivo?"
            :icon="GitBranch"
        >
            <!-- Render de un modelo de otro sitio: dos pasos -->
            <ol
                v-if="basisMode === 'other_model'"
                class="mb-4 flex flex-wrap items-center gap-2 text-xs font-semibold"
            >
                <li
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#187744] px-3 py-1 text-white"
                >
                    1. Esta imagen
                </li>
                <li class="text-[#A0B2A6]">→</li>
                <li
                    class="inline-flex items-center gap-1.5 rounded-full border border-[#C8DCCF] bg-white px-3 py-1 text-[#718477]"
                >
                    2. El modelo 3D de otro sitio
                </li>
            </ol>

            <div class="grid gap-3 md:grid-cols-3">
                <label
                    v-for="mode in MODES"
                    :key="mode.value"
                    class="flex flex-col gap-1 rounded-xl border p-3.5 transition"
                    :class="[
                        basisMode === mode.value
                            ? 'border-[#187744] bg-[#F2F7F4]'
                            : 'border-[#C8DCCF] bg-white',
                        mode.disabled
                            ? 'cursor-not-allowed opacity-50'
                            : 'cursor-pointer',
                    ]"
                >
                    <input
                        type="radio"
                        class="sr-only"
                        name="basis_mode"
                        :value="mode.value"
                        :checked="basisMode === mode.value"
                        :disabled="mode.disabled"
                        @change="selectMode(mode.value)"
                    />
                    <component
                        :is="mode.icon"
                        :size="18"
                        :class="
                            basisMode === mode.value
                                ? 'text-[#187744]'
                                : 'text-[#718477]'
                        "
                    />
                    <span class="text-sm font-semibold text-[#244334]">
                        {{ mode.label }}
                    </span>
                    <span class="text-xs text-[#718477]">
                        {{ mode.hint }}
                    </span>
                </label>
            </div>

            <!-- Qué archivo de la tarjeta -->
            <div v-if="basisMode === 'card'" class="mt-4 space-y-2">
                <p class="form-label">¿De cuál?</p>

                <label
                    v-for="option in cardBasisOptions"
                    :key="option.value"
                    class="flex items-center gap-3 rounded-xl border p-3 transition"
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
                        class="accent-[#187744]"
                        :value="option.value"
                        :disabled="option.disabled"
                    />
                    <component :is="ASSET_ICONS[option.value]" :size="16" />
                    <span class="text-sm font-medium text-[#244334]">
                        {{ option.label }}
                    </span>
                    <span v-if="option.disabled" class="text-xs text-[#718477]">
                        (la tarjeta no lo tiene)
                    </span>
                </label>

                <!-- Créditos del archivo en que se basa -->
                <div
                    v-if="basisAsset"
                    class="rounded-lg border border-[#DCE7DF] bg-[#F7FAF8] p-3"
                >
                    <p class="text-xs font-semibold text-[#435D4C]">
                        Créditos de
                        {{ basisAsset.label.toLowerCase() }} (también aparecen
                        en la app)
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
                        este archivo es una obra derivada y también debe
                        compartirse con esa licencia.
                    </p>
                </div>
            </div>

            <p v-if="errors.based_on_asset" class="form-error mt-2">
                {{ errors.based_on_asset }}
            </p>
        </AdminCard>

        <!-- Dónde, licencia y datos -->
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
