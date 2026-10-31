<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Info, Ruler, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

import { formatFactor, formatMeters } from '@/lib/glb/modelSize';

/**
 * Tamaño real guardado del modelo 3D (solo lectura).
 */
const props = defineProps<{
    height: number | null;
    width: number | null;
    depth: number | null;
    originalHeight: number | null;
    editUrl: string;
}>();

const tiles = computed(() => [
    { label: 'Alto', value: props.height },
    { label: 'Ancho', value: props.width },
    { label: 'Largo', value: props.depth },
]);

const factor = computed(() =>
    props.height && props.originalHeight
        ? props.height / props.originalHeight
        : null,
);
</script>

<template>
    <!-- Con tamaño definido -->
    <div
        v-if="height"
        class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4"
    >
        <div class="mb-3 flex items-center gap-2">
            <Ruler :size="16" class="text-[#187744]" />
            <p class="text-sm font-semibold text-[#244334]">Tamaño real</p>
            <span
                class="ml-auto inline-flex items-center rounded-lg border border-[#C8DCCF] bg-[#EAF5EE] px-2.5 py-1 text-xs font-semibold text-[#187744]"
            >
                {{ formatMeters(height) }} de alto
            </span>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div
                v-for="tile in tiles"
                :key="tile.label"
                class="rounded-xl border border-[#DCE7DF] bg-white p-3"
            >
                <p class="text-xs font-medium text-[#718477]">
                    {{ tile.label }}
                </p>
                <p class="mt-1 text-lg font-semibold text-[#244334]">
                    {{ formatMeters(tile.value) }}
                </p>
            </div>
        </div>

        <p class="mt-3 flex items-start gap-2 text-xs text-[#718477]">
            <Info :size="14" class="mt-0.5 shrink-0 text-[#187744]" />
            <span>
                Así se verá en la app de realidad aumentada (1 unidad = 1 m),
                apoyado en el suelo.
                <template v-if="factor">
                    Escala
                    <span class="font-semibold text-[#244334]">
                        {{ formatFactor(factor) }}
                    </span>
                    respecto al archivo original.
                </template>
            </span>
        </p>
    </div>

    <!-- Modelo anterior al escalado -->
    <div
        v-else
        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
    >
        <TriangleAlert :size="18" class="mt-0.5 shrink-0 text-amber-700" />

        <div class="text-sm text-amber-800">
            <p class="font-semibold">Sin tamaño definido</p>
            <p class="mt-0.5 text-xs">
                La vista previa usa las medidas del archivo, que pueden no ser
                reales. Define su alto para que se vea a escala en la app.
            </p>
            <Link
                :href="editUrl"
                class="mt-2 inline-block text-xs font-semibold text-[#187744] hover:underline"
            >
                Definir tamaño
            </Link>
        </div>
    </div>
</template>
