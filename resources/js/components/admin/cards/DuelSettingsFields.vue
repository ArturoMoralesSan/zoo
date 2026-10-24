<script setup lang="ts">
import { Info, Swords } from 'lucide-vue-next';
import { computed } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import {
    getRarityClasses,
    getRarityLabel
    
    
} from '@/lib/cardRarity';
import type {CardRaritySetting, DuelSettingsSource} from '@/lib/cardRarity';

const props = defineProps<{
    rarity: string;
    raritySettings: CardRaritySetting[];
    errors: Partial<
        Record<'duel_settings_source' | 'questions_per_duel' | 'retries', string>
    >;
}>();

const source = defineModel<DuelSettingsSource>('source', { required: true });
const questionsPerDuel = defineModel<number | null>('questionsPerDuel');
const retries = defineModel<number | null>('retries');

const raritySetting = computed(() =>
    props.raritySettings.find((setting) => setting.rarity === props.rarity),
);

const sourceOptions: {
    value: DuelSettingsSource;
    title: string;
    description: string;
}[] = [
    {
        value: 'rarity',
        title: 'Por rareza (Default)',
        description: 'Usa la configuración de la rareza de la tarjeta',
    },
    {
        value: 'card',
        title: 'Personalizado',
        description: 'Valores exclusivos para esta tarjeta',
    },
];

/*
 * Al personalizar, se parte de los valores de la rareza.
 */
const selectSource = (value: DuelSettingsSource) => {
    if (value === 'card') {
        questionsPerDuel.value ??= raritySetting.value?.questions_per_duel ?? 3;
        retries.value ??= raritySetting.value?.retries ?? 3;
    }

    source.value = value;
};
</script>

<template>
    <AdminCard
        title="Configuración de duelos y reintentos"
        description="Usa la configuración de la rareza o personaliza la cantidad de preguntas por duelo y el límite de reintentos."
        :icon="Swords"
    >
        <div class="space-y-5">
            <!-- Origen de la configuración -->
            <div class="form-field">
                <label class="form-label">
                    Asignación de la configuración
                    <span class="text-red-500">*</span>
                </label>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label
                        v-for="option in sourceOptions"
                        :key="option.value"
                        class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition"
                        :class="
                            source === option.value
                                ? 'border-[#187744] bg-[#F2F7F4] text-[#187744]'
                                : 'border-[#C8DCCF] bg-white text-[#435D4C]'
                        "
                    >
                        <input
                            type="radio"
                            :value="option.value"
                            :checked="source === option.value"
                            class="accent-[#187744]"
                            @change="selectSource(option.value)"
                        />
                        <div>
                            <p class="text-sm font-semibold">{{ option.title }}</p>
                            <p class="text-xs text-[#718477]">{{ option.description }}</p>
                        </div>
                    </label>
                </div>

                <p v-if="errors.duel_settings_source" class="form-error">
                    {{ errors.duel_settings_source }}
                </p>
            </div>

            <!-- Configuración de la rareza -->
            <div
                v-if="source === 'rarity'"
                class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4"
            >
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-[#244334]">
                        <span>Rareza seleccionada:</span>

                        <span
                            class="inline-flex rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                            :class="getRarityClasses(rarity)"
                        >
                            {{ getRarityLabel(rarity) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-[#DCE7DF] bg-white p-3.5">
                            <p class="text-xs font-medium text-[#718477]">
                                Preguntas por duelo
                            </p>
                            <p class="mt-1 text-lg font-semibold text-[#244334]">
                                {{ raritySetting?.questions_per_duel ?? 3 }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-[#DCE7DF] bg-white p-3.5">
                            <p class="text-xs font-medium text-[#718477]">
                                Límite de reintentos
                            </p>
                            <p class="mt-1 text-lg font-semibold text-[#244334]">
                                {{ raritySetting?.retries ?? 3 }}
                            </p>
                        </div>
                    </div>

                    <p class="flex items-start gap-2 text-xs text-[#718477]">
                        <Info :size="14" class="mt-0.5 shrink-0 text-[#187744]" />
                        Estos valores se administran desde la tabla
                        "Configuración por rareza" en el listado de tarjetas.
                    </p>
                </div>
            </div>

            <!-- Configuración personalizada -->
            <div
                v-else
                class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4"
            >
                <div class="form-grid">
                    <div class="form-field">
                        <label for="questions_per_duel" class="form-label">
                            Preguntas por duelo
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="questions_per_duel"
                            v-model.number="questionsPerDuel"
                            type="number"
                            min="1"
                            max="50"
                            required
                            placeholder="3"
                            class="form-input"
                            :class="{
                                'form-input-error': errors.questions_per_duel,
                            }"
                        />

                        <p class="form-help">
                            Cantidad de preguntas aleatorias que se mostrarán al usuario por cada duelo.
                        </p>

                        <p v-if="errors.questions_per_duel" class="form-error">
                            {{ errors.questions_per_duel }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label for="retries" class="form-label">
                            Límite de reintentos
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="retries"
                            v-model.number="retries"
                            type="number"
                            min="1"
                            max="100"
                            required
                            placeholder="3"
                            class="form-input"
                            :class="{
                                'form-input-error': errors.retries,
                            }"
                        />

                        <p class="form-help">
                            Número máximo de intentos que tendrá el usuario para resolver el quiz de esta tarjeta.
                        </p>

                        <p v-if="errors.retries" class="form-error">
                            {{ errors.retries }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminCard>
</template>
