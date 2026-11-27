/**
 * Recorte de imágenes antes de comprimirlas. Algunas imágenes tienen una
 * proporción obligatoria (clave "aspect" de los perfiles en
 * resources/data/*-image-profiles.json): el servidor la vuelve a comprobar
 * con la regla dimensions:ratio (App\Concerns\ImageAspectRules).
 */

import { encodeWebp, targetSize } from '@/lib/imageOptimize';
import type { ImageProfile } from '@/lib/imageOptimize';

/**
 * Proporción ancho / alto del perfil, o null si se recorta libre.
 */
export const profileAspect = (profile: ImageProfile): number | null => {
    const [width, height] = profile.aspect ?? [];

    return width && height ? width / height : null;
};

/**
 * Texto de la proporción, p. ej. "1:1" o "572:1016".
 */
export const aspectLabel = (profile: ImageProfile): string | null =>
    profile.aspect?.length === 2
        ? `${profile.aspect[0]}:${profile.aspect[1]}`
        : null;

/**
 * Misma tolerancia que la regla dimensions:ratio de Laravel, para avisar de
 * lo que el servidor rechazaría.
 */
export const matchesAspect = (
    width: number,
    height: number,
    aspect: number,
): boolean => {
    const precision = 1 / (Math.max((width + height) / 2, height) + 1);

    return Math.abs(aspect - width / height) <= precision;
};

/**
 * Tamaño final del recorte, ya dentro del perfil. Con proporción fija se
 * redondea una sola vez (el ancho sale del alto): redondear ancho y alto por
 * separado, al recortar y otra vez al reducir, puede desviarse un píxel y la
 * regla del servidor no tolera ni eso en imágenes grandes. Como ya cabe en el
 * perfil, la compresión WebP no vuelve a cambiar el tamaño.
 */
export const cropOutputSize = (
    cropWidth: number,
    cropHeight: number,
    profile: ImageProfile,
): [number, number] => {
    const scale = Math.min(
        1,
        profile.maxWidth / cropWidth,
        profile.maxHeight / cropHeight,
    );
    const aspect = profileAspect(profile);

    if (!aspect) {
        return [
            Math.max(
                1,
                Math.min(profile.maxWidth, Math.round(cropWidth * scale)),
            ),
            Math.max(
                1,
                Math.min(profile.maxHeight, Math.round(cropHeight * scale)),
            ),
        ];
    }

    let height = Math.max(
        1,
        Math.min(profile.maxHeight, Math.round(cropHeight * scale)),
    );
    let width = Math.max(1, Math.round(height * aspect));

    if (width > profile.maxWidth) {
        width = profile.maxWidth;
        height = Math.max(1, Math.round(width / aspect));
    }

    return [width, height];
};

/**
 * El recorte quedó muy por debajo del tamaño final del perfil: se verá
 * borroso. Solo es un aviso.
 */
export const isSmallCrop = (
    width: number,
    height: number,
    profile: ImageProfile,
): boolean =>
    Math.max(width / profile.maxWidth, height / profile.maxHeight) < 0.5;

// De qué salió cada recorte: el archivo elegido o la URL de la imagen guardada.
const sources = new WeakMap<File, File | string>();

/**
 * Archivo que eligió el usuario: el original si `file` es un recorte. Sirve
 * para mostrar el peso real antes de comprimir.
 */
export const cropSource = (file: File): File => {
    const origin = sources.get(file);

    return origin instanceof File ? origin : file;
};

/**
 * Foto completa de la que salió `file` (archivo o URL guardada), para volver
 * a recortar desde ella y no desde el recorte anterior.
 */
export const cropOrigin = (file: File): File | string =>
    sources.get(file) ?? file;

/**
 * Archivo PNG con el recorte (sin pérdida: la compresión WebP viene después).
 */
export const croppedFile = (
    blob: Blob,
    name: string,
    source?: File | string,
): File => {
    const file = new File(
        [blob],
        `${name.replace(/\.[^./]+$/, '') || 'imagen'}.png`,
        { type: 'image/png', lastModified: Date.now() },
    );

    if (source !== undefined) {
        sources.set(file, source instanceof File ? cropOrigin(source) : source);
    }

    return file;
};

export interface CropMemory {
    // Datos de Cropper.getData(): recuadro en píxeles de la foto, giro y espejo.
    data: {
        x: number;
        y: number;
        width: number;
        height: number;
        rotate: number;
        scaleX: number;
        scaleY: number;
    };
    // Proporción elegida (NaN = libre); solo cuenta sin proporción fija.
    aspectRatio: number;
}

// Último recorte aplicado a cada foto, para que "Recortar de nuevo" parta de él.
const fileCrops = new WeakMap<File, CropMemory>();
const urlCrops = new Map<string, CropMemory>();

export const rememberCrop = (source: File | string, memory: CropMemory) => {
    if (source instanceof File) {
        fileCrops.set(source, memory);
    } else {
        urlCrops.set(source, memory);
    }
};

export const recalledCrop = (source: File | string): CropMemory | null =>
    (source instanceof File ? fileCrops.get(source) : urlCrops.get(source)) ??
    null;

/**
 * Copia de la foto completa que se guarda para volver a recortar. Mismo lado
 * máximo que config('images.crop_source').
 */
export const CROP_SOURCE_PROFILE: ImageProfile = {
    maxWidth: 2400,
    maxHeight: 2400,
    quality: 90,
};

export type SavedCrop = CropMemory['data'] & { aspectRatio: number | null };

/**
 * Recuadro guardado en el servidor, listo para rememberCrop().
 */
export const cropMemory = (saved: SavedCrop): CropMemory => {
    const { aspectRatio, ...data } = saved;

    return {
        data: {
            ...data,
            rotate: data.rotate ?? 0,
            scaleX: data.scaleX ?? 1,
            scaleY: data.scaleY ?? 1,
        },
        aspectRatio: aspectRatio ?? NaN,
    };
};

/**
 * Recuadro de `memory` en una copia `scale` veces el tamaño de la foto.
 */
export const scaleCrop = (memory: CropMemory, scale: number): SavedCrop => ({
    ...memory.data,
    x: memory.data.x * scale,
    y: memory.data.y * scale,
    width: memory.data.width * scale,
    height: memory.data.height * scale,
    aspectRatio: Number.isNaN(memory.aspectRatio) ? null : memory.aspectRatio,
});

const sourceCopies = new WeakMap<File, Promise<File | null>>();

/**
 * Lo que el formulario envía junto con una imagen recortada:
 * - foto nueva: su copia reducida (`source`) y el recuadro en píxeles de la copia;
 * - recortada de nuevo desde el original guardado: solo el recuadro;
 * - sin recortar: nada (el servidor borra el original anterior).
 */
export const cropPayload = async (
    file: File | null,
): Promise<{ source: File | null; crop: SavedCrop | null }> => {
    const origin = file ? cropOrigin(file) : null;
    const memory = origin && origin !== file ? recalledCrop(origin) : null;

    if (!origin || !memory) {
        return { source: null, crop: null };
    }

    if (typeof origin === 'string') {
        return { source: null, crop: scaleCrop(memory, 1) };
    }

    const bitmap = await createImageBitmap(origin).catch(() => null);

    if (!bitmap) {
        return { source: null, crop: null };
    }

    const scale =
        targetSize(bitmap.width, bitmap.height, CROP_SOURCE_PROFILE)[0] /
        bitmap.width;

    bitmap.close();

    const copy =
        sourceCopies.get(origin) ??
        encodeWebp(origin, CROP_SOURCE_PROFILE, origin.name);

    sourceCopies.set(origin, copy);

    const source = await copy;

    return source
        ? { source, crop: scaleCrop(memory, scale) }
        : { source: null, crop: null };
};
