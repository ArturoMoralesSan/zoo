/**
 * Recorte de imágenes antes de comprimirlas. Algunas imágenes tienen una
 * proporción obligatoria (clave "aspect" de los perfiles en
 * resources/data/*-image-profiles.json): el servidor la vuelve a comprobar
 * con la regla dimensions:ratio (App\Concerns\ImageAspectRules).
 */

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
