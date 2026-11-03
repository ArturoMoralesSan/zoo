/**
 * Los modelos 3D de las tarjetas solo se aceptan en GLB.
 */
export type ModelExtension = 'glb';

/**
 * URL pública de un archivo guardado en el disco "public".
 */
export const storageUrl = (path: string | null | undefined): string | null => {
    if (!path) {
        return null;
    }

    if (/^(https?:\/\/|\/|blob:)/.test(path)) {
        return path;
    }

    return `/storage/${path}`;
};
