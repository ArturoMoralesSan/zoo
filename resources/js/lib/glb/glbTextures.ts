import type { Document, Texture } from '@gltf-transform/core';

import type { GlbTextureProfile } from '@/lib/glb/modelSize';
import { resize } from '@/lib/imageOptimize';

/**
 * Texturas del modelo escalado: se reducen a config('glb.textures.max_size')
 * y se vuelven a codificar. ViroReact solo lee JPEG/PNG (sin KTX2 ni
 * EXT_texture_webp), así que el ahorro viene de la resolución y la calidad.
 * Ver docs/glb-resizing.md.
 */

export type TextureFormat = 'image/jpeg' | 'image/png';

export interface TextureEncodeOptions {
    maxSize: number;
    quality: number;
    format: TextureFormat;
}

/**
 * Recibe los bytes de la imagen y devuelve los nuevos. Lanza si no puede.
 */
export type TextureEncoder = (
    image: Uint8Array,
    mimeType: string,
    options: TextureEncodeOptions,
) => Promise<Uint8Array>;

export interface TextureBytes {
    before: number;
    after: number;
}

export const TEXTURE_WARNING =
    'Algunas texturas no se pudieron optimizar y se guardaron como estaban.';

/**
 * Tamaño final conservando la proporción. Nunca agranda.
 */
export const fitTexture = (
    width: number,
    height: number,
    maxSize: number,
): [number, number] => {
    const scale = Math.min(1, maxSize / Math.max(width, height));

    return [
        Math.max(1, Math.round(width * scale)),
        Math.max(1, Math.round(height * scale)),
    ];
};

/**
 * Codificador del navegador: canvas con JPEG/PNG (todos los navegadores).
 */
const browserEncoder: TextureEncoder = async (image, mimeType, options) => {
    /*
     * Sin gestión de color: los mapas normales y metallicRoughness son
     * datos, no colores, y no deben convertirse.
     */
    const bitmap = await createImageBitmap(
        new Blob([image as BlobPart], { type: mimeType }),
        { colorSpaceConversion: 'none', premultiplyAlpha: 'none' },
    );

    const [width, height] = fitTexture(
        bitmap.width,
        bitmap.height,
        options.maxSize,
    );

    const canvas = resize(bitmap, width, height);

    bitmap.close();

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, options.format, options.quality / 100),
    );

    if (!blob || blob.type !== options.format) {
        throw new Error('El navegador no pudo codificar la textura.');
    }

    return new Uint8Array(await blob.arrayBuffer());
};

let encoder: TextureEncoder = browserEncoder;

/**
 * Las pruebas (Node, sin canvas) inyectan su propio codificador.
 */
export function setTextureEncoder(value: TextureEncoder | null): void {
    encoder = value ?? browserEncoder;
}

/**
 * PNG solo si la textura puede llevar transparencia: color base o emisiva
 * de un material no opaco, o un PNG usado como color base. Los mapas
 * normales y metallicRoughness siempre son JPEG.
 */
const formatFor = (document: Document, texture: Texture): TextureFormat => {
    for (const material of document.getRoot().listMaterials()) {
        const isColor =
            material.getBaseColorTexture() === texture ||
            material.getEmissiveTexture() === texture;

        if (
            isColor &&
            (material.getAlphaMode() !== 'OPAQUE' ||
                texture.getMimeType() === 'image/png')
        ) {
            return 'image/png';
        }
    }

    return 'image/jpeg';
};

/**
 * Reducir y volver a codificar todas las texturas del documento.
 *
 * Nunca falla: si una textura no se puede codificar o no pesa menos, se
 * queda como estaba y se devuelve una advertencia.
 */
export async function optimizeTextures(
    document: Document,
    profile: GlbTextureProfile,
): Promise<{ bytes: TextureBytes; warnings: string[] }> {
    const bytes: TextureBytes = { before: 0, after: 0 };
    let failed = false;

    for (const texture of document.getRoot().listTextures()) {
        const image = texture.getImage();

        if (!image) {
            continue;
        }

        bytes.before += image.byteLength;

        const format = formatFor(document, texture);

        try {
            const encoded = await encoder(image, texture.getMimeType(), {
                maxSize: profile.maxSize,
                quality: profile.quality,
                format,
            });

            if (encoded.byteLength < image.byteLength) {
                texture.setImage(encoded).setMimeType(format);
                bytes.after += encoded.byteLength;

                continue;
            }
        } catch {
            failed = true;
        }

        bytes.after += image.byteLength;
    }

    return { bytes, warnings: failed ? [TEXTURE_WARNING] : [] };
}
