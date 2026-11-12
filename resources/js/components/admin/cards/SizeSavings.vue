<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';

import { formatBytes } from '@/lib/glb/modelSize';

/*
 * Peso de un archivo de la tarjeta: original → el que descarga la app, con
 * el porcentaje ahorrado. Sin original conocido, solo el peso actual.
 */
const props = withDefaults(
    defineProps<{
        label?: string;
        original?: number | null;
        optimized?: number | null;
        estimating?: boolean;
        // Peso del archivo que la app tiene hoy (al reemplazarlo).
        previous?: number | null;
    }>(),
    {
        label: 'Peso',
        original: null,
        optimized: null,
        estimating: false,
        previous: null,
    },
);

const savedPercent = computed(() =>
    props.original && props.optimized && props.optimized < props.original
        ? Math.round((1 - props.optimized / props.original) * 100)
        : null,
);

const showOriginal = computed(
    () =>
        props.original !== null &&
        (props.estimating ||
            props.optimized === null ||
            savedPercent.value !== null),
);
</script>

<template>
    <div
        v-if="original !== null || optimized !== null || estimating"
        class="space-y-1 text-sm text-[#718477]"
    >
        <p class="flex flex-wrap items-center gap-1">
            <span class="text-xs font-medium">{{ label }}:</span>

            <template v-if="showOriginal">
                {{ formatBytes(original) }}
                <span class="text-xs">(original)</span>
                <ArrowRight :size="13" />
            </template>

            <span v-if="estimating" class="text-xs font-medium">
                Calculando…
            </span>

            <template v-else-if="optimized !== null">
                <span class="font-semibold text-[#244334]">
                    {{ formatBytes(optimized) }}
                </span>
                <span class="text-xs">(en la app)</span>
                <span
                    v-if="savedPercent !== null"
                    class="rounded-full bg-[#EAF5EE] px-2 py-0.5 text-xs font-semibold text-[#187744]"
                >
                    −{{ savedPercent }} %
                </span>
            </template>
        </p>

        <p v-if="previous !== null" class="text-xs">
            Archivo actual en la app: {{ formatBytes(previous) }}
        </p>
    </div>
</template>
