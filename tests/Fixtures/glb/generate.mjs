/**
 * Genera los GLB de prueba (PHPUnit y Vitest).
 *
 * Uso (solo en desarrollo; los archivos generados se guardan en el repositorio):
 *   node tests/Fixtures/glb/generate.mjs
 *
 * - input/:  archivos "como los subiría un administrador" (para resizeGlb).
 * - *.glb:   archivos ya escalados (para GlbInspector), algunos creados a mano
 *            con errores y otros con el propio resizeGlb.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

import { Document, NodeIO } from '@gltf-transform/core';
import {
    ALL_EXTENSIONS,
    KHRMaterialsClearcoat,
} from '@gltf-transform/extensions';
import { draco, meshopt, quantize } from '@gltf-transform/functions';
import draco3d from 'draco3d';
import { MeshoptDecoder, MeshoptEncoder } from 'meshoptimizer';

import {
    resizeGlb,
    setGlbIOFactory,
} from '../../../resources/js/lib/glb/resizeGlb.ts';

const directory = dirname(fileURLToPath(import.meta.url));
const inputDirectory = join(directory, 'input');

mkdirSync(inputDirectory, { recursive: true });

await MeshoptEncoder.ready;
await MeshoptDecoder.ready;

const io = new NodeIO().registerExtensions(ALL_EXTENSIONS).registerDependencies({
    'draco3d.decoder': await draco3d.createDecoderModule(),
    'draco3d.encoder': await draco3d.createEncoderModule(),
    'meshopt.decoder': MeshoptDecoder,
    'meshopt.encoder': MeshoptEncoder,
});

setGlbIOFactory(async () => io);

const SUPPORTED = [
    'KHR_materials_unlit',
    'KHR_texture_transform',
    'KHR_materials_emissive_strength',
];

/*
 * Caja entre min y max (12 triángulos).
 */
function createBoxMesh(document, [x0, y0, z0], [x1, y1, z1], name = 'Box') {
    const buffer = document.getRoot().listBuffers()[0] ?? document.createBuffer();

    const corners = [
        [x0, y0, z0], [x1, y0, z0], [x1, y1, z0], [x0, y1, z0],
        [x0, y0, z1], [x1, y0, z1], [x1, y1, z1], [x0, y1, z1],
    ];

    const indices = [
        0, 2, 1, 0, 3, 2, 4, 5, 6, 4, 6, 7, 0, 1, 5, 0, 5, 4,
        3, 6, 2, 3, 7, 6, 0, 4, 7, 0, 7, 3, 1, 2, 6, 1, 6, 5,
    ];

    const position = document
        .createAccessor()
        .setType('VEC3')
        .setArray(new Float32Array(corners.flat()))
        .setBuffer(buffer);

    const index = document
        .createAccessor()
        .setType('SCALAR')
        .setArray(new Uint16Array(indices))
        .setBuffer(buffer);

    const primitive = document
        .createPrimitive()
        .setAttribute('POSITION', position)
        .setIndices(index)
        .setMaterial(document.createMaterial('Material'));

    return document.createMesh(name).addPrimitive(primitive);
}

/*
 * Modelo base: caja de 0.5 × 2 × 1 m centrada en el origen (no apoyada en el suelo).
 */
function createPlain() {
    const document = new Document();
    document.createBuffer();

    const node = document
        .createNode('Model')
        .setMesh(createBoxMesh(document, [-0.25, -1, -0.5], [0.25, 1, 0.5]));

    document.createScene('Scene').addChild(node);

    return document;
}

function createAnimated() {
    const document = createPlain();
    const buffer = document.getRoot().listBuffers()[0];
    const node = document.getRoot().listNodes()[0];

    const input = document
        .createAccessor()
        .setType('SCALAR')
        .setArray(new Float32Array([0, 1]))
        .setBuffer(buffer);

    const output = document
        .createAccessor()
        .setType('VEC3')
        .setArray(new Float32Array([0, 0, 0, 0, 0.5, 0]))
        .setBuffer(buffer);

    const sampler = document
        .createAnimationSampler()
        .setInput(input)
        .setOutput(output)
        .setInterpolation('LINEAR');

    const channel = document
        .createAnimationChannel()
        .setTargetNode(node)
        .setTargetPath('translation')
        .setSampler(sampler);

    document.createAnimation('Salto').addSampler(sampler).addChannel(channel);

    return document;
}

/*
 * Malla con piel de 2 m de alto en la pose de enlace. El nodo de la malla
 * tiene una transformación grande que se debe ignorar (especificación glTF).
 */
function createSkinned() {
    const document = new Document();
    const buffer = document.createBuffer();

    const mesh = createBoxMesh(document, [-0.25, 0, -0.5], [0.25, 2, 0.5]);
    const primitive = mesh.listPrimitives()[0];
    const positions = primitive.getAttribute('POSITION').getArray();
    const count = positions.length / 3;

    const joints = new Uint8Array(count * 4);
    const weights = new Float32Array(count * 4);

    for (let i = 0; i < count; i++) {
        joints[i * 4] = positions[i * 3 + 1] < 1 ? 0 : 1;
        weights[i * 4] = 1;
    }

    primitive
        .setAttribute(
            'JOINTS_0',
            document.createAccessor().setType('VEC4').setArray(joints).setBuffer(buffer),
        )
        .setAttribute(
            'WEIGHTS_0',
            document.createAccessor().setType('VEC4').setArray(weights).setBuffer(buffer),
        );

    const armature = document.createNode('Armature').setScale([0.5, 0.5, 0.5]);
    const hip = document.createNode('Hip');
    const chest = document.createNode('Chest').setTranslation([0, 1, 0]);

    armature.addChild(hip);
    hip.addChild(chest);

    // Inversas de las matrices mundiales de cada articulación en la pose de enlace.
    const inverseBind = document
        .createAccessor()
        .setType('MAT4')
        .setArray(
            new Float32Array([
                2, 0, 0, 0, 0, 2, 0, 0, 0, 0, 2, 0, 0, 0, 0, 1,
                2, 0, 0, 0, 0, 2, 0, 0, 0, 0, 2, 0, 0, -1, 0, 1,
            ]),
        )
        .setBuffer(buffer);

    const skin = document
        .createSkin('Skin')
        .addJoint(hip)
        .addJoint(chest)
        .setSkeleton(hip)
        .setInverseBindMatrices(inverseBind);

    const body = document
        .createNode('Body')
        .setMesh(mesh)
        .setSkin(skin)
        .setTranslation([100, 0, 0])
        .setScale([10, 10, 10]);

    document.createScene('Scene').addChild(armature).addChild(body);

    return document;
}

function createUnsupportedExtension() {
    const document = createPlain();
    const clearcoat = document
        .createExtension(KHRMaterialsClearcoat)
        .createClearcoat()
        .setClearcoatFactor(1);

    document.getRoot().listMaterials()[0].setExtension('KHR_materials_clearcoat', clearcoat);

    return document;
}

/*
 * Geometría a +50 m en X.
 */
function createOffCenter() {
    const document = new Document();
    document.createBuffer();

    const node = document
        .createNode('Model')
        .setMesh(createBoxMesh(document, [49.75, 3, -0.5], [50.25, 5, 0.5]));

    document.createScene('Scene').addChild(node);

    return document;
}

/*
 * Exportado en cm con eje Z hacia arriba: raíz con rotación y escala.
 */
function createRootTransform() {
    const document = new Document();
    document.createBuffer();

    const root = document
        .createNode('Root')
        .setRotation([-Math.SQRT1_2, 0, 0, Math.SQRT1_2])
        .setScale([0.01, 0.01, 0.01]);

    root.addChild(
        document
            .createNode('Model')
            .setMesh(createBoxMesh(document, [-25, -50, 0], [25, 50, 200])),
    );

    document.createScene('Scene').addChild(root);

    return document;
}

function createMultiScene() {
    const document = createPlain();
    const node = document
        .createNode('Other')
        .setMesh(document.getRoot().listMeshes()[0]);

    document.createScene('Other').addChild(node);

    return document;
}

/*
 * Archivo ya escalado armado a mano: caja de 2 m de alto bajo un ScaleRoot.
 */
function createScaled({
    scale = [0.75, 0.75, 0.75],
    rotation = [0, 0, 0, 1],
    translation = [0, 0.75, 0],
    rootName = 'ScaleRoot',
    rootMesh = false,
    extraRoot = false,
} = {}) {
    const document = createPlain();
    const scene = document.getRoot().listScenes()[0];
    const model = scene.listChildren()[0];

    const root = document
        .createNode(rootName)
        .setScale(scale)
        .setRotation(rotation)
        .setTranslation(translation);

    if (rootMesh) {
        root.setMesh(document.getRoot().listMeshes()[0]);
    }

    scene.removeChild(model);
    root.addChild(model);
    scene.addChild(root);

    if (extraRoot) {
        scene.addChild(document.createNode('Extra'));
    }

    return document;
}

const write = async (name, document) => {
    writeFileSync(join(directory, name), await io.writeBinary(document));
    console.log('✓', name);
};

const writeInput = async (name, document) => {
    const glb = await io.writeBinary(document);
    writeFileSync(join(inputDirectory, name), glb);
    console.log('✓ input/' + name);

    return glb;
};

/*
 * Entradas para resizeGlb.
 */
const plain = await writeInput('plain.glb', createPlain());

const dracoDocument = createPlain();
await dracoDocument.transform(draco());
await writeInput('draco.glb', dracoDocument);

const meshoptDocument = createPlain();
await meshoptDocument.transform(meshopt({ encoder: MeshoptEncoder }));
await writeInput('meshopt.glb', meshoptDocument);

const quantizedDocument = createPlain();
await quantizedDocument.transform(quantize());
await writeInput('quantized.glb', quantizedDocument);

await writeInput('animated.glb', createAnimated());
const skinned = await writeInput('skinned.glb', createSkinned());
const unsupported = await writeInput('unsupported-extension.glb', createUnsupportedExtension());
await writeInput('off-center.glb', createOffCenter());
await writeInput('root-transform.glb', createRootTransform());
await writeInput('multi-scene.glb', createMultiScene());

/*
 * Archivos escalados con resizeGlb (válidos para el servidor).
 */
const save = (name, glb) => {
    writeFileSync(join(directory, name), glb);
    console.log('✓', name);
};

save('valid.glb', (await resizeGlb(plain, 1.5, { supportedExtensions: SUPPORTED })).glb);
save('skinned.glb', (await resizeGlb(skinned, 1.5, { supportedExtensions: SUPPORTED })).glb);
save(
    'unsupported-extension.glb',
    (await resizeGlb(unsupported, 1.5, { supportedExtensions: SUPPORTED })).glb,
);

/*
 * Archivos escalados con errores (armados a mano).
 */
await write('off-center-x.glb', createScaled({ translation: [0.3, 0.75, 0] }));
await write('off-center-z.glb', createScaled({ translation: [0, 0.75, -0.3] }));
await write('min-y.glb', createScaled({ translation: [0, 0.9, 0] }));
await write('missing-scale-root.glb', createScaled({ rootName: 'Root' }));
await write(
    'rotated-scale-root.glb',
    createScaled({ rotation: [0, Math.sin(Math.PI / 8), 0, Math.cos(Math.PI / 8)] }),
);
await write('non-uniform-scale-root.glb', createScaled({ scale: [0.75, 0.75, 0.5] }));
await write('scale-root-with-mesh.glb', createScaled({ rootMesh: true }));
await write('two-roots.glb', createScaled({ extraRoot: true }));
await write('multi-scene.glb', createMultiScene());

const dracoScaled = createScaled();
await dracoScaled.transform(draco());
await write('draco.glb', dracoScaled);

const meshoptScaled = createScaled();
await meshoptScaled.transform(meshopt({ encoder: MeshoptEncoder }));
await write('meshopt.glb', meshoptScaled);

const quantizedScaled = createScaled();
await quantizedScaled.transform(quantize());
await write('quantized.glb', quantizedScaled);

/*
 * Encabezados inválidos.
 */
const valid = await io.writeBinary(createScaled());

const badMagic = new Uint8Array(valid);
badMagic.set([0x6e, 0x6f, 0x70, 0x65], 0); // "nope"
save('bad-magic.glb', badMagic);

save('truncated.glb', valid.slice(0, Math.floor(valid.length / 2)));
