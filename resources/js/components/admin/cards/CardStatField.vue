<script setup lang="ts">
/*
 * Estadística de la tarjeta (1-100): número y control deslizante.
 */
withDefaults(
    defineProps<{
        id: string;
        label: string;
        help: string;
        error?: string;
    }>(),
    {
        error: '',
    },
);

const value = defineModel<number>({ required: true });
</script>

<template>
    <div class="form-field rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4">
        <label :for="id" class="form-label flex items-center justify-between">
            <span>{{ label }}</span>
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
