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
}>();

/*
 * Modelo base de la tarjeta y material donde va la imagen generada.
 */
const CARD_MODEL_URL = '/models/card.glb';
const CARD_MATERIAL = 'CardImage';

/*
 * Tamaño real de la plantilla y alto máximo de la vista previa.
 */
const CARD_WIDTH = 572;
const CARD_HEIGHT = 1016;
const PREVIEW_MAX_HEIGHT = 460;

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
 * Textura del visor 3D.
 */
type CardMaterial = Material & {
    albedoTexture?: Nullable<BaseTexture>;
    baseColorTexture?: Nullable<BaseTexture>;
};

let scene: Scene | null = null;
let TextureClass: typeof Texture | null = null;
let pendingTexture: Texture | null = null;

const textureUrl = ref<string | null>(null);
const renderFailed = ref(false);

const applyTexture = () => {
    const material = scene?.getMaterialByName(
        CARD_MATERIAL,
    ) as CardMaterial | null;

    if (!scene || !TextureClass || !material || !textureUrl.value) {
        return;
    }

    // PBRMaterial usa albedoTexture; OpenPBRMaterial, baseColorTexture.
    const slot =
        'albedoTexture' in material ? 'albedoTexture' : 'baseColorTexture';

    pendingTexture?.dispose();

    // Se reemplaza al terminar de cargar para evitar parpadeos.
    const next: Texture = new TextureClass(textureUrl.value, scene, {
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
        },
    });

    pendingTexture = next;
};

const onModelLoaded = async (details: { scene: Scene }) => {
    // Babylon se carga solo junto con el visor (igual que en ModelViewer).
    TextureClass ??= (
        await import('@babylonjs/core/Materials/Textures/texture')
    ).Texture;

    scene = details.scene;
    applyTexture();
};

/*
 * Regenerar la textura cuando cambian los datos de la tarjeta.
 */
let renderId = 0;

const regenerate = async () => {
    const node = templateNode();

    if (!node) {
        return;
    }

    const id = ++renderId;

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
    ],
    scheduleRegenerate,
    { flush: 'post' },
);

onMounted(regenerate);

onBeforeUnmount(() => {
    renderId++;
    scene = null;
});

/*
 * Descargas.
 */
const downloading = ref<'png' | 'svg' | null>(null);

const download = async (format: 'png' | 'svg') => {
    const node = templateNode();

    if (!node || downloading.value) {
        return;
    }

    downloading.value = format;

    try {
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

/**
 * PNG final de la tarjeta para guardarlo en el servidor.
 */
const generatePngFile = async (): Promise<File | null> => {
    const node = templateNode();

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
        description="Se genera a partir del nombre, la especie, las estadísticas y la imagen de la tarjeta."
        :icon="IdCard"
    >
        <div class="grid gap-5 lg:grid-cols-2">
            <!-- Plantilla -->
            <div class="space-y-3">
                <span class="form-label">Imagen</span>

                <div
                    ref="container"
                    class="flex justify-center rounded-xl border border-[#DCE7DF] bg-[#F6F9F6] p-3"
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
                            />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <AdminButton
                        variant="secondary"
                        :icon="Download"
                        :loading="downloading === 'png'"
                        :disabled="downloading !== null"
                        loading-text="Generando..."
                        @click="download('png')"
                    >
                        Descargar PNG
                    </AdminButton>

                    <AdminButton
                        variant="secondary"
                        :icon="FileCode"
                        :loading="downloading === 'svg'"
                        :disabled="downloading !== null"
                        loading-text="Generando..."
                        @click="download('svg')"
                    >
                        Descargar SVG
                    </AdminButton>
                </div>

                <p class="form-help">
                    PNG en alta calidad (1716 × 3048 px). El SVG se ve
                    correctamente en navegadores, pero puede no abrirse bien en
                    editores vectoriales.
                </p>

                <p
                    v-if="renderFailed"
                    class="form-error flex items-center gap-1.5"
                >
                    <TriangleAlert :size="14" />
                    No se pudo generar la imagen de la tarjeta.
                </p>
            </div>

            <!-- Modelo 3D -->
            <div class="space-y-3">
                <span class="form-label">Modelo 3D</span>

                <ModelViewer
                    :source="CARD_MODEL_URL"
                    extension="glb"
                    height-class="h-[486px]"
                    @loaded="onModelLoaded"
                />
            </div>
        </div>
    </AdminCard>
</template>
