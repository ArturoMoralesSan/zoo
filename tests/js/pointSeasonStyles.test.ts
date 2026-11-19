import * as hugeicons from '@hugeicons/core-free-icons';
import { describe, expect, it } from 'vitest';

import {
    defaultSeasonColorKey,
    defaultSeasonIconKey,
    findSeasonColor,
    findSeasonIcon,
    hugeIconsByName,
    resolveSeasonColor,
    searchSeasonIcons,
    seasonColors,
    seasonHugeIcon,
    seasonIconGroups,
    seasonIcons,
} from '@/lib/pointSeasonStyles';

// Contraste WCAG 2.1 entre dos colores #RRGGBB.
const luminance = (hex: string) => {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = (a: string, b: string) => {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (high + 0.05) / (low + 0.05);
};

describe('catálogo de íconos', () => {
    it('cada ícono existe en core-free-icons y está en el mapa del panel', () => {
        for (const icon of seasonIcons) {
            expect(hugeicons, icon.icon).toHaveProperty(icon.icon);
            expect(hugeIconsByName[icon.icon], icon.key).toBe(
                (hugeicons as Record<string, unknown>)[icon.icon],
            );
        }
    });

    it('tiene claves únicas y grupos conocidos', () => {
        const keys = seasonIcons.map((icon) => icon.key);
        const groups = new Set(seasonIconGroups.map((group) => group.key));

        expect(new Set(keys).size).toBe(keys.length);
        seasonIcons.forEach((icon) => expect(groups).toContain(icon.group));
    });

    it('el ícono predeterminado está en el catálogo', () => {
        expect(findSeasonIcon(defaultSeasonIconKey)).not.toBeNull();
    });

    it('usa el predeterminado para claves nulas o desconocidas', () => {
        expect(seasonHugeIcon(null)).toBe(hugeicons.SparklesIcon);
        expect(seasonHugeIcon('no-existe')).toBe(hugeicons.SparklesIcon);
        expect(seasonHugeIcon('pumpkin')).toBe(hugeicons.PumpkinIcon);
    });
});

describe('searchSeasonIcons', () => {
    it('busca sin acentos ni mayúsculas, por nombre y palabras clave', () => {
        const keys = (query: string) =>
            searchSeasonIcons(query).map((icon) => icon.key);

        expect(keys('Día de Muertos')).toEqual(
            expect.arrayContaining(['skull', 'flower']),
        );
        expect(keys('HALLOWEEN')).toEqual(
            expect.arrayContaining(['pumpkin', 'ghost']),
        );
        expect(keys('sandia')).toEqual(['watermelon']);
    });

    it('filtra por grupo', () => {
        const animals = searchSeasonIcons('', 'animales');

        expect(animals.length).toBeGreaterThan(5);
        animals.forEach((icon) => expect(icon.group).toBe('animales'));
    });
});

describe('catálogo de colores', () => {
    it('tiene claves únicas y el predeterminado existe', () => {
        const keys = seasonColors.map((color) => color.key);

        expect(new Set(keys).size).toBe(keys.length);
        expect(findSeasonColor(defaultSeasonColorKey)).not.toBeNull();
        expect(resolveSeasonColor(null).key).toBe(defaultSeasonColorKey);
    });

    it.each(seasonColors)('$key se lee bien (contraste AA)', (color) => {
        expect(contrast(color.text, color.soft)).toBeGreaterThanOrEqual(4.5);
        expect(contrast(color.on_accent, color.accent)).toBeGreaterThanOrEqual(
            4.5,
        );
    });
});
