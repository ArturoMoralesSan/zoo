import { ref, watch } from 'vue';
import type { Ref } from 'vue';

import { optimizeImage, SPECIES_IMAGE_PROFILES } from '@/lib/imageOptimize';
import type { SpeciesImageKind } from '@/lib/imageOptimize';

/*
 * Compresión WebP de las imágenes de especies en el navegador, igual que en
 * las tarjetas. optimizeImage guarda cada resultado, así que el peso que se
 * calcula al elegir la imagen y el archivo que se envía al guardar son el
 * mismo. Ver docs/image-optimization.md.
 */

/**
 * Peso que tendrá la imagen elegida una vez comprimida.
 */
export function useEstimatedSize(
    file: () => File | null,
    kind: SpeciesImageKind,
): { bytes: Ref<number | null>; estimating: Ref<boolean> } {
    const bytes = ref<number | null>(null);
    const estimating = ref(false);

    watch(
        file,
        async (value) => {
            bytes.value = null;
            estimating.value = Boolean(value);

            if (!value) {
                return;
            }

            const optimized = await optimizeImage(
                value,
                SPECIES_IMAGE_PROFILES[kind],
            );

            // Pudieron elegir otra imagen mientras se comprimía.
            if (file() === value) {
                bytes.value = optimized.size;
                estimating.value = false;
            }
        },
        { immediate: true },
    );

    return { bytes, estimating };
}

interface SpeciesUploads {
    main_image: File | null;
    thumbnail_image: File | null;
    gallery_images: File[];
}

/**
 * Archivos comprimidos y el peso de cada original, listos para enviar con
 * form.transform(). El formulario conserva los originales por si hay que
 * reenviarlo.
 */
export async function optimizeSpeciesUploads(data: SpeciesUploads) {
    const [main, thumbnail, gallery] = await Promise.all([
        data.main_image
            ? optimizeImage(data.main_image, SPECIES_IMAGE_PROFILES.main)
            : null,
        data.thumbnail_image
            ? optimizeImage(
                  data.thumbnail_image,
                  SPECIES_IMAGE_PROFILES.thumbnail,
              )
            : null,
        Promise.all(
            data.gallery_images.map((file) =>
                optimizeImage(file, SPECIES_IMAGE_PROFILES.gallery),
            ),
        ),
    ]);

    const originalBytes: Partial<Record<'main' | 'thumbnail', number>> = {};

    if (data.main_image) {
        originalBytes.main = data.main_image.size;
    }

    if (data.thumbnail_image) {
        originalBytes.thumbnail = data.thumbnail_image.size;
    }

    return {
        main_image: main,
        thumbnail_image: thumbnail,
        gallery_images: gallery,
        image_original_bytes: originalBytes,
        // Por índice de gallery_images.
        gallery_original_bytes: data.gallery_images.map((file) => file.size),
    };
}
