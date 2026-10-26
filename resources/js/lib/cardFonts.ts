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

/*
 * Hojas de estilo de Fontsource (solo latin), cargadas bajo demanda.
 */
const fontStyles = import.meta.glob(
    '/node_modules/@fontsource/*/latin-[1-9]00.css',
);

const loadedStyles = new Set<string>();

/**
 * Cargar una fuente y esperar a que esté lista para dibujarse.
 */
export const ensureCardFont = async (
    id: CardFontId,
    weight: number,
): Promise<void> => {
    const font = CARD_FONTS[id];

    if (!font) {
        return;
    }

    const key = `/node_modules/@fontsource/${id}/latin-${weight}.css`;

    if (!loadedStyles.has(key)) {
        await fontStyles[key]?.();

        loadedStyles.add(key);
    }

    await document.fonts.load(`${weight} 60px "${font.family}"`);
};
