export type ModelExtension = 'glb' | 'gltf';

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

/**
 * Extensión del modelo 3D a partir de un nombre de archivo, ruta o URL.
 */
export const modelExtension = (
    value: string | null | undefined,
): ModelExtension | null => {
    const match = value
        ?.split(/[?#]/)[0]
        .toLowerCase()
        .match(/\.(glb|gltf)$/);

    return (match?.[1] as ModelExtension | undefined) ?? null;
};
