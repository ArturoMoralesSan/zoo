import catalogue from '../../data/card-fonts.json';

/**
 * Fuentes disponibles para el nombre de la especie en la tarjeta.
 * El catálogo (familias y pesos de Fontsource) se comparte con la validación de PHP.
 */
export type CardFontId = keyof typeof catalogue;

export interface CardFont {
    family: string;
    weights: number[];
}

export const CARD_FONTS: Record<CardFontId, CardFont> = catalogue;

export const cardFontOptions = (
    Object.entries(CARD_FONTS) as [CardFontId, CardFont][]
).map(([id, font]) => ({ id, ...font }));

/**
 * Tamaños del nombre (clases nativas de Tailwind, escritas completas
 * para que Tailwind las genere). El área del nombre mide 500 px de ancho.
 * previewPx: tamaño proporcional de la muestra "Aa" en el selector.
 */
export const TITLE_SIZES = {
    '4xl': {
        className: 'text-4xl',
        previewPx: 15,
        label: '4XL · 36 px',
        hint: 'Para nombres largos.',
    },
    '5xl': {
        className: 'text-5xl',
        previewPx: 20,
        label: '5XL · 48 px',
        hint: 'Para nombres de más de 12 caracteres o fuentes anchas y gruesas.',
    },
    '6xl': {
        className: 'text-6xl',
        previewPx: 25,
        label: '6XL · 60 px',
        hint: 'Tamaño predeterminado de la tarjeta.',
    },
    '7xl': {
        className: 'text-7xl',
        previewPx: 30,
        label: '7XL · 72 px',
        hint: 'Solo para nombres cortos (8 caracteres o menos).',
    },
} as const;

export type TitleSize = keyof typeof TITLE_SIZES;

export const DEFAULT_TITLE_FONT = {
    family: 'barlow' as CardFontId,
    weight: 800,
    size: '6xl' as TitleSize,
};

export const WEIGHT_LABELS: Record<number, string> = {
    100: 'Thin',
    200: 'ExtraLight',
    300: 'Light',
    400: 'Regular',
    500: 'Medium',
    600: 'SemiBold',
    700: 'Bold',
    800: 'ExtraBold',
    900: 'Black',
};

/**
 * Peso disponible más cercano (al cambiar a una familia sin el peso actual).
 */
export const nearestWeight = (id: CardFontId, weight: number): number =>
    CARD_FONTS[id].weights.reduce((best, candidate) =>
        Math.abs(candidate - weight) < Math.abs(best - weight) ||
        (Math.abs(candidate - weight) === Math.abs(best - weight) &&
            candidate > best)
            ? candidate
            : best,
    );

export type CardFontStyle = 'normal' | 'italic';

/*
 * Archivos woff2 de Fontsource (solo latin). Solo se importan sus URLs;
 * cada archivo se descarga cuando se usa la fuente.
 *
 * Las fuentes se registran con la API FontFace en lugar de importar las
 * hojas de estilo de Fontsource: así no dependen de los <style> que Vite
 * inyecta (y puede quitar al recargar módulos), y la imagen de la tarjeta
 * obtiene el archivo de cada fuente directamente de aquí.
 */
const fontFiles = import.meta.glob<string>(
    [
        '/node_modules/@fontsource/*/files/*-latin-[1-9]00-normal.woff2',
        '/node_modules/@fontsource/noto-sans/files/noto-sans-latin-400-italic.woff2',
    ],
    { query: '?url', import: 'default', eager: true },
);

const fontFileUrl = (
    id: CardFontId,
    weight: number,
    style: CardFontStyle,
): string | null =>
    fontFiles[
        `/node_modules/@fontsource/${id}/files/${id}-latin-${weight}-${style}.woff2`
    ] ?? null;

/**
 * URL del archivo de una fuente a partir de su nombre de familia
 * (para incrustarla en la imagen de la tarjeta).
 */
export const cardFontFileUrl = (
    family: string,
    weight: number,
    style: CardFontStyle,
): string | null => {
    const font = cardFontOptions.find((option) => option.family === family);

    return font ? fontFileUrl(font.id, weight, style) : null;
};

const findLoadedFace = (
    family: string,
    weight: number,
    style: CardFontStyle,
): FontFace | undefined =>
    [...document.fonts].find(
        (face) =>
            face.family.replace(/["']/g, '') === family &&
            face.weight === String(weight) &&
            face.style === style,
    );

/**
 * Cargar una fuente y esperar a que esté lista para dibujarse.
 */
export const ensureCardFont = async (
    id: CardFontId,
    weight: number,
    style: CardFontStyle = 'normal',
): Promise<void> => {
    const font = CARD_FONTS[id];
    const url = font ? fontFileUrl(id, weight, style) : null;

    if (!font || !url) {
        return;
    }

    let face = findLoadedFace(font.family, weight, style);

    if (!face) {
        face = new FontFace(font.family, `url("${url}") format("woff2")`, {
            weight: String(weight),
            style,
        });

        document.fonts.add(face);
    }

    await face.load();
};

/**
 * Fuentes fijas de la tarjeta: nombre científico (itálica) y estadísticas.
 */
export const ensureCardBaseFonts = (): Promise<void[]> =>
    Promise.all([
        ensureCardFont('noto-sans', 400),
        ensureCardFont('noto-sans', 500),
        ensureCardFont('noto-sans', 400, 'italic'),
    ]);
