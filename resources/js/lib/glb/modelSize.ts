/**
 * Utilidades del campo de tamaño del modelo 3D (siempre en metros).
 */

export interface GlbSizeConfig {
    minHeight: number;
    maxHeight: number;
    supportedExtensions: string[];
}

/*
 * Medidas rápidas con animales de ejemplo (alto total aproximado de un
 * adulto, de la base del modelo a su punto más alto).
 */
export const QUICK_HEIGHTS = [
    { value: 0.05, example: 'Rana, insecto' },
    { value: 0.1, example: 'Colibrí, ratón' },
    { value: 0.25, example: 'Conejo, tortuga' },
    { value: 0.5, example: 'Zorro, mapache' },
    { value: 0.75, example: 'Jaguar, lobo' },
    { value: 1, example: 'Venado, oso' },
    { value: 1.5, example: 'Caballo, bisonte' },
    { value: 2, example: 'Camello, avestruz' },
    { value: 3, example: 'Elefante asiático' },
    { value: 4, example: 'Elefante africano' },
    { value: 5.5, example: 'Jirafa' },
] as const;

/*
 * Avisos de tamaño sospechoso (no bloquean el guardado).
 */
export const SUSPICIOUS_ORIGINAL_HEIGHT = 20;
export const EXTREME_FACTOR_MAX = 100;
export const EXTREME_FACTOR_MIN = 0.01;

/*
 * Convierte lo que escribe el usuario ("1", "1.5", "1,5", "0.05") en número.
 * Admite punto o coma decimal y como máximo 2 decimales; null si no es válido.
 */
export const parseMeters = (value: string | number | null | undefined): number | null => {
    if (value === null || value === undefined) {
        return null;
    }

    const text = String(value).trim().replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$|^\.\d{1,2}$/.test(text)) {
        return null;
    }

    const number = Number(text);

    return Number.isFinite(number) ? number : null;
};

export const roundMeters = (value: number): number =>
    Math.round(value * 100) / 100;

export const clampMeters = (value: number, config: GlbSizeConfig): number =>
    Math.min(config.maxHeight, Math.max(config.minHeight, roundMeters(value)));

/*
 * Paso de los botones −/+: 0.1 m, o 0.01 m por debajo de 0.5 m.
 */
export const stepMeters = (
    value: number | null,
    direction: 1 | -1,
    config: GlbSizeConfig,
): number => {
    const current = value ?? 0;
    const step =
        current < 0.5 || (direction === -1 && current <= 0.5) ? 0.01 : 0.1;

    return clampMeters(current + step * direction, config);
};

export const isHeightInRange = (value: number | null, config: GlbSizeConfig): boolean =>
    value !== null && value >= config.minHeight && value <= config.maxHeight;

export const formatMeters = (value: number | null | undefined): string =>
    value === null || value === undefined || !Number.isFinite(value)
        ? '—'
        : `${value.toFixed(2)} m`;

export const formatFactor = (factor: number | null): string => {
    if (factor === null || !Number.isFinite(factor)) {
        return '—';
    }

    const digits = factor >= 100 ? 0 : factor >= 10 ? 1 : factor >= 0.1 ? 2 : 4;

    return `×${factor.toFixed(digits)}`;
};

export const heightRangeMessage = (config: GlbSizeConfig): string =>
    `Ingresa una medida entre ${config.minHeight} y ${config.maxHeight} m`;
