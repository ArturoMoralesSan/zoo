/**
 * Colores de la plantilla de la tarjeta.
 */
export const DEFAULT_FADE_COLOR = '#4f6b5a';

/**
 * Colores sugeridos para el degradado.
 */
export const DEFAULT_FADE_PRESETS: { name: string; color: string }[] = [
    { name: 'Verde (predeterminado)', color: DEFAULT_FADE_COLOR },
    { name: 'Bosque', color: '#2f4a3a' },
    { name: 'Océano', color: '#1f4e5f' },
    { name: 'Pizarra', color: '#3d4451' },
    { name: 'Noche', color: '#1c1f26' },
    { name: 'Tierra', color: '#6b4f3a' },
    { name: 'Terracota', color: '#a4553a' },
    { name: 'Arena', color: '#d8c7a1' },
];

export type AccentColor = 'white' | 'black';

export const ACCENT_HEX: Record<AccentColor, string> = {
    white: '#ffffff',
    black: '#000000',
};

export const isHexColor = (value: string | null | undefined): value is string =>
    /^#[0-9a-f]{6}$/i.test(value ?? '');

/*
 * Luminancia relativa (WCAG 2.x).
 */
const luminance = (hex: string): number => {
    const [r, g, b] = [1, 3, 5].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;

        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/**
 * Blanco o negro, el que tenga más contraste sobre el color del degradado.
 */
export const autoAccent = (fadeColor: string): AccentColor => {
    const fade = luminance(
        isHexColor(fadeColor) ? fadeColor : DEFAULT_FADE_COLOR,
    );

    const whiteContrast = 1.05 / (fade + 0.05);
    const blackContrast = (fade + 0.05) / 0.05;

    return whiteContrast >= blackContrast ? 'white' : 'black';
};

/**
 * Color de acento final: el elegido o, si es automático, el calculado.
 */
export const resolveAccent = (
    fadeColor: string,
    override: AccentColor | null,
): string => ACCENT_HEX[override ?? autoAccent(fadeColor)];
