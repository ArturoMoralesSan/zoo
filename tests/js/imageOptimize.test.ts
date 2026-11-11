import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
    CARD_IMAGE_PROFILES,
    encodeWebp,
    fitsProfile,
    targetSize,
    webpFilename,
} from '@/lib/imageOptimize';
import profilesJson from '../../resources/data/card-image-profiles.json';

const wasmEncode = vi.hoisted(() => vi.fn());

vi.mock('@jsquash/webp/encode', () => ({ default: wasmEncode }));

/**
 * Lienzo mínimo para Node: toBlob responde con el tipo que "soporta".
 */
class FakeCanvas {
    static outputType = 'image/webp';

    constructor(
        public width = 0,
        public height = 0,
    ) {}

    getContext() {
        return {
            getImageData: () => ({ width: this.width, height: this.height }),
        };
    }

    toBlob(callback: (blob: Blob | null) => void, type: string) {
        callback(new Blob(['x'], { type: FakeCanvas.outputType || type }));
    }
}

describe('targetSize', () => {
    const profile = CARD_IMAGE_PROFILES.generated;

    it('reduce el render a 3× al tamaño de la textura (2×)', () => {
        expect(targetSize(1716, 3048, profile)).toEqual([1144, 2032]);
    });

    it('nunca agranda', () => {
        expect(targetSize(500, 900, profile)).toEqual([500, 900]);
    });

    it('conserva la proporción con el lado que más excede', () => {
        expect(targetSize(4000, 2000, CARD_IMAGE_PROFILES.thumbnail)).toEqual([
            800, 400,
        ]);
    });

    it('fitsProfile respeta ambos límites', () => {
        expect(fitsProfile(1144, 2032, profile)).toBe(true);
        expect(fitsProfile(1145, 2032, profile)).toBe(false);
    });
});

describe('perfiles', () => {
    it('vienen del archivo compartido con el servidor', () => {
        expect(CARD_IMAGE_PROFILES).toEqual(profilesJson);
    });
});

describe('webpFilename', () => {
    it('cambia solo la extensión', () => {
        expect(webpFilename('fondo.final.jpg')).toBe('fondo.final.webp');
        expect(webpFilename('tarjeta')).toBe('tarjeta.webp');
    });
});

describe('encodeWebp', () => {
    beforeEach(() => {
        vi.stubGlobal('HTMLCanvasElement', FakeCanvas);
        vi.stubGlobal('ImageBitmap', class {});
        vi.stubGlobal('document', {
            createElement: () => new FakeCanvas(),
        });
        FakeCanvas.outputType = 'image/webp';
        wasmEncode.mockReset();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    const canvas = () =>
        new FakeCanvas(1144, 2032) as unknown as HTMLCanvasElement;

    it('usa libwebp (WASM) con sharp YUV', async () => {
        wasmEncode.mockResolvedValue(new ArrayBuffer(10));

        const file = await encodeWebp(
            canvas(),
            CARD_IMAGE_PROFILES.generated,
            'tarjeta.png',
        );

        expect(file?.type).toBe('image/webp');
        expect(file?.name).toBe('tarjeta.webp');
        expect(wasmEncode).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ quality: 88, use_sharp_yuv: 1 }),
        );
    });

    it('si el WASM falla, usa el WebP del canvas', async () => {
        wasmEncode.mockRejectedValue(new Error('sin wasm'));
        vi.spyOn(console, 'warn').mockImplementation(() => {});

        const file = await encodeWebp(
            canvas(),
            CARD_IMAGE_PROFILES.generated,
            'tarjeta.png',
        );

        expect(file?.type).toBe('image/webp');
    });

    it('devuelve null si el navegador no produce WebP', async () => {
        wasmEncode.mockRejectedValue(new Error('sin wasm'));
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        // Safari antiguo: toBlob('image/webp') devuelve PNG.
        FakeCanvas.outputType = 'image/png';

        const file = await encodeWebp(
            canvas(),
            CARD_IMAGE_PROFILES.generated,
            'tarjeta.png',
        );

        expect(file).toBeNull();
    });
});
