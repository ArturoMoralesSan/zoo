<script setup lang="ts">
import { Link2, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import AssetCreditFields from '@/components/admin/cards/AssetCreditFields.vue';
import type { SpeciesCreditImage } from '@/lib/credits/speciesCredits';
import {
    CREDIT_ROLES,
    DEFAULT_MODIFICATION_NOTES,
    creditRole,
} from '@/lib/credits/types';
import type {
    AssetSourceOption,
    CreditForm,
    CreditRole,
    License,
} from '@/lib/credits/types';

/*
 * Formulario de un crédito de una imagen de especie: tipo de crédito
 * (origen, basado en, incluye) y después origen, licencia y datos
 * (AssetCreditFields). A diferencia de la tarjeta, una imagen de especie no
 * se hace a partir de otro archivo de la especie.
 */
const props = withDefaults(
    defineProps<{
        image: SpeciesCreditImage;
        licenses: License[];
        sources: AssetSourceOption[];
        allowRestricted?: boolean;
        errors: Record<string, string | undefined>;
        // Tipo ya elegido: se muestra resumido.
        compact?: boolean;
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

const showSelector = ref(
    !props.compact || credit.value.role === null || Boolean(props.errors.role),
);

// El origen es único por imagen: no se ofrece si ya lo tiene (salvo al editarlo).
const originTaken = props.image.has_origin && props.editingId === null;

const selectRole = (role: CreditRole) => {
    credit.value.role = role;

    // El archivo final (origen) suele recortarse o redimensionarse.
    if (role === 'origin' && props.editingId === null) {
        credit.value.modified = true;
        credit.value.modification_note ||= DEFAULT_MODIFICATION_NOTES.image;
    }
};
</script>

<template>
    <div class="space-y-5">
        <!-- Tipo ya elegido -->
        <div
            v-if="!showSelector && credit.role"
            class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#C8DCCF] bg-white p-4"
        >
            <img
                v-if="image.preview_url"
                :src="image.preview_url"
                :alt="image.alt_text ?? image.label"
                class="h-11 w-11 shrink-0 rounded-xl border border-[#DCE7DF] object-cover"
            />
            <div class="min-w-0 flex-1">
                <p
                    class="text-xs font-semibold tracking-wide text-[#718477] uppercase"
                >
                    {{ image.label }}
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
                @click="showSelector = true"
            >
                <Pencil :size="14" />
                Cambiar
            </button>
        </div>

        <!-- Tipo de crédito -->
        <AdminCard
            v-else
            :title="`${image.label}: ¿qué quieres registrar?`"
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
                                ? 'Esta imagen ya lo tiene: edítalo desde la lista.'
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
            <p v-if="errors.asset" class="form-error mt-2">
                {{ errors.asset }}
            </p>
        </AdminCard>

        <!-- Dónde, licencia y datos -->
        <AssetCreditFields
            v-if="credit.role"
            v-model="credit"
            asset="image"
            :role="credit.role"
            :licenses="licenses"
            :sources="sources"
            :allow-restricted="allowRestricted"
            :stored-license-id="storedLicenseId"
            :errors="errors"
        />
    </div>
</template>
