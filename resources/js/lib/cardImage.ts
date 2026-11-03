import { toCanvas, toSvg } from 'html-to-image';

import { cardFontFileUrl } from '@/lib/cardFonts';
import type { CardFontStyle } from '@/lib/cardFonts';

/**
 * Calidad de las imágenes generadas a partir de la plantilla (572 × 1016 px).
 * - Descarga y servidor: 3× (1716 × 3048 px).
 * - Textura del visor 3D: 2× (1144 × 2032 px).
 */
export const CARD_EXPORT_PIXEL_RATIO = 3;
export const CARD_TEXTURE_PIXEL_RATIO = 2;

/*
 * Fuentes incrustadas en la imagen.
 *
 * html-to-image incrusta todas las variantes cargadas de cada familia
 * (todos los pesos, en woff2 y woff). Con los selectores de fuente se cargan
 * muchas, y el SVG resultante es tan grande que el navegador lo dibuja antes
 * de terminar de leer las fuentes: el texto sale con una fuente genérica.
 * Por eso se incrusta solo cada variante (familia, peso y estilo) que usa
 * el texto de la tarjeta, una vez y en woff2.
 */
interface UsedFontFace {
    family: string;
    weight: string;
    style: CardFontStyle;
}

const firstFamily = (fontFamily: string): string =>
    fontFamily.split(',')[0].trim().replace(/["']/g, '');

const hasOwnText = (element: Element): boolean =>
    [...element.childNodes].some(
        (child) =>
            child.nodeType === Node.TEXT_NODE &&
            Boolean(child.textContent?.trim()),
    );

const usedFontFaces = (node: HTMLElement): UsedFontFace[] => {
    const faces = new Map<string, UsedFontFace>();

    [node, ...node.querySelectorAll('*')]
        .filter(hasOwnText)
        .forEach((element) => {
            const style = getComputedStyle(element);
            const face: UsedFontFace = {
                family: firstFamily(style.fontFamily),
                weight: style.fontWeight,
                style: style.fontStyle === 'italic' ? 'italic' : 'normal',
            };

            faces.set(`${face.family}|${face.weight}|${face.style}`, face);
        });

    return [...faces.values()];
};

const blobToDataUrl = (blob: Blob): Promise<string> =>
    new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = () => resolve(reader.result as string);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
    });

const fontFaceCss = new Map<string, Promise<string>>();

const embedFontFace = (face: UsedFontFace): Promise<string> => {
    const key = `${face.family}|${face.weight}|${face.style}`;
    const cached = fontFaceCss.get(key);

    if (cached) {
        return cached;
    }

    const url = cardFontFileUrl(face.family, Number(face.weight), face.style);

    // Fuente que no es de la tarjeta (p. ej. del sistema): no se incrusta.
    if (!url) {
        return Promise.resolve('');
    }

    const css = fetch(new URL(url, document.baseURI))
        .then((response) => {
            if (!response.ok) {
                throw new Error(`No se pudo cargar la fuente: ${url}`);
            }

            return response.blob();
        })
        .then(blobToDataUrl)
        .then(
            (dataUrl) =>
                `@font-face { font-family: "${face.family}"; font-style: ${face.style}; font-weight: ${face.weight}; src: url("${dataUrl}"); }`,
        )
        .catch((error) => {
            fontFaceCss.delete(key);

            throw error;
        });

    fontFaceCss.set(key, css);

    return css;
};

const renderOptions = async (node: HTMLElement) => {
    await document.fonts.ready;

    const fontEmbedCss = (
        await Promise.all(usedFontFaces(node).map(embedFontFace))
    ).join('\n');

    /*
     * Sin cacheBust: añadiría "?t=..." también a las URLs blob: de los
     * archivos recién seleccionados y no se podrían leer. Los archivos
     * guardados tienen nombres únicos, así que la caché no queda obsoleta.
     */
    return {
        fontEmbedCSS: fontEmbedCss,
    };
};

/**
 * PNG de la tarjeta. Se codifica con canvas.toBlob (asíncrono) en lugar de
 * un data URL, que bloquea la página mientras se genera.
 */
export const renderCardPng = async (
    node: HTMLElement,
    pixelRatio = CARD_EXPORT_PIXEL_RATIO,
): Promise<Blob> => {
    const canvas = await toCanvas(node, {
        ...(await renderOptions(node)),
        pixelRatio,
    });

    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) =>
            blob
                ? resolve(blob)
                : reject(new Error('No se pudo crear el PNG.')),
        );
    });
};

/**
 * SVG (data URL) de la tarjeta. Usa <foreignObject>: se ve bien en
 * navegadores, pero no siempre en editores vectoriales.
 */
export const renderCardSvg = async (node: HTMLElement): Promise<string> =>
    toSvg(node, await renderOptions(node));

/**
 * Descargar un archivo a partir de una URL (data: o blob:).
 */
export const downloadUrl = (url: string, filename: string): void => {
    const link = document.createElement('a');

    link.download = filename;
    link.href = url;
    link.click();
};

/**
 * Nombre de archivo a partir del título de la tarjeta.
 */
export const cardFilename = (title: string, extension: string): string => {
    const slug = title
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `${slug || 'tarjeta'}.${extension}`;
};
