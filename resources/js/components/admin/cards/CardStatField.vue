<script setup lang="ts">
import { HugeiconsIcon } from '@hugeicons/vue';

import type { CardStatIcon } from '@/lib/cardStats';

/*
 * Estadística de la tarjeta (1-100): número y control deslizante.
 */
withDefaults(
    defineProps<{
        id: string;
        label: string;
        help: string;
        error?: string;
        // Icono de la estadística (el mismo que en la tarjeta).
        icon?: CardStatIcon | null;
    }>(),
    {
        error: '',
        icon: null,
    },
);

const value = defineModel<number>({ required: true });
</script>

<template>
    <div class="form-field rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
        <label :for="id" class="form-label flex items-center justify-between">
            <span class="flex items-center gap-2">
                <HugeiconsIcon
                    v-if="icon"
                    :icon="icon"
                    :size="18"
                    :stroke-width="2"
                    class="text-[#187744]"
                />
                {{ label }}
            </span>
            <span
                class="rounded bg-[#EAF5EE] px-2 py-0.5 text-xs font-bold text-[#187744]"
            >
                {{ value }}/100
            </span>
        </label>

        <input
            :id="id"
            v-model.number="value"
            type="number"
            min="1"
            max="100"
            required
            class="form-input text-center text-lg font-semibold"
            :class="{
                'form-input-error': error,
            }"
        />

        <input
            v-model.number="value"
            type="range"
            min="1"
            max="100"
            class="w-full cursor-pointer accent-[#187744]"
            :aria-label="label"
        />

        <p class="form-help text-center">
            {{ help }}
        </p>

        <p v-if="error" class="form-error text-center">
            {{ error }}
        </p>
    </div>
</template>
