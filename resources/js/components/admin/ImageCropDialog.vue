<script setup lang="ts">
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import {
    Check,
    Lock,
    RotateCcw,
    RotateCw,
    TriangleAlert,
    Undo2,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    aspectLabel,
    cropOutputSize,
    croppedFile,
    isSmallCrop,
    profileAspect,
    recalledCrop,
    rememberCrop,
} from '@/lib/imageCrop';
import { resize } from '@/lib/imageOptimize';
import type { ImageProfile } from '@/lib/imageOptimize';

/*
 * Recorte antes de subir una imagen. Con proporción fija (perfil con
 * "aspect") no se puede saltar: cancelar deja el campo como estaba. Sin ella
 * hay proporciones sugeridas y "Usar sin recortar".
 */
const props = withDefaults(
    defineProps<{
        // Archivo elegido o URL de la imagen guardada (mismo origen).
        source: File | string | null;
        profile: ImageProfile;
        title: string;
        // Por qué la proporción es fija, en palabras del panel.
        reason?: string;
        // Guía sobre el recorte: lo que tapa la tarjeta, o el círculo del mapa.
        guide?: 'card' | 'circle' | null;
        // Nombre del archivo resultante cuando la fuente es una URL.
        filename?: string;
    }>(),
    { reason: '', guide: null, filename: 'imagen.png' },
);

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    cropped: [file: File];
    // Solo sin proporción fija: se usa el archivo tal cual.
    skip: [];
    cancel: [];
}>();

const aspect = computed(() => profileAspect(props.profile));
const locked = computed(() => aspect.value !== null);
const lockLabel = computed(() => aspectLabel(props.profile));

const PRESETS = [
    { label: 'Libre', value: NaN },
    { label: '1:1', value: 1 },
    { label: '4:3', value: 4 / 3 },
    { label: '3:4', value: 3 / 4 },
    { label: '16:9', value: 16 / 9 },
];

const preset = ref(NaN);

const image = ref<HTMLImageElement | null>(null);
const imageUrl = ref<string | null>(null);
let objectUrl: string | null = null;
let cropper: Cropper | null = null;

const output = ref<[number, number] | null>(null);
const applying = ref(false);
const failed = ref('');
// Se resolvió con aplicar o saltar: al cerrarse no es una cancelación.
let settled = false;

const small = computed(
    () =>
        output.value !== null &&
        isSmallCrop(output.value[0], output.value[1], props.profile),
);

const releaseUrl = () => {
    if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
};

const destroyCropper = () => {
    cropper?.destroy();
    cropper = null;
};

watch(
    [open, () => props.source],
    ([isOpen, source]) => {
        destroyCropper();
        releaseUrl();
        imageUrl.value = null;
        output.value = null;
        failed.value = '';

        if (!isOpen || !source) {
            return;
        }

        settled = false;
        preset.value = NaN;

        if (typeof source === 'string') {
            imageUrl.value = source;
        } else {
            objectUrl = URL.createObjectURL(source);
            imageUrl.value = objectUrl;
        }
    },
    { immediate: true },
);

watch(open, (isOpen, wasOpen) => {
    if (wasOpen && !isOpen && !settled) {
        emit('cancel');
    }
});

onBeforeUnmount(() => {
    destroyCropper();
    releaseUrl();
});

/*
 * Guía dibujada dentro del recuadro de recorte. Es HTML fijo (sin datos del
 * usuario) y se mueve con el recuadro.
 */
const GUIDES: Record<'card' | 'circle', string> = {
    card: `
        <div class="crop-guide-fade-top"></div>
        <div class="crop-guide-fade-bottom"></div>
        <div class="crop-guide-zone crop-guide-logo"><span>Logo</span></div>
        <div class="crop-guide-zone crop-guide-text"><span>Nombre y estadísticas</span></div>`,
    circle: '<div class="crop-guide-circle"></div>',
};

const addGuide = () => {
    const box = image.value?.parentElement?.querySelector('.cropper-crop-box');

    if (!box || !props.guide) {
        return;
    }

    const guide = document.createElement('div');
    guide.className = 'crop-guide';
    guide.innerHTML = GUIDES[props.guide];
    box.appendChild(guide);
};

const updateOutput = () => {
    if (!cropper) {
        return;
    }

    const data = cropper.getData();

    output.value =
        data.width > 0 && data.height > 0
            ? cropOutputSize(data.width, data.height, props.profile)
            : null;
};

const onImageLoad = async () => {
    await nextTick();

    if (!image.value) {
        return;
    }

    destroyCropper();
    cropper = new Cropper(image.value, {
        viewMode: 1,
        dragMode: 'move',
        // El recuadro empieza con el área más grande que cabe, centrada.
        autoCropArea: 1,
        aspectRatio: aspect.value ?? NaN,
        background: false,
        toggleDragModeOnDblclick: false,
        ready: () => {
            restoreCrop();
            addGuide();
            updateOutput();
        },
        crop: updateOutput,
    });
};

// "Recortar de nuevo" parte del último recorte aplicado a esta foto.
const restoreCrop = () => {
    const memory = props.source ? recalledCrop(props.source) : null;

    if (!cropper || !memory) {
        return;
    }

    if (!locked.value && !Number.isNaN(memory.aspectRatio)) {
        choosePreset(memory.aspectRatio);
    }

    cropper.setData(memory.data);
};

const onImageError = () => {
    failed.value = 'No se pudo abrir la imagen. Prueba con otro archivo.';
};

const choosePreset = (value: number) => {
    preset.value = value;
    cropper?.setAspectRatio(value);
};

const zoom = (ratio: number) => cropper?.zoom(ratio);
const rotate = (degrees: number) => cropper?.rotate(degrees);

const reset = () => {
    cropper?.reset();

    if (!locked.value) {
        choosePreset(NaN);
    }
};

const apply = async () => {
    if (!cropper || !output.value || applying.value) {
        return;
    }

    applying.value = true;
    failed.value = '';

    try {
        const [width, height] = output.value;
        // Recorte al tamaño original y luego reducido con el mismo método que la compresión.
        const canvas = resize(
            cropper.getCroppedCanvas({
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            }),
            width,
            height,
        );

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/png'),
        );

        if (!blob) {
            throw new Error('toBlob');
        }

        const name =
            props.source instanceof File ? props.source.name : props.filename;

        if (props.source) {
            rememberCrop(props.source, {
                data: cropper.getData(),
                aspectRatio: locked.value ? NaN : preset.value,
            });
        }

        settled = true;
        emit('cropped', croppedFile(blob, name, props.source ?? undefined));
        open.value = false;
    } catch {
        failed.value =
            'No se pudo recortar la imagen. Intenta de nuevo o elige otro archivo.';
    } finally {
        applying.value = false;
    }
};

const skip = () => {
    settled = true;
    emit('skip');
    open.value = false;
};

const onKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;

    if (event.key === 'Enter' && target?.tagName !== 'BUTTON') {
        event.preventDefault();
        void apply();
    }
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-3xl"
            @keydown="onKeydown"
        >
            <div class="space-y-1 pr-6">
                <DialogTitle class="text-lg font-semibold text-[#244334]">
                    {{ title }}
                </DialogTitle>

                <DialogDescription class="text-sm text-[#718477]">
                    Arrastra la imagen para moverla y usa la rueda del ratón o
                    los botones para acercarla.
                    <template v-if="reason"> {{ reason }}</template>
                </DialogDescription>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="locked"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#EAF4ED] px-3 py-1 text-xs font-semibold text-[#187744]"
                >
                    <Lock :size="14" />
                    Proporción fija {{ lockLabel }}
                </span>

                <template v-else>
                    <span class="text-xs font-semibold text-[#718477]">
                        Proporción:
                    </span>
                    <button
                        v-for="option in PRESETS"
                        :key="option.label"
                        type="button"
                        :aria-pressed="Object.is(preset, option.value)"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-semibold transition',
                            Object.is(preset, option.value)
                                ? 'border-[#187744] bg-[#187744] text-white'
                                : 'border-[#c8dccf] bg-white text-[#244334] hover:bg-[#F2F7F4]',
                        ]"
                        @click="choosePreset(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </template>
            </div>

            <div
                class="crop-stage h-[min(60vh,520px)] overflow-hidden rounded-xl border border-[#c8dccf] bg-[#244334]"
            >
                <img
                    v-if="imageUrl"
                    ref="image"
                    :src="imageUrl"
                    alt="Imagen a recortar"
                    class="block max-w-full"
                    @load="onImageLoad"
                    @error="onImageError"
                />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="tool in [
                            {
                                icon: ZoomIn,
                                label: 'Acercar',
                                run: () => zoom(0.1),
                            },
                            {
                                icon: ZoomOut,
                                label: 'Alejar',
                                run: () => zoom(-0.1),
                            },
                            {
                                icon: RotateCcw,
                                label: 'Girar a la izquierda',
                                run: () => rotate(-90),
                            },
                            {
                                icon: RotateCw,
                                label: 'Girar a la derecha',
                                run: () => rotate(90),
                            },
                            { icon: Undo2, label: 'Restablecer', run: reset },
                        ]"
                        :key="tool.label"
                        type="button"
                        :title="tool.label"
                        :aria-label="tool.label"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#c8dccf] bg-white text-[#244334] transition hover:bg-[#F2F7F4]"
                        @click="tool.run"
                    >
                        <component :is="tool.icon" :size="16" />
                    </button>
                </div>

                <p v-if="output" class="text-xs text-[#718477]">
                    Resultado: {{ output[0] }} × {{ output[1] }} px
                </p>
            </div>

            <p
                v-if="small"
                class="flex items-start gap-2 rounded-lg bg-[#fffaeb] px-3 py-2 text-xs text-[#8a5a00]"
            >
                <TriangleAlert :size="14" class="mt-0.5 shrink-0" />
                La imagen recortada es pequeña y puede verse borrosa en la app.
                Si puedes, usa una foto de mayor resolución.
            </p>

            <p
                v-if="failed"
                class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600"
            >
                {{ failed }}
            </p>

            <div class="flex flex-wrap justify-end gap-2">
                <AdminButton variant="secondary" @click="open = false">
                    Cancelar
                </AdminButton>

                <AdminButton v-if="!locked" variant="secondary" @click="skip">
                    Usar sin recortar
                </AdminButton>

                <AdminButton
                    :icon="Check"
                    :loading="applying"
                    loading-text="Recortando…"
                    :disabled="!output"
                    @click="apply"
                >
                    Aplicar recorte
                </AdminButton>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style>
/* Sin scoped: la guía se crea dentro del recuadro de cropperjs. */
.crop-guide {
    position: absolute;
    inset: 0;
    pointer-events: none;
}

.crop-guide-fade-top,
.crop-guide-fade-bottom {
    position: absolute;
    inset-inline: 0;
}

.crop-guide-fade-top {
    top: 0;
    height: 30%;
    background: linear-gradient(to bottom, rgb(36 67 52 / 0.55), transparent);
}

.crop-guide-fade-bottom {
    bottom: 0;
    height: 75%;
    background: linear-gradient(to top, rgb(36 67 52 / 0.55), transparent);
}

.crop-guide-zone {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px dashed rgb(255 255 255 / 0.85);
    background: rgb(255 255 255 / 0.18);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    text-shadow: 0 1px 2px rgb(0 0 0 / 0.6);
}

/* Medidas de CardTemplate.vue (572 × 1016): recuadro del logo y textos. */
.crop-guide-logo {
    top: 0;
    right: 0;
    width: 36%;
    height: 33%;
}

.crop-guide-text {
    inset-inline: 6%;
    bottom: 4%;
    height: 26%;
}

.crop-guide-circle {
    position: absolute;
    inset: 0;
    border: 2px dashed rgb(255 255 255 / 0.9);
    border-radius: 9999px;
}

/* Esquinas que el marcador redondo del mapa no muestra. */
.crop-guide:has(.crop-guide-circle) {
    background: radial-gradient(
        circle closest-side,
        transparent 99%,
        rgb(36 67 52 / 0.35) 100%
    );
}

.crop-stage .cropper-view-box {
    outline-color: #8fd3a8;
}

.crop-stage .cropper-line,
.crop-stage .cropper-point {
    background-color: #8fd3a8;
}
</style>
