import { describe, expect, it } from 'vitest';

import { missingAssetsText, missingImagesText } from '@/lib/credits/missing';

// Mismo texto que Card::missingCreditsText() y Species::missingCreditsText().
describe('missingAssetsText', () => {
    it('returns null when nothing is missing', () => {
        expect(missingAssetsText([])).toBeNull();
    });

    it('joins the assets with their article', () => {
        expect(missingAssetsText(['artwork'])).toBe('la ilustración');
        expect(missingAssetsText(['thumbnail', 'model'])).toBe(
            'la miniatura y el modelo 3D',
        );
        expect(missingAssetsText(['artwork', 'thumbnail', 'model'])).toBe(
            'la ilustración, la miniatura y el modelo 3D',
        );
    });
});

describe('missingImagesText', () => {
    it('counts the images', () => {
        expect(missingImagesText(0)).toBeNull();
        expect(missingImagesText(1)).toBe('1 imagen');
        expect(missingImagesText(3)).toBe('3 imágenes');
    });
});
