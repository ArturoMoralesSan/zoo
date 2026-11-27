import { describe, expect, it } from 'vitest';

import {
    aspectLabel,
    croppedFile,
    cropOrigin,
    cropOutputSize,
    cropSource,
    isSmallCrop,
    matchesAspect,
    profileAspect,
    recalledCrop,
    rememberCrop,
} from '@/lib/imageCrop';
import {
    CARD_IMAGE_PROFILES,
    SPECIES_IMAGE_PROFILES,
    targetSize,
} from '@/lib/imageOptimize';

describe('profileAspect', () => {
    it('reads the locked ratios from the shared profiles', () => {
        expect(profileAspect(CARD_IMAGE_PROFILES.artwork)).toBeCloseTo(
            572 / 1016,
        );
        expect(profileAspect(CARD_IMAGE_PROFILES.thumbnail)).toBe(1);
        expect(profileAspect(SPECIES_IMAGE_PROFILES.thumbnail)).toBe(1);
    });

    it('leaves the other images free', () => {
        expect(profileAspect(CARD_IMAGE_PROFILES.generated)).toBeNull();
        expect(profileAspect(SPECIES_IMAGE_PROFILES.main)).toBeNull();
        expect(profileAspect(SPECIES_IMAGE_PROFILES.gallery)).toBeNull();
        expect(aspectLabel(SPECIES_IMAGE_PROFILES.main)).toBeNull();
    });

    it('labels the ratio', () => {
        expect(aspectLabel(CARD_IMAGE_PROFILES.artwork)).toBe('572:1016');
        expect(aspectLabel(CARD_IMAGE_PROFILES.thumbnail)).toBe('1:1');
    });
});

describe('matchesAspect', () => {
    const artwork = 572 / 1016;

    it('accepts what the browser produces after resizing a crop', () => {
        // Recorte grande reducido al perfil: el alto manda (2048).
        const [width, height] = targetSize(
            2860,
            5080,
            CARD_IMAGE_PROFILES.artwork,
        );

        expect([width, height]).toEqual([1153, 2048]);
        expect(matchesAspect(width, height, artwork)).toBe(true);
        expect(matchesAspect(572, 1016, artwork)).toBe(true);
        expect(matchesAspect(800, 800, 1)).toBe(true);
    });

    it('rejects other shapes, like the server', () => {
        expect(matchesAspect(1000, 1000, artwork)).toBe(false);
        expect(matchesAspect(80, 60, 1)).toBe(false);
        // Laravel no tolera ni un píxel a este tamaño.
        expect(matchesAspect(801, 800, 1)).toBe(false);
        expect(matchesAspect(3, 2, 1.5)).toBe(true);
    });
});

describe('cropOutputSize', () => {
    it('always produces a size the server accepts', () => {
        const artwork = CARD_IMAGE_PROFILES.artwork;
        const ratio = profileAspect(artwork)!;

        for (let height = 300; height <= 6000; height += 37) {
            const [width, outHeight] = cropOutputSize(
                height * ratio,
                height,
                artwork,
            );

            expect(width).toBeLessThanOrEqual(artwork.maxWidth);
            expect(outHeight).toBeLessThanOrEqual(artwork.maxHeight);
            expect(matchesAspect(width, outHeight, ratio)).toBe(true);
            // Ya cabe: la compresión no vuelve a reducir.
            expect(targetSize(width, outHeight, artwork)).toEqual([
                width,
                outHeight,
            ]);
        }
    });

    it('keeps squares square and never enlarges', () => {
        const thumbnail = SPECIES_IMAGE_PROFILES.thumbnail;

        expect(cropOutputSize(1200.6, 1200.6, thumbnail)).toEqual([512, 512]);
        expect(cropOutputSize(300.4, 300.4, thumbnail)).toEqual([300, 300]);
    });

    it('fits free crops inside the profile', () => {
        expect(cropOutputSize(3200, 1000, SPECIES_IMAGE_PROFILES.main)).toEqual(
            [1600, 500],
        );
    });
});

describe('isSmallCrop', () => {
    it('warns only well below the final size', () => {
        const profile = SPECIES_IMAGE_PROFILES.thumbnail;

        expect(isSmallCrop(200, 200, profile)).toBe(true);
        expect(isSmallCrop(300, 300, profile)).toBe(false);
    });
});

describe('croppedFile', () => {
    it('keeps the name as a PNG', () => {
        const file = croppedFile(new Blob(['x']), 'jaguar.final.jpg');

        expect(file.name).toBe('jaguar.final.png');
        expect(file.type).toBe('image/png');
        expect(cropSource(file)).toBe(file);
    });

    it('remembers the original file, even after cropping twice', () => {
        const original = new File(['original'], 'jaguar.jpg');
        const first = croppedFile(new Blob(['a']), original.name, original);
        const second = croppedFile(new Blob(['b']), first.name, first);

        expect(cropSource(first)).toBe(original);
        expect(cropSource(second)).toBe(original);
    });
});

describe('cropOrigin', () => {
    it('re-crops a saved image from its URL, not from the previous crop', () => {
        const url = '/storage/cards/artwork.webp';
        const first = croppedFile(new Blob(['a']), 'artwork.webp', url);
        const second = croppedFile(new Blob(['b']), 'artwork.webp', first);

        expect(cropOrigin(second)).toBe(url);
        // Sin archivo original, el peso de referencia es el del recorte.
        expect(cropSource(second)).toBe(second);
    });

    it('returns the file itself when it was never cropped', () => {
        const file = new File(['x'], 'a.png');

        expect(cropOrigin(file)).toBe(file);
    });
});

describe('rememberCrop', () => {
    const memory = {
        data: {
            x: 10,
            y: 20,
            width: 300,
            height: 200,
            rotate: 90,
            scaleX: 1,
            scaleY: 1,
        },
        aspectRatio: 1.5,
    };

    it('recalls the last crop of a file or a URL', () => {
        const file = new File(['x'], 'a.png');

        expect(recalledCrop(file)).toBeNull();
        rememberCrop(file, memory);
        rememberCrop('/storage/a.webp', memory);

        expect(recalledCrop(file)).toEqual(memory);
        expect(recalledCrop('/storage/a.webp')).toEqual(memory);
        expect(recalledCrop(new File(['x'], 'a.png'))).toBeNull();
    });
});
