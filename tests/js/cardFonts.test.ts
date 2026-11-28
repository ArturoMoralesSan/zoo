import { describe, expect, it } from 'vitest';

import { DEFAULT_TITLE_FONT, resolveCardFont } from '@/lib/cardFonts';

describe('resolveCardFont', () => {
    it('keeps a font that is in the catalogue', () => {
        expect(resolveCardFont('cinzel', 700)).toEqual({
            family: 'cinzel',
            weight: 700,
            missing: false,
        });
    });

    it('snaps a weight the font does not have to the nearest one', () => {
        // Caveat va de 400 a 700.
        expect(resolveCardFont('caveat', 900).weight).toBe(700);
    });

    it('falls back to the default font when the saved one was removed', () => {
        expect(resolveCardFont('roboto', 900)).toEqual({
            family: DEFAULT_TITLE_FONT.family,
            weight: 900,
            missing: true,
        });
    });

    it('does not treat object prototype keys as fonts', () => {
        expect(resolveCardFont('constructor', 400).missing).toBe(true);
    });

    it('uses the default font without warning when none was saved', () => {
        expect(resolveCardFont(null, null)).toEqual({
            family: DEFAULT_TITLE_FONT.family,
            weight: DEFAULT_TITLE_FONT.weight,
            missing: false,
        });
    });
});
