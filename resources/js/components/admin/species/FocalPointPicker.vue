<script setup lang="ts">
import { Crosshair, LocateFixed } from 'lucide-vue-next';
import { computed, ref, useId } from 'vue';

import { APP_FRAMES, clampFocal, objectPosition } from '@/lib/focalPoint';

/*
 * Punto focal de una imagen de especie: la app recorta la foto en marcos de
 * distinta forma y mantiene este punto a la vista. null = centro.
 */
const props = defineProps<{
    src: string;
    alt?: string;
}>();

const x = defineModel<number | null>('x', { required: true });
const y = defineModel<number | null>('y', { required: true });

const hintId = useId();
const area = ref<HTMLElement | null>(null);
const dragging = ref(false);

const hasPoint = computed(() => x.value !== null && y.value !== null);
const position = computed(() => objectPosition(x.value, y.value));
const markerLeft = computed(() => `${(x.value ?? 0.5) * 100}%`);
const markerTop = computed(() => `${(y.value ?? 0.5) * 100}%`);

const valueText = computed(() =>
    hasPoint.value
        ? `${Math.round((x.value ?? 0) * 100)} % horizontal, ${Math.round((y.value ?? 0) * 100)} % vertical`
        : 'Centro (sin punto focal)',
);

// Vistas previas a 96 px de alto, con la forma real de cada marco de la app.
const previews = APP_FRAMES.map((frame) => ({
    ...frame,
    style: {
        width: `${Math.round((96 * frame.width) / frame.height)}px`,
        height: '96px',
    },
}));

const setFromPointer = (event: PointerEvent) => {
    const rect = area.value?.getBoundingClientRect();

    if (!rect || rect.width === 0 || rect.height === 0) {
        return;
    }

    const point = clampFocal(
        (event.clientX - rect.left) / rect.width,
        (event.clientY - rect.top) / rect.height,
    );

    x.value = point.x;
    y.value = point.y;
};

const onPointerDown = (event: PointerEvent) => {
    dragging.value = true;
    area.value?.setPointerCapture(event.pointerId);
    setFromPointer(event);
};

const onPointerMove = (event: PointerEvent) => {
    if (dragging.value) {
        setFromPointer(event);
    }
};

const onPointerUp = (event: PointerEvent) => {
    dragging.value = false;
    area.value?.releasePointerCapture(event.pointerId);
};

const KEY_STEPS: Record<string, [number, number]> = {
    ArrowLeft: [-1, 0],
    ArrowRight: [1, 0],
    ArrowUp: [0, -1],
    ArrowDown: [0, 1],
};

const onKeydown = (event: KeyboardEvent) => {
    const step = KEY_STEPS[event.key];

    if (!step) {
        return;
    }

    event.preventDefault();
    const amount = event.shiftKey ? 0.05 : 0.01;
    const point = clampFocal(
        (x.value ?? 0.5) + step[0] * amount,
        (y.value ?? 0.5) + step[1] * amount,
    );

    x.value = point.x;
    y.value = point.y;
};

const center = () => {
    x.value = null;
    y.value = null;
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p
                    class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                >
                    <Crosshair :size="16" />
                    Punto focal
                </p>
                <p :id="hintId" class="mt-1 text-xs text-[#718477]">
                    Toca la parte más importante de la foto (p. ej. la cara del
                    animal). La app la mantendrá a la vista al recortar. Con el
                    teclado, usa las flechas (Shift para pasos largos).
                </p>
            </div>

            <button
                type="button"
                :disabled="!hasPoint"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#c8dccf] bg-white px-3 py-1.5 text-xs font-semibold text-[#244334] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-50"
                @click="center"
            >
                <LocateFixed :size="14" />
                Centrar
            </button>
        </div>

        <div class="flex justify-center rounded-xl bg-[#F7FAF8] p-2">
            <div
                ref="area"
                role="group"
                tabindex="0"
                aria-label="Punto focal"
                :aria-describedby="hintId"
                :aria-valuetext="valueText"
                :class="[
                    'relative inline-block touch-none rounded-lg outline-none select-none focus-visible:ring-2 focus-visible:ring-[#187744]',
                    dragging ? 'cursor-grabbing' : 'cursor-crosshair',
                ]"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @keydown="onKeydown"
            >
                <img
                    :src="props.src"
                    :alt="alt ?? ''"
                    draggable="false"
                    class="block max-h-72 max-w-full rounded-lg"
                />

                <span
                    class="pointer-events-none absolute h-9 w-9 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white shadow-[0_0_0_2px_#187744,0_2px_8px_rgb(0_0_0/0.4)]"
                    :class="hasPoint ? 'bg-[#187744]/30' : 'bg-white/20'"
                    :style="{ left: markerLeft, top: markerTop }"
                >
                    <span
                        class="absolute top-1/2 left-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white"
                    ></span>
                </span>
            </div>
        </div>

        <p class="text-xs text-[#718477]">
            {{
                hasPoint
                    ? 'Así se verá en la app:'
                    : 'Sin punto focal, la app usa el centro:'
            }}
        </p>

        <div class="flex flex-wrap items-end gap-3">
            <figure
                v-for="frame in previews"
                :key="frame.key"
                class="space-y-1"
            >
                <img
                    :src="props.src"
                    alt=""
                    class="rounded-md border border-[#c8dccf] object-cover"
                    :style="{ ...frame.style, objectPosition: position }"
                />
                <figcaption class="text-center text-[11px] text-[#718477]">
                    {{ frame.label }}
                </figcaption>
            </figure>
        </div>
    </div>
</template>
