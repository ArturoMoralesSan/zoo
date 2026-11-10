<script setup lang="ts">
import { computed } from 'vue';

import { QUESTION_PRESETS, isValidQuestionCount } from '@/lib/quizImport';

/*
 * Cantidad de preguntas por pedir: atajos, número libre y cuántas caben.
 * El límite de `max` es por guardado; la tarjeta no tiene un máximo total.
 */
const props = withDefaults(
    defineProps<{
        modelValue: number;
        inputId: string;
        // Cuántas se pueden pedir ahora.
        limit: number;
        max: number;
        saved: number;
        pending?: number;
    }>(),
    { pending: 0 },
);

const emit = defineEmits<{
    'update:modelValue': [value: number];
}>();

const count = computed({
    get: () => props.modelValue,
    set: (value: number) => emit('update:modelValue', value),
});

const isValid = computed(() =>
    isValidQuestionCount(props.modelValue, props.limit),
);
</script>

<template>
    <div class="form-field">
        <label :for="inputId" class="form-label">¿Cuántas preguntas?</label>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <button
                v-for="preset in QUESTION_PRESETS"
                :key="preset"
                type="button"
                class="min-w-12 rounded-lg border px-3.5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="
                    modelValue === preset
                        ? 'border-[#187744] bg-[#EAF5EE] text-[#187744]'
                        : 'border-[#C8DCCF] bg-white text-[#435D4C] hover:bg-[#F2F7F4]'
                "
                :aria-pressed="modelValue === preset"
                :disabled="preset > limit"
                @click="count = preset"
            >
                {{ preset }}
            </button>

            <input
                :id="inputId"
                v-model.number="count"
                type="number"
                min="1"
                :max="limit"
                step="1"
                class="form-input w-28"
                aria-label="Cantidad personalizada de preguntas"
                :disabled="limit < 1"
                :aria-invalid="!isValid"
            />
        </div>

        <p
            class="mt-3 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] px-3.5 py-2.5 text-sm text-[#435D4C]"
            aria-live="polite"
        >
            Esta tarjeta tiene
            <strong class="text-[#244334]">{{ saved }}</strong>
            {{ saved === 1 ? 'pregunta guardada' : 'preguntas guardadas' }}.

            <template v-if="limit < 1">
                Ya tienes {{ pending }} por revisar, el máximo por guardado.
                Guárdalas antes de pedir más.
            </template>
            <template v-else-if="pending">
                Tienes {{ pending }} por revisar, así que puedes pedir hasta
                <strong class="text-[#244334]">{{ limit }}</strong>
                más antes de guardar.
            </template>
            <template v-else>
                Puedes agregar hasta
                <strong class="text-[#244334]">{{ limit }}</strong>
                a la vez.
            </template>
        </p>

        <p v-if="limit >= 1 && !isValid" class="mt-1 text-xs text-red-700">
            Escribe un número entero del 1 al {{ limit }}.
        </p>
    </div>
</template>
