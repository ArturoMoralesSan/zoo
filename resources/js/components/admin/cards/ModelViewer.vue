<script setup lang="ts">
import type { HTML3DElement } from '@babylonjs/viewer';
import { Box, LoaderCircle, Pause, Play, TriangleAlert } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import type { ModelExtension } from '@/lib/modelPreview';

const props = withDefaults(
    defineProps<{
        source: string | null;
        extension?: ModelExtension | null;
        heightClass?: string;
        emptyText?: string;
    }>(),
    {
        extension: null,
        heightClass: 'h-[360px]',
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

const animations = ref<string[]>([]);
const selectedAnimation = ref<number | null>(null);
const isPlaying = ref(false);

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
        isPlaying.value = false;
    },
    { immediate: true },
);

const syncAnimationState = () => {
    selectedAnimation.value = viewer.value?.selectedAnimation ?? null;
    isPlaying.value = viewer.value?.isAnimationPlaying ?? false;
};

const onModelChange = () => {
    isLoading.value = false;
    hasError.value = false;
    animations.value = [...(viewer.value?.animations ?? [])];
    syncAnimationState();

    if (viewer.value?.viewerDetails) {
        emit('loaded', viewer.value.viewerDetails);
    }
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

const toggleAnimation = () => {
    viewer.value?.toggleAnimation();
};
</script>

<template>
    <div class="space-y-3">
        <!-- Sin modelo -->
        <div
            v-if="!source"
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-[#DCE7DF] bg-[#F8FAF8] px-4 py-8 text-center"
            :class="heightClass"
        >
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]">
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
            :class="heightClass"
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
                animation-auto-play
                clear-color="#F6F9F6"
                class="model-viewer block h-full w-full"
                @modelchange="onModelChange"
                @modelerror="onModelError"
                @selectedanimationchange="syncAnimationState"
                @animationplayingchange="syncAnimationState"
            />

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
                    No se pudo cargar el modelo. Verifica que el archivo GLB sea válido.
                </p>
            </div>
        </div>

        <!-- Animaciones -->
        <div
            v-if="source && !hasError && animations.length"
            class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-3.5"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-[#435D4C]">
                    Animaciones ({{ animations.length }})
                </p>

                <AdminButton
                    variant="secondary"
                    :icon="isPlaying ? Pause : Play"
                    class="w-[8.5rem]"
                    @click="toggleAnimation"
                >
                    {{ isPlaying ? 'Pausar' : 'Reproducir' }}
                </AdminButton>
            </div>

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
