import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { NodeIO } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { getBounds } from '@gltf-transform/functions';
import draco3d from 'draco3d';
import { MeshoptDecoder } from 'meshoptimizer';
import { beforeAll, describe, expect, it } from 'vitest';

import {
    QUICK_HEIGHTS,
    clampMeters,
    isHeightInRange,
    parseMeters,
    stepMeters,
} from '@/lib/glb/modelSize';
import {
    inspectGlb,
    resizeGlb,
    SCALE_ROOT_NAME,
    setGlbIOFactory,
} from '@/lib/glb/resizeGlb';

const FIXTURES = join(__dirname, '../Fixtures/glb/input');

const SUPPORTED = ['KHR_materials_unlit', 'KHR_texture_transform'];

const fixture = (name: string) => new Uint8Array(readFileSync(join(FIXTURES, name)));

let io: NodeIO;

beforeAll(async () => {
    await MeshoptDecoder.ready;

    io = new NodeIO().registerExtensions(ALL_EXTENSIONS).registerDependencies({
        'draco3d.decoder': await draco3d.createDecoderModule(),
        'meshopt.decoder': MeshoptDecoder,
    });

    setGlbIOFactory(async () => io);
});

/*
 * Contrato del origen: centro inferior en [0,0,0] y el alto pedido.
 */
const expectOriginContract = async (glb: Uint8Array) => {
    const document = await io.readBinary(glb);
    const scene = document.getRoot().getDefaultScene()!;
    const [root, ...others] = scene.listChildren();

    expect(others).toHaveLength(0);
    expect(root.getName()).toBe(SCALE_ROOT_NAME);
    expect(root.getRotation()).toEqual([0, 0, 0, 1]);

    const extensions = document
        .getRoot()
        .listExtensionsUsed()
        .map((extension) => extension.extensionName);

    expect(extensions).not.toContain('KHR_draco_mesh_compression');
    expect(extensions).not.toContain('EXT_meshopt_compression');
    expect(extensions).not.toContain('KHR_mesh_quantization');

    return document;
};

const expectBounds = (min: number[], max: number[], target: number) => {
    const height = max[1] - min[1];
    const horizontal = Math.max(max[0] - min[0], max[2] - min[2]);

    expect(Math.abs(height - target)).toBeLessThanOrEqual(target * 0.001);
    expect(Math.abs(min[1])).toBeLessThanOrEqual(Math.max(0.001, height * 0.001));
    expect(Math.abs((min[0] + max[0]) / 2)).toBeLessThanOrEqual(Math.max(0.001, horizontal * 0.001));
    expect(Math.abs((min[2] + max[2]) / 2)).toBeLessThanOrEqual(Math.max(0.001, horizontal * 0.001));
};

describe('resizeGlb', () => {
    it.each([
        ['plain.glb', 1.5],
        ['draco.glb', 0.75],
        ['meshopt.glb', 1],
        ['quantized.glb', 0.05],
        ['animated.glb', 2],
        ['unsupported-extension.glb', 1.5],
        ['off-center.glb', 1.7],
        ['root-transform.glb', 0.3],
    ])('cumple el contrato del origen con %s', async (name, target) => {
        const result = await resizeGlb(fixture(name), target, {
            supportedExtensions: SUPPORTED,
        });

        const document = await expectOriginContract(result.glb);
        const { min, max } = getBounds(document.getRoot().getDefaultScene()!);

        expectBounds(min, max, target);
        expect(result.finalHeight).toBeCloseTo(target, 3);
        expect(result.factor).toBeCloseTo(target / result.originalHeight, 6);
    });

    it('mide el modelo original sin escalar', async () => {
        const result = await resizeGlb(fixture('plain.glb'), 1);

        expect(result.originalHeight).toBeCloseTo(2, 5);
        expect(result.width).toBeCloseTo(0.25, 5);
        expect(result.depth).toBeCloseTo(0.5, 5);
    });

    it('mide los modelos exportados en cm y con Z hacia arriba', async () => {
        const result = await resizeGlb(fixture('root-transform.glb'), 1);

        // 200 cm en Z local → 2 m de alto tras la rotación de la raíz.
        expect(result.originalHeight).toBeCloseTo(2, 4);
    });

    it('conserva las animaciones', async () => {
        const result = await resizeGlb(fixture('animated.glb'), 1);
        const document = await io.readBinary(result.glb);

        expect(document.getRoot().listAnimations()).toHaveLength(1);
        expect(document.getRoot().listAnimations()[0].listChannels()).toHaveLength(1);
    });

    it('mide los modelos con piel en la pose de enlace', async () => {
        const result = await resizeGlb(fixture('skinned.glb'), 1.5);

        // getBounds usaría la escala ×10 del nodo de la malla (20 m).
        expect(result.originalHeight).toBeCloseTo(2, 4);
        expect(result.finalHeight).toBeCloseTo(1.5, 3);
        expect(result.warnings.join(' ')).toContain('esqueleto');

        const document = await expectOriginContract(result.glb);
        expect(document.getRoot().listSkins()).toHaveLength(1);
    });

    it('avisa de extensiones no compatibles sin bloquear', async () => {
        const result = await resizeGlb(fixture('unsupported-extension.glb'), 1, {
            supportedExtensions: SUPPORTED,
        });

        expect(result.warnings.join(' ')).toContain('KHR_materials_clearcoat');
    });

    it('no avisa de las extensiones de compresión', async () => {
        const result = await resizeGlb(fixture('draco.glb'), 1);

        expect(result.warnings).toHaveLength(0);
    });

    it('rechaza archivos con varias escenas', async () => {
        await expect(resizeGlb(fixture('multi-scene.glb'), 1)).rejects.toThrow(
            /una sola escena/,
        );
    });

    it('rechaza un alto inválido', async () => {
        await expect(resizeGlb(fixture('plain.glb'), 0)).rejects.toThrow();
    });
});

describe('inspectGlb', () => {
    it('devuelve las medidas y las extensiones', async () => {
        const info = await inspectGlb(fixture('draco.glb'));

        expect(info.height).toBeCloseTo(2, 2);
        expect(info.hasSkins).toBe(false);
        expect(info.extensionsUsed).toContain('KHR_draco_mesh_compression');
    });

    it('detecta modelos con piel', async () => {
        const info = await inspectGlb(fixture('skinned.glb'));

        expect(info.hasSkins).toBe(true);
        expect(info.warnings).toHaveLength(1);
    });
});

describe('modelSize', () => {
    const config = { minHeight: 0.01, maxHeight: 100, supportedExtensions: [] };

    it.each([
        ['1', 1],
        ['1.5', 1.5],
        ['1,5', 1.5],
        ['0.75', 0.75],
        ['0.05', 0.05],
        [' 2 ', 2],
        ['.5', 0.5],
    ])('interpreta "%s"', (value, expected) => {
        expect(parseMeters(value)).toBe(expected);
    });

    it.each(['', 'abc', '1.555', '-1', '1.2.3', '1 m'])('rechaza "%s"', (value) => {
        expect(parseMeters(value)).toBeNull();
    });

    it('ajusta el paso según el valor', () => {
        expect(stepMeters(1, 1, config)).toBe(1.1);
        expect(stepMeters(0.3, 1, config)).toBe(0.31);
        expect(stepMeters(0.5, -1, config)).toBe(0.49);
        expect(stepMeters(0.01, -1, config)).toBe(0.01);
        expect(stepMeters(100, 1, config)).toBe(100);
    });

    it('las medidas rápidas están ordenadas y dentro del rango', () => {
        expect(QUICK_HEIGHTS).toEqual([0.25, 0.5, 1, 1.5, 2]);
        expect(QUICK_HEIGHTS.every((value) => isHeightInRange(value, config))).toBe(true);
    });

    it('limita al rango configurado', () => {
        expect(clampMeters(500, config)).toBe(100);
        expect(clampMeters(0, config)).toBe(0.01);
    });
});
