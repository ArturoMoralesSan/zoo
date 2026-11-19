<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

import { findSeasonColor, seasonColors } from '@/lib/pointSeasonStyles';

/*
 * Selector del color del aviso de temporada. null = predeterminado de la app.
 */
defineProps<{
    error?: string;
}>();

const model = defineModel<string | null>({ required: true });

const selected = computed(() => findSeasonColor(model.value));
</script>

<template>
    <div>
        <div
            class="flex flex-wrap items-center gap-2.5"
            role="radiogroup"
            aria-label="Color del aviso"
        >
            <button
                type="button"
                role="radio"
                :aria-checked="model === null"
                aria-label="Predeterminado"
                title="Predeterminado"
                class="flex h-10 items-center justify-center gap-1.5 rounded-full border px-3 text-xs font-semibold transition-colors focus:ring-2 focus:ring-[#187744]/20 focus:outline-none"
                :class="
                    model === null
                        ? 'border-[#187744] bg-[#E2F3E8] text-[#187744]'
                        : 'border-dashed border-[#C8DCCF] bg-white text-[#435D4C] hover:border-[#A9CBB5]'
                "
                @click="model = null"
            >
                <Check
                    v-if="model === null"
                    class="h-3.5 w-3.5"
                    aria-hidden="true"
                />
                Predeterminado
            </button>

            <button
                v-for="color in seasonColors"
                :key="color.key"
                type="button"
                role="radio"
                :aria-checked="model === color.key"
                :aria-label="color.label"
                :title="color.label"
                class="flex h-10 w-10 items-center justify-center rounded-full border-2 transition-transform hover:scale-105 focus:ring-2 focus:ring-[#187744]/30 focus:ring-offset-2 focus:outline-none"
                :class="
                    model === color.key ? 'border-[#244334]' : 'border-white'
                "
                :style="{
                    backgroundColor: color.accent,
                    color: color.on_accent,
                    outline: `1px solid ${color.accent}`,
                }"
                @click="model = color.key"
            >
                <Check
                    v-if="model === color.key"
                    class="h-4 w-4"
                    aria-hidden="true"
                />
            </button>
        </div>

        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-else class="form-help">
            {{
                selected
                    ? `${selected.label}. Cada color viene con tonos que se leen bien en el celular.`
                    : 'La app usa sus colores de siempre (verde esmeralda).'
            }}
        </p>
    </div>
</template>
