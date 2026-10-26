<script setup lang="ts">
import type { HTML3DElement } from '@babylonjs/viewer';
import {
    Box,
    LoaderCircle,
    RotateCcw,
    Scan,
    TriangleAlert,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

import type { ModelExtension } from '@/lib/modelPreview';

const props = withDefaults(
    defineProps<{
        source: string | null;
        extension?: ModelExtension | null;
        heightClass?: string;
        /*
         * Alto exacto (p. ej. para igualar otra vista previa); tiene prioridad sobre heightClass.
         */
        height?: string | null;
        /*
         * Acercamiento predeterminado y botones de zoom (Completo / Normal / Cerca).
         */
        zoomControls?: boolean;
        /*
         * Motor de render; por defecto el visor elige (WebGPU en Chromium).
         */
        engine?: 'WebGL' | 'WebGPU' | null;
        emptyText?: string;
    }>(),
    {
        extension: null,
        heightClass: 'h-[360px]',
        height: null,
        engine: null,
        zoomControls: true,
        emptyText: 'Selecciona un archivo GLB para previsualizar el modelo.',
    },
);

const emit = defineEmits<{
    /*
     * Acceso a la escena de Babylon una vez cargado el modelo.
     */
    loaded: [details: NonNullable<HTML3DElement['viewerDetails']>];
}>();

const viewer = ref<HTML3DElement | null>(null);

const isReady = ref(false);
const isLoading = ref(false);
const hasError = ref(false);

/*
 * Zoom relativo al encuadre automático del visor, para que se vea
 * igual en cualquier modelo sin importar su tamaño.
 */
const ZOOM_PRESETS = [
    { id: 'full', label: 'Completo', factor: 1, icon: ZoomOut },
    { id: 'normal', label: 'Normal', factor: 0.8, icon: Scan },
    { id: 'close', label: 'Cerca', factor: 0.55, icon: ZoomIn },
] as const;

type ZoomPreset = (typeof ZOOM_PRESETS)[number];

const DEFAULT_ZOOM = ZOOM_PRESETS[1];

let framedRadius: number | null = null;

const activeZoom = ref<ZoomPreset['id'] | null>(DEFAULT_ZOOM.id);

const applyZoom = (preset: ZoomPreset) => {
    const details = viewer.value?.viewerDetails;

    if (!details || !framedRadius) {
        return;
    }

    // Solo cambia la distancia; el ángulo y el centro se conservan.
    details.viewer.updateCamera({ radius: framedRadius * preset.factor });
    activeZoom.value = preset.id;
};

const resetView = () => {
    viewer.value?.viewerDetails?.viewer.resetCamera(true);
    applyZoom(DEFAULT_ZOOM);
};

// Al acercar o girar a mano ya no aplica ningún preajuste.
const clearActiveZoom = () => {
    activeZoom.value = null;
};

const animations = ref<string[]>([]);
const selectedAnimation = ref<number | null>(null);

/*
 * La barra del visor siempre incluye un botón para restablecer la cámara,
 * sin opción para quitarlo. Se oculta con estilos dentro de su shadow DOM
 * (se usa "Restablecer vista" de los controles de zoom), junto con el
 * separador anterior y la barra completa si ya no le queda ningún control.
 * Sin controles de zoom (vista previa de la tarjeta) se conserva el botón.
 */
const TOOLBAR_STYLES = `
    button[aria-label="Reset Camera Pose"],
    .divider:has(+ button[aria-label="Reset Camera Pose"]) {
        display: none;
    }

    .tool-bar:not(:has(.animation-timeline, select)) {
        display: none;
    }
`;

let toolbarSheet: CSSStyleSheet | null = null;

watch(viewer, (element) => {
    const root = element?.shadowRoot;

    if (!root || !props.zoomControls) {
        return;
    }

    if (!toolbarSheet) {
        toolbarSheet = new CSSStyleSheet();
        toolbarSheet.replaceSync(TOOLBAR_STYLES);
    }

    if (!root.adoptedStyleSheets.includes(toolbarSheet)) {
        root.adoptedStyleSheets = [...root.adoptedStyleSheets, toolbarSheet];
    }
});

/*
 * Babylon.js es pesado: se carga solo cuando se usa el visor.
 */
onMounted(async () => {
    await import('@babylonjs/viewer');
    isReady.value = true;
});

watch(
    () => props.source,
    (source) => {
        hasError.value = false;
        isLoading.value = Boolean(source);
        animations.value = [];
        selectedAnimation.value = null;
        framedRadius = null;
        activeZoom.value = DEFAULT_ZOOM.id;
    },
    { immediate: true },
);

const syncAnimationState = () => {
    selectedAnimation.value = viewer.value?.selectedAnimation ?? null;
};

const onModelChange = () => {
    isLoading.value = false;
    hasError.value = false;
    animations.value = [...(viewer.value?.animations ?? [])];
    syncAnimationState();

    const details = viewer.value?.viewerDetails;

    if (!details) {
        return;
    }

    /*
     * Al cargar, el visor encuadra el modelo con una animación, así que el
     * radio final aún no está aplicado. El límite máximo de zoom sí: el visor
     * lo fija en 5 veces el radio de encuadre.
     */
    framedRadius = (details.camera.upperRadiusLimit ?? 0) / 5 || null;

    if (props.zoomControls) {
        applyZoom(DEFAULT_ZOOM);
    }

    emit('loaded', details);
};

const onModelError = () => {
    isLoading.value = false;
    hasError.value = true;
    animations.value = [];
};

const selectAnimation = (index: number) => {
    if (!viewer.value) {
        return;
    }

    viewer.value.selectedAnimation = index;

    if (!viewer.value.isAnimationPlaying) {
        viewer.value.toggleAnimation();
    }
};
</script>

<template>
    <div class="space-y-3">
        <!-- Sin modelo -->
        <div
            v-if="!source"
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[#DCE7DF] bg-[#F8FAF8] px-4 py-8 text-center"
            :class="height ? null : heightClass"
            :style="height ? { height } : undefined"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <Box :size="24" />
            </div>

            <p class="max-w-xs text-sm text-[#718477]">
                {{ emptyText }}
            </p>
        </div>

        <!-- Visor -->
        <div
            v-else
            class="relative overflow-hidden rounded-xl border border-[#DCE7DF] bg-[#F6F9F6]"
            :class="height ? null : heightClass"
            :style="height ? { height } : undefined"
        >
            <!--
                source/extension se enlazan como atributos (.attr):
                el visor lee el atributo "source" al inicializarse.
            -->
            <babylon-viewer
                v-if="isReady"
                ref="viewer"
                :source.attr="source"
                :extension.attr="extension ? `.${extension}` : null"
                :engine.attr="engine"
                clear-color="#F6F9F6"
                class="model-viewer block h-full w-full"
                @modelchange="onModelChange"
                @modelerror="onModelError"
                @selectedanimationchange="syncAnimationState"
                @pointerdown="clearActiveZoom"
                @wheel.passive="clearActiveZoom"
            />

            <!-- Zoom -->
            <div
                v-if="zoomControls && isReady && !isLoading && !hasError"
                class="absolute top-4 left-4 flex items-center gap-1 rounded-xl border border-[#DCE7DF] bg-white/90 p-1 backdrop-blur-sm"
            >
                <button
                    v-for="preset in ZOOM_PRESETS"
                    :key="preset.id"
                    type="button"
                    :aria-pressed="activeZoom === preset.id"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-colors"
                    :class="
                        activeZoom === preset.id
                            ? 'bg-[#EAF5EE] text-[#187744]'
                            : 'text-[#435D4C] hover:bg-[#F2F7F4]'
                    "
                    @click="applyZoom(preset)"
                >
                    <component :is="preset.icon" :size="15" />
                    {{ preset.label }}
                </button>

                <span class="mx-0.5 h-6 w-px bg-[#DCE7DF]" />

                <button
                    type="button"
                    title="Restablecer vista"
                    aria-label="Restablecer vista"
                    class="inline-flex items-center rounded-lg p-2 text-[#435D4C] transition-colors hover:bg-[#F2F7F4]"
                    @click="resetView"
                >
                    <RotateCcw :size="15" />
                </button>
            </div>

            <!-- Cargando -->
            <div
                v-if="!isReady || (isLoading && !hasError)"
                class="pointer-events-none absolute inset-0 flex items-center justify-center gap-2 text-sm text-[#718477]"
            >
                <LoaderCircle :size="18" class="animate-spin text-[#187744]" />
                Cargando modelo...
            </div>

            <!-- Error -->
            <div
                v-if="hasError"
                class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-[#F8FAF8] px-4 text-center"
            >
                <TriangleAlert :size="22" class="text-red-500" />

                <p class="form-error max-w-sm">
                    No se pudo cargar el modelo. Verifica que el archivo GLB sea
                    válido.
                </p>
            </div>
        </div>

        <!-- Animaciones -->
        <div
            v-if="source && !hasError && animations.length"
            class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-3.5"
        >
            <p
                class="mb-3 text-xs font-semibold tracking-wider text-[#435D4C] uppercase"
            >
                Animaciones ({{ animations.length }})
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(name, index) in animations"
                    :key="`${index}-${name}`"
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        selectedAnimation === index
                            ? 'border-[#187744] bg-[#E4F2E8] text-[#187744]'
                            : 'border-[#DCE7DF] bg-white text-[#435D4C] hover:bg-[#F2F7F4]'
                    "
                    @click="selectAnimation(index)"
                >
                    {{ name || `Animación ${index + 1}` }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Barra de herramientas del visor con los colores de la app. */
.model-viewer {
    --ui-foreground-color: #ffffff;
    --ui-background-hue: 147;
    --ui-background-saturation: 66%;
    --ui-background-lightness: 28%;
    --ui-background-opacity: 0.85;
}
</style>
