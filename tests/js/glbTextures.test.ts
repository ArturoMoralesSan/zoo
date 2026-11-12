import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { NodeIO } from '@gltf-transform/core';
import type { Document, Texture } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';

import {
    TEXTURE_WARNING,
    fitTexture,
    optimizeTextures,
    setTextureEncoder,
} from '@/lib/glb/glbTextures';
import type { TextureEncoder } from '@/lib/glb/glbTextures';
import { resizeGlb, setGlbIOFactory } from '@/lib/glb/resizeGlb';

const PROFILE = { maxSize: 1024, quality: 85 };

const FIXTURES = join(__dirname, '../Fixtures/glb/input');

let io: NodeIO;

beforeAll(() => {
    io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
    setGlbIOFactory(async () => io);
});

afterEach(() => {
    setTextureEncoder(null);
});

/**
 * Codificador falso (Node no tiene canvas): devuelve 10 bytes.
 */
const fakeEncoder = () => {
    const encoder = vi.fn<TextureEncoder>(async () => new Uint8Array(10));

    setTextureEncoder(encoder);

    return encoder;
};

const image = (bytes: number) => new Uint8Array(bytes).fill(7);

/**
 * Modelo de prueba (plain.glb) con un material y sus texturas.
 */
const withTextures = async (
    alphaMode: 'OPAQUE' | 'BLEND' = 'OPAQUE',
): Promise<{ document: Document; textures: Record<string, Texture> }> => {
    const document = await io.readBinary(
        new Uint8Array(readFileSync(join(FIXTURES, 'plain.glb'))),
    );

    const textures = {
        baseColor: document
            .createTexture('base')
            .setImage(image(5000))
            .setMimeType('image/jpeg'),
        normal: document
            .createTexture('normal')
            .setImage(image(4000))
            .setMimeType('image/png'),
        metallicRoughness: document
            .createTexture('mr')
            .setImage(image(3000))
            .setMimeType('image/jpeg'),
    };

    const material = document
        .createMaterial('piel')
        .setAlphaMode(alphaMode)
        .setBaseColorTexture(textures.baseColor)
        .setNormalTexture(textures.normal)
        .setMetallicRoughnessTexture(textures.metallicRoughness);

    for (const mesh of document.getRoot().listMeshes()) {
        for (const primitive of mesh.listPrimitives()) {
            primitive.setMaterial(material);
        }
    }

    return { document, textures };
};

describe('fitTexture', () => {
    it('reduce al lado mayor sin deformar', () => {
        expect(fitTexture(2048, 2048, 1024)).toEqual([1024, 1024]);
        expect(fitTexture(4096, 2048, 1024)).toEqual([1024, 512]);
    });

    it('nunca agranda', () => {
        expect(fitTexture(512, 512, 1024)).toEqual([512, 512]);
    });
});

describe('optimizeTextures', () => {
    it('pide el perfil y JPEG para texturas opacas y de datos', async () => {
        const encoder = fakeEncoder();
        const { document, textures } = await withTextures();

        const { bytes, warnings } = await optimizeTextures(document, PROFILE);

        expect(encoder).toHaveBeenCalledTimes(3);
        expect(encoder.mock.calls.map(([, , options]) => options)).toEqual([
            { ...PROFILE, format: 'image/jpeg' },
            { ...PROFILE, format: 'image/jpeg' },
            { ...PROFILE, format: 'image/jpeg' },
        ]);
        // El mapa normal en PNG también pasa a JPEG.
        expect(textures.normal.getMimeType()).toBe('image/jpeg');
        expect(bytes).toEqual({ before: 12000, after: 30 });
        expect(warnings).toEqual([]);
    });

    it('conserva PNG en el color base de un material transparente', async () => {
        const encoder = fakeEncoder();
        const { document, textures } = await withTextures('BLEND');

        await optimizeTextures(document, PROFILE);

        expect(
            encoder.mock.calls.map(([, , options]) => options.format),
        ).toEqual(['image/png', 'image/jpeg', 'image/jpeg']);
        expect(textures.baseColor.getMimeType()).toBe('image/png');
        expect(textures.metallicRoughness.getMimeType()).toBe('image/jpeg');
    });

    it('deja la textura como estaba si el resultado no pesa menos', async () => {
        setTextureEncoder(async () => new Uint8Array(999999));
        const { document, textures } = await withTextures();
        const original = textures.baseColor.getImage();

        const { bytes, warnings } = await optimizeTextures(document, PROFILE);

        expect(textures.baseColor.getImage()).toBe(original);
        expect(textures.normal.getMimeType()).toBe('image/png');
        expect(bytes.after).toBe(bytes.before);
        expect(warnings).toEqual([]);
    });

    it('si el codificador falla, guarda la textura y avisa', async () => {
        setTextureEncoder(async () => {
            throw new Error('sin canvas');
        });
        const { document, textures } = await withTextures();

        const { warnings } = await optimizeTextures(document, PROFILE);

        expect(textures.baseColor.getImage()?.byteLength).toBe(5000);
        expect(warnings).toEqual([TEXTURE_WARNING]);
    });
});

describe('resizeGlb con texturas', () => {
    it('optimiza las texturas y conserva el alto pedido', async () => {
        fakeEncoder();
        const { document } = await withTextures();
        const input = await io.writeBinary(document);

        const result = await resizeGlb(input, 1.5, { textures: PROFILE });

        expect(result.finalHeight).toBeCloseTo(1.5, 5);
        expect(result.textureBytes).toEqual({ before: 12000, after: 30 });
        expect(result.glb.byteLength).toBeLessThan(input.byteLength);

        const output = await io.readBinary(result.glb);

        expect(
            output
                .getRoot()
                .listTextures()
                .map((texture) => texture.getImage()?.byteLength),
        ).toEqual([10, 10, 10]);
    });

    it('sin `textures` no toca las imágenes', async () => {
        const encoder = fakeEncoder();
        const { document } = await withTextures();

        const result = await resizeGlb(await io.writeBinary(document), 1);

        expect(encoder).not.toHaveBeenCalled();
        expect(result.textureBytes).toBeNull();
    });
});
