<script setup lang="ts">
import { Crop, FileImage, Lock, Trash2, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import SizeSavings from '@/components/admin/cards/SizeSavings.vue';
import ImageCropDialog from '@/components/admin/ImageCropDialog.vue';
import { useObjectUrl } from '@/composables/useObjectUrl';
import { aspectLabel, cropOrigin, cropSource } from '@/lib/imageCrop';
import { CARD_IMAGE_PROFILES, optimizeUpload } from '@/lib/imageOptimize';
import type { CardImageKind } from '@/lib/imageOptimize';
import { storageUrl } from '@/lib/modelPreview';

/*
 * Campo de imagen de la tarjeta (miniatura o imagen de la plantilla):
 * imagen actual con opción de eliminarla y selector de archivo nuevo.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        help: string;
        currentPath?: string | null;
        alt?: string;
        error?: string;
        disabled?: boolean;
        // Perfil de compresión de la imagen (lib/imageOptimize.ts).
        kind: CardImageKind;
        // Peso de la imagen guardada y el que tenía antes de comprimirla.
        currentBytes?: number | null;
        originalBytes?: number | null;
    }>(),
    {
        currentPath: null,
        alt: '',
        error: '',
        disabled: false,
        currentBytes: null,
        originalBytes: null,
    },
);

const file = defineModel<File | null>('file', { required: true });

const emit = defineEmits<{
    delete: [];
}>();

const filePreviewUrl = useObjectUrl(() => file.value);

/*
 * Peso que tendrá la imagen elegida. optimizeUpload guarda el resultado,
 * así que al enviar el formulario no se vuelve a codificar.
 */
const estimatedBytes = ref<number | null>(null);
const estimating = ref(false);

watch(file, async (value) => {
    estimatedBytes.value = null;
    estimating.value = Boolean(value);

    if (!value) {
        return;
    }

    const optimized = await optimizeUpload(value, props.kind);

    if (file.value === value) {
        estimatedBytes.value = optimized.size;
        estimating.value = false;
    }
});

const handleFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const picked = target.files?.[0] ?? null;

    // Permite volver a elegir el mismo archivo tras cancelar.
    target.value = '';

    if (picked) {
        openCrop(picked);
    }
};

/*
 * Recorte antes de comprimir. La miniatura (1:1) y la ilustración (572:1016)
 * tienen proporción fija: el archivo solo entra al formulario recortado.
 */
const profile = computed(() => CARD_IMAGE_PROFILES[props.kind]);
const lockLabel = computed(() => aspectLabel(profile.value));

const CROP_TEXTS: Partial<
    Record<CardImageKind, { reason: string; guide: 'card' | 'circle' }>
> = {
    artwork: {
        reason: 'La tarjeta usa esta proporción; lo que quede fuera del marco no se verá. Mantén al animal fuera de las zonas marcadas.',
        guide: 'card',
    },
    thumbnail: {
        reason: 'La miniatura se muestra en cuadros y círculos de la app.',
        guide: 'circle',
    },
};

const cropOpen = ref(false);
const cropFrom = ref<File | string | null>(null);

const openCrop = (source: File | string) => {
    cropFrom.value = source;
    cropOpen.value = true;
};

// Solo sin proporción fija: el archivo elegido, tal cual.
const useUncropped = () => {
    if (cropFrom.value instanceof File) {
        file.value = cropFrom.value;
    }
};

const recropCurrent = () => {
    if (props.currentPath) {
        openCrop(storageUrl(props.currentPath) ?? props.currentPath);
    }
};

const currentFilename = computed(
    () => props.currentPath?.split('/').pop() ?? 'imagen.png',
);
</script>

<template>
    <div class="space-y-4">
        <!-- Imagen actual -->
        <div
            v-if="currentPath"
            class="flex flex-col gap-5 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:flex-row sm:items-start"
        >
            <img
                :src="storageUrl(currentPath) ?? ''"
                :alt="alt"
                class="h-44 w-32 shrink-0 rounded-lg border border-[#DCE7DF] bg-white object-cover"
            />

            <div class="flex min-w-0 flex-1 flex-col items-start gap-4">
                <div>
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                    >
                        <FileImage :size="16" />
                        Imagen actual
                    </p>

                    <p class="mt-2 text-xs break-all text-[#718477]">
                        {{ currentPath }}
                    </p>

                    <SizeSavings
                        class="mt-2"
                        :original="originalBytes"
                        :optimized="currentBytes"
                    />
                </div>

                <button
                    v-if="!file"
                    type="button"
                    :disabled="props.disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#c8dccf] bg-white px-4 py-2.5 text-sm font-semibold text-[#244334] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-50"
                    @click="recropCurrent"
                >
                    <Crop :size="16" />
                    Recortar imagen actual
                </button>

                <button
                    type="button"
                    :disabled="props.disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="emit('delete')"
                >
                    <Trash2 :size="16" />
                    Eliminar imagen
                </button>
            </div>
        </div>

        <!-- Nueva imagen -->
        <div class="form-field">
            <label :for="id" class="form-label">
                {{ currentPath ? `Reemplazar ${label.toLowerCase()}` : label }}
            </label>

            <label :for="id" class="form-upload">
                <img
                    v-if="filePreviewUrl"
                    :src="filePreviewUrl"
                    :alt="file?.name"
                    class="h-24 w-auto rounded-lg object-contain"
                />

                <div v-else class="form-upload-icon">
                    <Upload :size="22" />
                </div>

                <span class="form-upload-title break-all">
                    {{ file?.name || 'Selecciona una imagen' }}
                </span>

                <span class="form-upload-description">
                    {{ help }}
                </span>

                <span class="form-upload-description">
                    JPG, JPEG, PNG o WEBP · Máximo 5 MB
                </span>

                <span
                    v-if="lockLabel"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#187744]"
                >
                    <Lock :size="12" />
                    Se recorta a proporción {{ lockLabel }}
                </span>

                <input
                    :id="id"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="form-upload-input"
                    @change="handleFile"
                />
            </label>

            <button
                v-if="file"
                type="button"
                :disabled="props.disabled"
                class="inline-flex items-center gap-2 text-sm font-semibold text-[#187744] hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                @click="openCrop(cropOrigin(file))"
            >
                <Crop :size="16" />
                Recortar de nuevo
            </button>

            <SizeSavings
                v-if="file"
                :original="cropSource(file).size"
                :optimized="estimatedBytes"
                :estimating="estimating"
                :previous="currentPath ? currentBytes : null"
            />

            <p v-if="error" class="form-error">
                {{ error }}
            </p>
        </div>

        <ImageCropDialog
            v-model:open="cropOpen"
            :source="cropFrom"
            :profile="profile"
            :title="`Recortar ${label.toLowerCase()}`"
            :reason="CROP_TEXTS[kind]?.reason"
            :guide="CROP_TEXTS[kind]?.guide"
            :filename="currentFilename"
            @cropped="file = $event"
            @skip="useUncropped"
        />
    </div>
</template>
