import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { describe, expect, it } from 'vitest';

import { buildAttribution } from '@/lib/credits/attribution';
import type { AttributionInput } from '@/lib/credits/attribution';
import {
    detectSource,
    isCommonsFileUrl,
    isWikipediaFileUrl,
} from '@/lib/credits/sources';

/*
 * Mismos casos que tests/Unit/Licensing/AttributionBuilderTest.php.
 */
const cases = JSON.parse(
    readFileSync(
        join(__dirname, '../Fixtures/credits/attribution-cases.json'),
        'utf8',
    ),
) as { name: string; input: AttributionInput; expected: string }[];

describe('buildAttribution', () => {
    it.each(cases.map((item) => [item.name, item] as const))(
        '%s',
        (_name, item) => {
            expect(buildAttribution(item.input)).toBe(item.expected);
        },
    );
});

describe('detectSource', () => {
    it.each([
        ['https://www.pexels.com/photo/jaguar-123/', 'pexels', 'pexels-license'],
        ['unsplash.com/photos/abc', 'unsplash', 'unsplash-license'],
        ['https://pixabay.com/photos/jaguar-1/', 'pixabay', 'pixabay-license'],
        ['https://commons.wikimedia.org/wiki/File:Jaguar.jpg', 'wikimedia_commons', null],
        ['https://sketchfab.com/3d-models/jaguar-123', 'sketchfab', null],
        ['https://poly.pizza/m/abc', 'poly_pizza', null],
        ['https://polyhaven.com/a/jaguar', 'poly_haven', 'cc0-1.0'],
    ])('%s', (url, source, licenseSlug) => {
        expect(detectSource(url)).toEqual({ source, licenseSlug });
    });

    it('ignora otros dominios y textos que no son URL', () => {
        expect(detectSource('https://notpexels.com/x')).toBeNull();
        expect(detectSource('https://example.com/jaguar.jpg')).toBeNull();
        expect(detectSource('')).toBeNull();
        expect(detectSource('no es una url')).toBeNull();
    });
});

describe('Wikimedia', () => {
    it('reconoce páginas de archivo de Commons', () => {
        expect(isCommonsFileUrl('https://commons.wikimedia.org/wiki/File:Jaguar_(Panthera).jpg')).toBe(true);
        expect(isCommonsFileUrl('https://commons.wikimedia.org/w/index.php?title=File:Jaguar.jpg')).toBe(true);
        expect(isCommonsFileUrl('https://commons.wikimedia.org/wiki/Category:Jaguars')).toBe(false);
        expect(isCommonsFileUrl('https://en.wikipedia.org/wiki/File:Jaguar.jpg')).toBe(false);
    });

    it('detecta páginas de archivo locales de Wikipedia', () => {
        expect(isWikipediaFileUrl('https://en.wikipedia.org/wiki/File:Jaguar.jpg')).toBe(true);
        expect(isWikipediaFileUrl('https://es.wikipedia.org/wiki/Archivo:Jaguar.jpg')).toBe(true);
        expect(isWikipediaFileUrl('https://es.wikipedia.org/wiki/Jaguar')).toBe(false);
        expect(isWikipediaFileUrl('https://commons.wikimedia.org/wiki/File:Jaguar.jpg')).toBe(false);
    });
});
