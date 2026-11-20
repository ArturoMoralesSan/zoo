<script setup lang="ts">
import { Check, X } from 'lucide-vue-next';
import { computed } from 'vue';

import {
    conservationStatusOption,
    conservationStatusOptions,
    dietOptions,
    habitatGroups,
    sortHabitats,
} from '@/lib/speciesCatalogs';
import type { HabitatCode } from '@/lib/speciesCatalogs';
import { cn } from '@/lib/utils';

// Dieta, estado de conservación y hábitats de una especie (Create y Edit).
// El campo "Origen" llega por el slot para compartir la misma rejilla.

const diet = defineModel<string>('diet', { required: true });
const conservationStatus = defineModel<string>('conservationStatus', {
    required: true,
});
const habitats = defineModel<HabitatCode[]>('habitats', { required: true });

defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const selectedStatus = computed(() =>
    conservationStatusOption(conservationStatus.value),
);

// La escala UICN de mayor a menor riesgo, sin DD ni NE.
const scale = conservationStatusOptions.filter(
    (option) => option.value !== 'DD' && option.value !== 'NE',
);

const toggleHabitat = (code: HabitatCode) => {
    habitats.value = habitats.value.includes(code)
        ? habitats.value.filter((value) => value !== code)
        : sortHabitats([...habitats.value, code]);
};
</script>

<template>
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <slot />

        <div class="form-field">
            <label for="diet" class="form-label">Dieta</label>

            <div class="form-select-wrapper">
                <select
                    id="diet"
                    v-model="diet"
                    :class="[
                        'form-select appearance-none',
                        errors.diet && 'form-select-error',
                    ]"
                >
                    <option value="">Sin especificar</option>

                    <option
                        v-for="option in dietOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }} ({{ option.hint }})
                    </option>
                </select>
            </div>

            <p v-if="errors.diet" class="form-error">{{ errors.diet }}</p>
        </div>

        <div class="form-field md:col-span-2">
            <label for="conservation_status" class="form-label">
                Estado de conservación
                <span class="font-normal text-[#718477]"
                    >(Lista Roja de la UICN)</span
                >
            </label>

            <div class="flex flex-col gap-3 md:flex-row md:items-center">
                <div class="form-select-wrapper md:w-80 md:shrink-0">
                    <select
                        id="conservation_status"
                        v-model="conservationStatus"
                        :class="[
                            'form-select appearance-none',
                            errors.conservation_status && 'form-select-error',
                        ]"
                    >
                        <option value="">Sin especificar</option>

                        <option
                            v-for="option in conservationStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.value }} · {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- Escala de referencia; resalta la categoría elegida. -->
                <ol
                    class="flex flex-wrap items-center gap-1"
                    aria-hidden="true"
                >
                    <li
                        v-for="option in scale"
                        :key="option.value"
                        :title="option.label"
                        :class="
                            cn(
                                'flex h-7 w-9 items-center justify-center rounded-md border text-xs font-bold transition',
                                option.badgeClass,
                                selectedStatus &&
                                    selectedStatus.value !== option.value &&
                                    'opacity-25',
                                selectedStatus?.value === option.value &&
                                    'ring-2 ring-[#244334] ring-offset-1',
                            )
                        "
                    >
                        {{ option.value }}
                    </li>
                </ol>
            </div>

            <p v-if="selectedStatus?.threatened" class="form-help">
                Especie amenazada según la UICN.
            </p>

            <p v-if="errors.conservation_status" class="form-error">
                {{ errors.conservation_status }}
            </p>
        </div>

        <fieldset class="form-field md:col-span-2">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <legend class="form-label">
                    Hábitats
                    <span class="font-normal text-[#718477]"
                        >(elige todos los que apliquen)</span
                    >
                </legend>

                <button
                    v-if="habitats.length"
                    type="button"
                    class="inline-flex items-center gap-1 text-xs font-medium text-[#718477] hover:text-[#244334]"
                    @click="habitats = []"
                >
                    <X class="h-3.5 w-3.5" />
                    Quitar
                    {{
                        habitats.length === 1
                            ? 'el seleccionado'
                            : `los ${habitats.length} seleccionados`
                    }}
                </button>
            </div>

            <div
                class="mt-2 space-y-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4"
            >
                <div v-for="group in habitatGroups" :key="group.name">
                    <p
                        class="mb-1.5 text-xs font-semibold tracking-wide text-[#718477] uppercase"
                    >
                        {{ group.name }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in group.options"
                            :key="option.value"
                            type="button"
                            :aria-pressed="habitats.includes(option.value)"
                            :class="
                                cn(
                                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition',
                                    habitats.includes(option.value)
                                        ? 'border-[#187744] bg-[#187744] text-white'
                                        : 'border-[#c8dccf] bg-white text-[#244334] hover:border-[#187744]',
                                )
                            "
                            @click="toggleHabitat(option.value)"
                        >
                            <Check
                                v-if="habitats.includes(option.value)"
                                class="h-3.5 w-3.5"
                            />
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="errors.habitats" class="form-error">
                {{ errors.habitats }}
            </p>
            <p v-else-if="errors['habitats.0']" class="form-error">
                {{ errors['habitats.0'] }}
            </p>
        </fieldset>
    </div>
</template>
