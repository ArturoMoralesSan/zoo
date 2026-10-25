import { getFontEmbedCSS, toPng, toSvg } from 'html-to-image';

/**
 * Calidad de las imágenes generadas a partir de la plantilla (572 × 1016 px).
 * - Descarga y servidor: 3× (1716 × 3048 px).
 * - Textura del visor 3D: 2× (1144 × 2032 px).
 */
export const CARD_EXPORT_PIXEL_RATIO = 3;
export const CARD_TEXTURE_PIXEL_RATIO = 2;

/*
 * Las fuentes se incrustan en cada imagen; se calculan una sola vez
 * porque leerlas y convertirlas a base64 es lo más costoso.
 */
let fontEmbedCss: Promise<string> | null = null;

const renderOptions = async (node: HTMLElement) => {
    await document.fonts.ready;

    fontEmbedCss ??= getFontEmbedCSS(node).catch((error) => {
        fontEmbedCss = null;

        throw error;
    });

    /*
     * Sin cacheBust: añadiría "?t=..." también a las URLs blob: de los
     * archivos recién seleccionados y no se podrían leer. Los archivos
     * guardados tienen nombres únicos, así que la caché no queda obsoleta.
     */
    return {
        fontEmbedCSS: await fontEmbedCss,
    };
};

/**
 * PNG (data URL) de la tarjeta.
 */
export const renderCardPng = async (
    node: HTMLElement,
    pixelRatio = CARD_EXPORT_PIXEL_RATIO,
): Promise<string> =>
    toPng(node, { ...(await renderOptions(node)), pixelRatio });

/**
 * SVG (data URL) de la tarjeta. Usa <foreignObject>: se ve bien en
 * navegadores, pero no siempre en editores vectoriales.
 */
export const renderCardSvg = async (node: HTMLElement): Promise<string> =>
    toSvg(node, await renderOptions(node));

/**
 * Archivo PNG listo para enviarse en un formulario.
 */
export const dataUrlToFile = async (
    dataUrl: string,
    filename: string,
): Promise<File> => {
    const blob = await (await fetch(dataUrl)).blob();

    return new File([blob], filename, { type: blob.type });
};

/**
 * Descargar un data URL como archivo.
 */
export const downloadDataUrl = (dataUrl: string, filename: string): void => {
    const link = document.createElement('a');

    link.download = filename;
    link.href = dataUrl;
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
