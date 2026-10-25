<script setup lang="ts">
import type { Material } from '@babylonjs/core/Materials/material';
import type { BaseTexture } from '@babylonjs/core/Materials/Textures/baseTexture';
import type { Texture } from '@babylonjs/core/Materials/Textures/texture';
import type { Scene } from '@babylonjs/core/scene';
import type { Nullable } from '@babylonjs/core/types';
import { useDebounceFn, useResizeObserver } from '@vueuse/core';
import { Download, FileCode, IdCard, TriangleAlert } from 'lucide-vue-next';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import CardTemplate from '@/components/admin/cards/CardTemplate.vue';
import ModelViewer from '@/components/admin/cards/ModelViewer.vue';
import { resolveAccent } from '@/lib/cardColors';
import type { AccentColor } from '@/lib/cardColors';
import { ensureCardFont } from '@/lib/cardFonts';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';
import {
    CARD_EXPORT_PIXEL_RATIO,
    CARD_TEXTURE_PIXEL_RATIO,
    cardFilename,
    dataUrlToFile,
    downloadDataUrl,
    renderCardPng,
    renderCardSvg,
} from '@/lib/cardImage';

const props = defineProps<{
    title: string;
    scientificName: string;
    astucia: number | string | null;
    rapidez: number | string | null;
    fuerza: number | string | null;
    artworkUrl: string | null;
    fadeColor: string;
    accentColor: AccentColor | null;
    titleFontFamily: CardFontId;
    titleFontWeight: number;
    titleFontSize: TitleSize;
}>();

/*
 * Acento final: el elegido o, en automático, blanco/negro según el degradado.
 */
const resolvedAccent = computed(() =>
    resolveAccent(props.fadeColor, props.accentColor),
);

/*
 * Modelo base de la tarjeta y material donde va la imagen generada.
 */
const CARD_MODEL_URL = '/models/card.glb?v=2';
const CARD_MATERIAL = 'CardImage';

/*
 * Tamaño real de la plantilla, alto máximo de la vista previa
 * y espacio del marco (padding + borde) alrededor de la tarjeta 2D.
 */
const CARD_WIDTH = 572;
const CARD_HEIGHT = 1016;
const PREVIEW_MAX_HEIGHT = 440;
const PREVIEW_FRAME = 18;

const container = useTemplateRef<HTMLElement>('container');
const template = useTemplateRef<InstanceType<typeof CardTemplate>>('template');

const scale = ref(PREVIEW_MAX_HEIGHT / CARD_HEIGHT);

useResizeObserver(container, ([entry]) => {
    scale.value = Math.min(
        entry.contentRect.width / CARD_WIDTH,
        PREVIEW_MAX_HEIGHT / CARD_HEIGHT,
    );
});

const templateNode = (): HTMLElement | null =>
    (template.value?.$el as HTMLElement | undefined) ?? null;

/*
 * Plantilla lista para convertirse en imagen: la fuente del nombre
 * se carga bajo demanda y debe estar disponible antes de dibujar.
 */
const readyTemplateNode = async (): Promise<HTMLElement | null> => {
    await ensureCardFont(props.titleFontFamily, props.titleFontWeight);

    return templateNode();
};

/*
 * Textura del visor 3D.
 */
type CardMaterial = Material & {
    albedoTexture?: Nullable<BaseTexture>;
    baseColorTexture?: Nullable<BaseTexture>;
};

/*
 * Lo que se usa del visor: la escena y el aviso de que cambió.
 * El visor solo vuelve a dibujar cuando algo cambia (cámara, animación);
 * cambiar una textura no cuenta, así que hay que avisarle.
 */
type ViewerScene = {
    scene: Scene;
    markSceneMutated: () => void;
};

let viewerScene: ViewerScene | null = null;
let TextureClass: typeof Texture | null = null;
let pendingTexture: Texture | null = null;

const textureUrl = ref<string | null>(null);
const renderFailed = ref(false);

const applyTexture = () => {
    const current = viewerScene;
    const material = current?.scene.getMaterialByName(
        CARD_MATERIAL,
    ) as CardMaterial | null;

    if (!current || !TextureClass || !material || !textureUrl.value) {
        return;
    }

    // PBRMaterial usa albedoTexture; OpenPBRMaterial, baseColorTexture.
    const slot =
        'albedoTexture' in material ? 'albedoTexture' : 'baseColorTexture';

    pendingTexture?.dispose();

    // Se reemplaza al terminar de cargar para evitar parpadeos.
    const next: Texture = new TextureClass(textureUrl.value, current.scene, {
        invertY: false, // Convención de glTF.
        onLoad: () => {
            if (next !== pendingTexture) {
                return;
            }

            const previous = material[slot] ?? null;

            pendingTexture = null;
            next.coordinatesIndex = previous?.coordinatesIndex ?? 0;
            next.anisotropicFilteringLevel = 8;
            material[slot] = next;
            previous?.dispose();
            current.markSceneMutated();
        },
    });

    pendingTexture = next;
};

const onModelLoaded = async (details: ViewerScene) => {
    // Babylon se carga solo junto con el visor (igual que en ModelViewer).
    TextureClass ??= (
        await import('@babylonjs/core/Materials/Textures/texture')
    ).Texture;

    viewerScene = details;
    applyTexture();
};

/*
 * Regenerar la textura cuando cambian los datos de la tarjeta.
 */
let renderId = 0;

const regenerate = async () => {
    const id = ++renderId;
    const node = await readyTemplateNode();

    if (!node || id !== renderId) {
        return;
    }

    try {
        const url = await renderCardPng(node, CARD_TEXTURE_PIXEL_RATIO);

        if (id === renderId) {
            textureUrl.value = url;
            renderFailed.value = false;
            applyTexture();
        }
    } catch {
        if (id === renderId) {
            renderFailed.value = true;
        }
    }
};

const scheduleRegenerate = useDebounceFn(regenerate, 400);

watch(
    () => [
        props.title,
        props.scientificName,
        props.astucia,
        props.rapidez,
        props.fuerza,
        props.artworkUrl,
        props.fadeColor,
        props.accentColor,
        props.titleFontFamily,
        props.titleFontWeight,
        props.titleFontSize,
    ],
    scheduleRegenerate,
    { flush: 'post' },
);

onMounted(regenerate);

onBeforeUnmount(() => {
    renderId++;
    viewerScene = null;
});

/*
 * Descargas.
 */
const downloading = ref<'png' | 'svg' | null>(null);

const download = async (format: 'png' | 'svg') => {
    if (downloading.value) {
        return;
    }

    downloading.value = format;

    try {
        const node = await readyTemplateNode();

        if (!node) {
            return;
        }

        const dataUrl =
            format === 'png'
                ? await renderCardPng(node, CARD_EXPORT_PIXEL_RATIO)
                : await renderCardSvg(node);

        downloadDataUrl(dataUrl, cardFilename(props.title, format));
    } catch {
        renderFailed.value = true;
    } finally {
        downloading.value = null;
    }
};

const previewSize = computed(() => ({
    width: `${CARD_WIDTH * scale.value}px`,
    height: `${CARD_HEIGHT * scale.value}px`,
}));

// El visor 3D ocupa el mismo alto que la tarjeta 2D con su marco.
const viewerHeight = computed(
    () => `${CARD_HEIGHT * scale.value + PREVIEW_FRAME}px`,
);

/**
 * PNG final de la tarjeta para guardarlo en el servidor.
 */
const generatePngFile = async (): Promise<File | null> => {
    const node = await readyTemplateNode();

    if (!node) {
        return null;
    }

    return dataUrlToFile(
        await renderCardPng(node, CARD_EXPORT_PIXEL_RATIO),
        cardFilename(props.title, 'png'),
    );
};

defineExpose({ generatePngFile });
</script>

<template>
    <AdminCard
        title="Vista previa de la tarjeta"
        description="Se actualiza mientras editas la tarjeta."
        :icon="IdCard"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <!-- Plantilla -->
                <div class="min-w-0 space-y-2">
                    <span class="form-label">Imagen</span>

                    <div
                        ref="container"
                        class="flex justify-center rounded-xl border border-[#DCE7DF] bg-[#F6F9F6] p-2"
                    >
                        <div class="overflow-hidden" :style="previewSize">
                            <div
                                class="origin-top-left"
                                :style="{ transform: `scale(${scale})` }"
                            >
                                <CardTemplate
                                    ref="template"
                                    :title="title"
                                    :scientific-name="scientificName"
                                    :astucia="astucia"
                                    :rapidez="rapidez"
                                    :fuerza="fuerza"
                                    :artwork-url="artworkUrl"
                                    :fade-color="fadeColor"
                                    :accent-color="resolvedAccent"
                                    :title-font-family="titleFontFamily"
                                    :title-font-weight="titleFontWeight"
                                    :title-font-size="titleFontSize"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modelo 3D -->
                <div class="min-w-0 space-y-2">
                    <span class="form-label">Modelo 3D</span>

                    <ModelViewer
                        :source="CARD_MODEL_URL"
                        extension="glb"
                        :height="viewerHeight"
                        :zoom-controls="false"
                        @loaded="onModelLoaded"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <AdminButton
                    variant="secondary"
                    :icon="Download"
                    :loading="downloading === 'png'"
                    :disabled="downloading !== null"
                    loading-text="Generando..."
                    @click="download('png')"
                >
                    PNG
                </AdminButton>

                <AdminButton
                    variant="secondary"
                    :icon="FileCode"
                    :loading="downloading === 'svg'"
                    :disabled="downloading !== null"
                    loading-text="Generando..."
                    @click="download('svg')"
                >
                    SVG
                </AdminButton>

                <p class="form-help">
                    PNG de 1716 × 3048 px. El SVG puede no abrirse bien en
                    editores vectoriales.
                </p>
            </div>

            <p v-if="renderFailed" class="form-error flex items-center gap-1.5">
                <TriangleAlert :size="14" />
                No se pudo generar la imagen de la tarjeta.
            </p>
        </div>
    </AdminCard>
</template>
