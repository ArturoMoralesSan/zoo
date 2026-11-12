/**
 * Compresión WebP de las imágenes de las tarjetas, en el navegador.
 *
 * Se usa libwebp en WASM (@jsquash/webp) porque el resultado es igual en
 * todos los navegadores (Safari no siempre codifica WebP con canvas) y no
 * depende del hosting. Ver docs/image-optimization.md.
 */

import profiles from '../../data/card-image-profiles.json';

export type CardImageKind = 'generated' | 'thumbnail' | 'artwork';

export interface ImageProfile {
    maxWidth: number;
    maxHeight: number;
    quality: number;
}

/**
 * Compartidos con el servidor (config/images.php).
 * - generated: 2× la plantilla (572 × 1016), igual que la textura del visor 3D.
 * - artwork: alimenta futuros renders de la tarjeta, conserva más resolución.
 */
export const CARD_IMAGE_PROFILES: Record<CardImageKind, ImageProfile> =
    profiles;

/**
 * Tamaño final conservando la proporción. Nunca agranda.
 */
export const targetSize = (
    width: number,
    height: number,
    profile: ImageProfile,
): [number, number] => {
    const scale = Math.min(
        1,
        profile.maxWidth / width,
        profile.maxHeight / height,
    );

    return [
        Math.max(1, Math.min(profile.maxWidth, Math.round(width * scale))),
        Math.max(1, Math.min(profile.maxHeight, Math.round(height * scale))),
    ];
};

export const fitsProfile = (
    width: number,
    height: number,
    profile: ImageProfile,
): boolean => width <= profile.maxWidth && height <= profile.maxHeight;

/**
 * Mismo nombre con extensión .webp.
 */
export const webpFilename = (name: string): string =>
    `${name.replace(/\.[^./]+$/, '') || 'imagen'}.webp`;

export type Drawable = HTMLCanvasElement | ImageBitmap;

const createCanvas = (width: number, height: number): HTMLCanvasElement => {
    const canvas = document.createElement('canvas');

    canvas.width = width;
    canvas.height = height;

    return canvas;
};

/**
 * Reducir a la mitad hasta acercarse al tamaño final: un solo drawImage
 * con una reducción grande (p. ej. 4000 → 800 px) deja la imagen granulada.
 */
export const resize = (
    source: Drawable,
    width: number,
    height: number,
): HTMLCanvasElement => {
    let current: Drawable = source;

    while (current.width / 2 >= width && current.height / 2 >= height) {
        current = draw(
            current,
            Math.round(current.width / 2),
            Math.round(current.height / 2),
        );
    }

    return draw(current, width, height);
};

const draw = (
    source: Drawable,
    width: number,
    height: number,
): HTMLCanvasElement => {
    const canvas = createCanvas(width, height);
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('No se pudo crear el lienzo.');
    }

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(source, 0, 0, width, height);

    return canvas;
};

/**
 * libwebp en WASM. Se carga solo al usarse (≈ 300 KB).
 */
const encodeWithWasm = async (
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob> => {
    const { default: encode } = await import('@jsquash/webp/encode');

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('No se pudo leer el lienzo.');
    }

    const buffer = await encode(
        context.getImageData(0, 0, canvas.width, canvas.height),
        {
            quality,
            /*
             * method 4: la tarjeta pesa ~5 % más que con 6, pero se codifica
             * en ~0,7 s en lugar de ~6 s (se hace al guardar).
             */
            method: 4,
            // Evita que el color se corra alrededor del texto de la tarjeta.
            use_sharp_yuv: 1,
            alpha_quality: 100,
        },
    );

    return new Blob([buffer], { type: 'image/webp' });
};

/**
 * Respaldo si el WASM no carga. Safari puede devolver PNG en lugar de WebP.
 */
const encodeWithCanvas = (
    canvas: HTMLCanvasElement,
    quality: number,
): Promise<Blob | null> =>
    new Promise((resolve) => {
        canvas.toBlob(
            (blob) => resolve(blob?.type === 'image/webp' ? blob : null),
            'image/webp',
            quality / 100,
        );
    });

/**
 * Codificar como WebP dentro del perfil.
 *
 * Devuelve null si el navegador no puede producir WebP: quien llama
 * conserva el archivo original. Guardar nunca falla por la compresión.
 */
export const encodeWebp = async (
    source: Blob | HTMLCanvasElement,
    profile: ImageProfile,
    filename: string,
): Promise<File | null> => {
    try {
        // Con un Blob, createImageBitmap aplica la orientación EXIF.
        const drawable =
            source instanceof Blob ? await createImageBitmap(source) : source;

        const [width, height] = targetSize(
            drawable.width,
            drawable.height,
            profile,
        );

        const canvas =
            drawable instanceof HTMLCanvasElement &&
            drawable.width === width &&
            drawable.height === height
                ? drawable
                : resize(drawable, width, height);

        if (drawable instanceof ImageBitmap) {
            drawable.close();
        }

        let blob: Blob | null;

        try {
            blob = await encodeWithWasm(canvas, profile.quality);
        } catch (error) {
            console.warn('WebP (WASM) no disponible, se usa canvas.', error);
            blob = await encodeWithCanvas(canvas, profile.quality);
        }

        return blob
            ? new File([blob], webpFilename(filename), { type: 'image/webp' })
            : null;
    } catch (error) {
        console.warn('No se pudo comprimir la imagen.', error);

        return null;
    }
};

/*
 * Un resultado por archivo y tipo: el campo calcula el peso al elegir la
 * imagen y el formulario reutiliza el mismo archivo al guardar.
 */
const uploads = new WeakMap<File, Map<CardImageKind, Promise<File>>>();

/**
 * Para los campos de archivo: el WebP si es más ligero, si no el original.
 */
export const optimizeUpload = (
    file: File,
    kind: CardImageKind,
): Promise<File> => {
    const byKind = uploads.get(file) ?? new Map<CardImageKind, Promise<File>>();
    const cached = byKind.get(kind);

    if (cached) {
        return cached;
    }

    const result = optimizeUploadNow(file, kind);

    byKind.set(kind, result);
    uploads.set(file, byKind);

    return result;
};

const optimizeUploadNow = async (
    file: File,
    kind: CardImageKind,
): Promise<File> => {
    const profile = CARD_IMAGE_PROFILES[kind];

    // Un WebP que ya cabe no se vuelve a codificar (perdería calidad).
    if (file.type === 'image/webp') {
        const fits = await createImageBitmap(file)
            .then((bitmap) => {
                const result = fitsProfile(
                    bitmap.width,
                    bitmap.height,
                    profile,
                );

                bitmap.close();

                return result;
            })
            .catch(() => false);

        if (fits) {
            return file;
        }
    }

    const optimized = await encodeWebp(file, profile, file.name);

    return optimized && optimized.size < file.size ? optimized : file;
};
