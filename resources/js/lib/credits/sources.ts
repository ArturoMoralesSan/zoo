/*
 * Detección del origen a partir de la URL que escribe el administrador
 * (sin claves de API). Solo sugiere: el formulario aplica la sugerencia si
 * el administrador aún no eligió origen o licencia.
 */

import type { AssetSourceValue } from './types';

export interface DetectedSource {
    source: AssetSourceValue;
    // Licencia del proveedor (null: depende del archivo, p. ej. Commons).
    licenseSlug: string | null;
}

const PROVIDERS: { hosts: string[]; detected: DetectedSource }[] = [
    {
        hosts: ['pexels.com'],
        detected: { source: 'pexels', licenseSlug: 'pexels-license' },
    },
    {
        hosts: ['unsplash.com'],
        detected: { source: 'unsplash', licenseSlug: 'unsplash-license' },
    },
    {
        hosts: ['pixabay.com'],
        detected: { source: 'pixabay', licenseSlug: 'pixabay-license' },
    },
    // Modelos 3D: la licencia depende del modelo, salvo en Poly Haven.
    {
        hosts: ['sketchfab.com'],
        detected: { source: 'sketchfab', licenseSlug: null },
    },
    {
        hosts: ['poly.pizza'],
        detected: { source: 'poly_pizza', licenseSlug: null },
    },
    {
        hosts: ['polyhaven.com'],
        detected: { source: 'poly_haven', licenseSlug: 'cc0-1.0' },
    },
    {
        hosts: ['commons.wikimedia.org', 'commons.m.wikimedia.org'],
        detected: { source: 'wikimedia_commons', licenseSlug: null },
    },
];

/*
 * Prefijos de las páginas de archivo en las Wikipedias locales
 * (igual que CommonsMetadataFetcher).
 */
const WIKIPEDIA_FILE_PATH =
    /^\/wiki\/(File|Archivo|Image|Imagen|Fichier|Datei|Ficheiro|Bestand):/i;

function parseUrl(value: string | null | undefined): URL | null {
    const text = (value ?? '').trim();

    if (!text) {
        return null;
    }

    try {
        return new URL(/^https?:\/\//i.test(text) ? text : `https://${text}`);
    } catch {
        return null;
    }
}

const hostMatches = (host: string, domain: string): boolean =>
    host === domain || host.endsWith(`.${domain}`);

export function detectSource(
    value: string | null | undefined,
): DetectedSource | null {
    const url = parseUrl(value);

    if (!url) {
        return null;
    }

    const host = url.hostname.toLowerCase();

    return (
        PROVIDERS.find((provider) =>
            provider.hosts.some((domain) => hostMatches(host, domain)),
        )?.detected ?? null
    );
}

/**
 * Página de archivo de Commons (commons.wikimedia.org/wiki/File:…).
 */
export function isCommonsFileUrl(value: string | null | undefined): boolean {
    const url = parseUrl(value);

    if (!url || !/^commons(\.m)?\.wikimedia\.org$/i.test(url.hostname)) {
        return false;
    }

    const path = decodeURIComponent(url.pathname);
    const title = path.startsWith('/wiki/')
        ? path.slice('/wiki/'.length)
        : (url.searchParams.get('title') ?? '');

    return /^File:.+/i.test(title.replace(/_/g, ' ').trim());
}

/**
 * Página de archivo de una Wikipedia local: puede ser un archivo no libre.
 */
export function isWikipediaFileUrl(value: string | null | undefined): boolean {
    const url = parseUrl(value);

    if (!url || !hostMatches(url.hostname.toLowerCase(), 'wikipedia.org')) {
        return false;
    }

    return WIKIPEDIA_FILE_PATH.test(decodeURIComponent(url.pathname));
}
