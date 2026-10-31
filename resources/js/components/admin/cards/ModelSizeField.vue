<script setup lang="ts">
import {
    ArrowRight,
    Info,
    Minus,
    Plus,
    Ruler,
    TriangleAlert,
    Undo2,
} from 'lucide-vue-next';
import { computed } from 'vue';

import type { CardModelSize } from '@/composables/useCardModelSize';
import {
    QUICK_HEIGHTS,
    formatFactor,
    formatMeters,
    stepMeters,
} from '@/lib/glb/modelSize';

const props = defineProps<{
    model: CardModelSize;
    errors: Partial<Record<'model_target_height', string>>;
}>();

const minHeight = computed(() => props.model.config.minHeight);

const tiles = computed(() => [
    {
        label: 'Alto',
        original: props.model.original?.height ?? null,
        final: props.model.final?.height ?? null,
    },
    {
        label: 'Ancho',
        original: props.model.original?.width ?? null,
        final: props.model.final?.width ?? null,
    },
    {
        label: 'Largo',
        original: props.model.original?.depth ?? null,
        final: props.model.final?.depth ?? null,
    },
]);

const step = (direction: 1 | -1) => {
    props.model.setHeight(
        stepMeters(props.model.height, direction, props.model.config),
    );
};

const heightError = computed(
    () => props.model.heightError ?? props.errors.model_target_height ?? null,
);
</script>

<template>
    <div class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4">
        <div class="mb-4 flex items-start gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <Ruler :size="18" />
            </div>

            <div>
                <p class="text-sm font-semibold text-[#244334]">Tamaño real</p>
                <p class="text-xs text-[#718477]">
                    Alto que tendrá el modelo en la app de realidad aumentada
                    (1 unidad = 1 metro).
                </p>
            </div>
        </div>

        <!-- Midiendo el archivo -->
        <div
            v-if="model.inspecting"
            class="animate-pulse space-y-3"
            aria-busy="true"
            aria-label="Midiendo el modelo"
        >
            <div class="h-4 w-24 rounded bg-[#E4EEE7]" />
            <div class="h-11 w-full rounded-lg bg-[#E4EEE7] sm:w-72" />
            <div class="flex gap-2">
                <div
                    v-for="index in 5"
                    :key="index"
                    class="h-8 w-14 rounded-lg bg-[#E4EEE7]"
                />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div
                    v-for="index in 3"
                    :key="index"
                    class="h-[74px] rounded-xl bg-[#E4EEE7]"
                />
            </div>
        </div>

        <div v-else class="space-y-4">
            <!-- Alto final -->
            <div class="form-field">
                <label for="model_target_height" class="form-label">
                    Alto final
                    <span v-if="model.isRequired" class="text-red-500">*</span>
                </label>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-[#C8DCCF] bg-white text-[#435D4C] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Reducir alto"
                        :disabled="model.height !== null && model.height <= minHeight"
                        @click="step(-1)"
                    >
                        <Minus :size="16" />
                    </button>

                    <div class="relative w-36">
                        <input
                            id="model_target_height"
                            type="text"
                            inputmode="decimal"
                            autocomplete="off"
                            placeholder="1.50"
                            class="form-input pr-9 text-right font-semibold"
                            :class="{ 'form-input-error': heightError }"
                            :value="model.heightInput"
                            :aria-invalid="Boolean(heightError)"
                            aria-describedby="model_target_height_help"
                            @input="
                                model.setHeightInput(
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            @blur="model.touch()"
                        />

                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm font-semibold text-[#718477]"
                        >
                            m
                        </span>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-[#C8DCCF] bg-white text-[#435D4C] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-50"
                        aria-label="Aumentar alto"
                        :disabled="
                            model.height !== null &&
                            model.height >= model.config.maxHeight
                        "
                        @click="step(1)"
                    >
                        <Plus :size="16" />
                    </button>

                    <button
                        v-if="model.isNewFile && model.original"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#C8DCCF] bg-white px-3 py-2.5 text-sm font-medium text-[#435D4C] transition hover:bg-[#F2F7F4]"
                        @click="model.useFileSize()"
                    >
                        <Undo2 :size="15" />
                        Usar tamaño del archivo
                    </button>
                </div>

                <!-- Medidas rápidas -->
                <div class="mt-1 flex flex-wrap gap-2">
                    <button
                        v-for="quick in QUICK_HEIGHTS"
                        :key="quick"
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            model.height === quick
                                ? 'border-[#187744] bg-[#E4F2E8] text-[#187744]'
                                : 'border-[#DCE7DF] bg-white text-[#435D4C] hover:bg-[#F2F7F4]'
                        "
                        :aria-pressed="model.height === quick"
                        @click="model.setHeight(quick)"
                    >
                        {{ quick }} m
                    </button>
                </div>

                <p id="model_target_height_help" class="form-help">
                    <template v-if="model.original && model.isNewFile">
                        El archivo mide
                        {{ formatMeters(model.original.height) }} de alto.
                    </template>
                    Ingresa metros con punto o coma decimal (por ejemplo 1 o
                    1.5).
                </p>

                <p v-if="heightError" class="form-error">
                    {{ heightError }}
                </p>

                <p
                    v-if="model.sizeChanged && model.storedHeight !== null"
                    class="flex items-center gap-1.5 text-xs font-medium text-[#187744]"
                >
                    {{ formatMeters(model.storedHeight) }}
                    <ArrowRight :size="13" />
                    {{ formatMeters(model.height) }}
                    · se volverá a escalar desde el archivo original
                </p>

                <p
                    v-else-if="model.sizeChanged"
                    class="text-xs font-medium text-[#187744]"
                >
                    Se escalará a {{ formatMeters(model.height) }} desde el
                    archivo original.
                </p>
            </div>

            <!-- Medidas -->
            <div v-if="model.original" class="space-y-2">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div
                        v-for="tile in tiles"
                        :key="tile.label"
                        class="rounded-xl border border-[#DCE7DF] bg-white p-3.5"
                    >
                        <p class="text-xs font-medium text-[#718477]">
                            {{ tile.label }}
                        </p>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-1 text-sm text-[#718477]"
                        >
                            {{ formatMeters(tile.original) }}
                            <ArrowRight :size="13" />
                            <span class="text-lg font-semibold text-[#244334]">
                                {{ formatMeters(tile.final) }}
                            </span>
                        </p>
                    </div>
                </div>

                <p class="flex items-center gap-2 text-xs text-[#718477]">
                    <Info :size="14" class="shrink-0 text-[#187744]" />
                    Escala uniforme
                    <span class="font-semibold text-[#244334]">
                        {{ formatFactor(model.factor ?? 1) }}
                    </span>
                    respecto al archivo original.
                </p>
            </div>

            <!-- Advertencias -->
            <ul
                v-if="model.warnings.length"
                class="space-y-1.5 rounded-lg border border-amber-200 bg-amber-50 p-3"
            >
                <li
                    v-for="warning in model.warnings"
                    :key="warning"
                    class="flex items-start gap-2 text-xs text-amber-800"
                >
                    <TriangleAlert :size="14" class="mt-0.5 shrink-0" />
                    {{ warning }}
                </li>
            </ul>
        </div>

        <!-- Error al medir o procesar (los del servidor van bajo el archivo) -->
        <p v-if="model.error" class="form-error mt-3 flex items-start gap-2">
            <TriangleAlert :size="14" class="mt-0.5 shrink-0" />
            {{ model.error }}
        </p>
    </div>
</template>
