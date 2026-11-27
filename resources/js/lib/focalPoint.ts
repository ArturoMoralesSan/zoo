/**
 * Punto focal de las imágenes de especies (principal y galería): x e y de 0
 * a 1 desde la esquina superior izquierda, o null para el centro. La app lo
 * aplica como contentPosition de expo-image, que funciona igual que
 * object-position de CSS: el punto al x% de la foto queda al x% del marco.
 * Así se mantiene a la vista en cualquier proporción, sin ir al centro.
 * Ver docs/species-focal-app.md.
 */

export interface FocalPoint {
    x: number;
    y: number;
}

/**
 * Marcos donde la app recorta la imagen, con su tamaño real en un teléfono
 * de 400 px de ancho.
 */
export const APP_FRAMES = [
    { key: 'hero', label: 'Ficha', width: 400, height: 470 },
    { key: 'card', label: 'Lista', width: 400, height: 232 },
    { key: 'tile', label: 'Mi zoo', width: 152, height: 176 },
    { key: 'gallery', label: 'Galería', width: 248, height: 164 },
] as const;

const clamp = (value: number): number =>
    Math.round(Math.min(1, Math.max(0, value)) * 10000) / 10000;

/**
 * Limita a 0..1 con cuatro decimales, como la columna decimal(5,4).
 */
export const clampFocal = (x: number, y: number): FocalPoint => ({
    x: clamp(x),
    y: clamp(y),
});

/**
 * Valor de object-position (CSS) para el punto, o el centro.
 */
export const objectPosition = (
    x: number | null | undefined,
    y: number | null | undefined,
): string =>
    x === null || x === undefined || y === null || y === undefined
        ? '50% 50%'
        : `${Math.round(clamp(x) * 100)}% ${Math.round(clamp(y) * 100)}%`;
