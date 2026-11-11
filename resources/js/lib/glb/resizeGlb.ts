/**
 * Escalado de modelos GLB a su tamaño real (1 unidad = 1 m).
 *
 * Solo se carga con import() dinámico desde el navegador: nunca forma parte
 * del bundle principal ni se ejecuta durante el SSR.
 *
 * Contrato del origen en el archivo de salida: [0,0,0] es el centro inferior
 * de la caja del modelo (centro en X/Z, Y mínima), +Y hacia arriba y el
 * modelo apoyado en Y = 0.
 */
import { WebIO } from '@gltf-transform/core';
import type {
    Accessor,
    Document,
    mat4,
    Node,
    PlatformIO,
    Property,
    Scene,
    vec3,
} from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { dequantize, getBounds, unpartition } from '@gltf-transform/functions';

/*
 * Extensiones de compresión: se eliminan al procesar y el servidor las rechaza.
 */
export const COMPRESSION_EXTENSIONS = [
    'KHR_draco_mesh_compression',
    'EXT_meshopt_compression',
    'KHR_mesh_quantization',
] as const;

export const SCALE_ROOT_NAME = 'ScaleRoot';

/*
 * Tolerancias de la verificación final (más estrictas que las del servidor).
 */
const HEIGHT_TOLERANCE = 0.001;
const ORIGIN_ABSOLUTE_TOLERANCE = 0.001;
const ORIGIN_RELATIVE_TOLERANCE = 0.001;
// Diferencia admitida entre la matriz del nodo y la de enlace de su piel.
const SKIN_MATCH_TOLERANCE = 0.0001;

export interface GlbDimensions {
    height: number;
    width: number;
    depth: number;
}

export interface GlbInspection extends GlbDimensions {
    hasSkins: boolean;
    extensionsUsed: string[];
    warnings: string[];
}

export interface GlbResizeResult {
    glb: Uint8Array;
    originalHeight: number;
    finalHeight: number;
    width: number;
    depth: number;
    factor: number;
    warnings: string[];
}

export interface GlbOptions {
    /*
     * Extensiones que ViroReact muestra correctamente (config('glb.supported_extensions')).
     */
    supportedExtensions?: readonly string[];
}

interface Bounds {
    min: vec3;
    max: vec3;
    skinned: boolean;
    approximate: boolean;
}

/*
 * Lector/escritor con todas las extensiones y los decodificadores de
 * Draco y Meshopt. Las pruebas pueden reemplazarlo (en Node no hay ?url).
 */
type IOFactory = () => Promise<PlatformIO>;

let ioFactory: IOFactory = async () => {
    const [{ default: draco3d }, { MeshoptDecoder }, { default: dracoWasmUrl }] =
        await Promise.all([
            import('draco3d'),
            import('meshoptimizer/decoder'),
            import('draco3d/draco_decoder.wasm?url'),
        ]);

    await MeshoptDecoder.ready;

    return new WebIO().registerExtensions(ALL_EXTENSIONS).registerDependencies({
        'draco3d.decoder': await draco3d.createDecoderModule({
            locateFile: () => dracoWasmUrl,
        }),
        'meshopt.decoder': MeshoptDecoder,
    });
};

let ioPromise: Promise<PlatformIO> | null = null;

export function setGlbIOFactory(factory: IOFactory): void {
    ioFactory = factory;
    ioPromise = null;
}

const getIO = (): Promise<PlatformIO> => {
    ioPromise ??= ioFactory().catch((error) => {
        ioPromise = null;

        throw error;
    });

    return ioPromise;
};

const toUint8Array = (buffer: ArrayBuffer | Uint8Array): Uint8Array =>
    buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);

const readDocument = async (buffer: ArrayBuffer | Uint8Array): Promise<Document> => {
    const io = await getIO();

    try {
        return await io.readBinary(toUint8Array(buffer));
    } catch (error) {
        throw new Error(
            `No se pudo leer el archivo GLB: ${error instanceof Error ? error.message : String(error)}`,
        );
    }
};

/*
 * Escena a usar: la predeterminada o la única. Solo se admite una escena.
 */
const getScene = (document: Document): Scene => {
    const root = document.getRoot();
    const scenes = root.listScenes();

    if (scenes.length > 1) {
        throw new Error(
            `El archivo tiene ${scenes.length} escenas. Solo se admiten modelos con una sola escena.`,
        );
    }

    const scene = root.getDefaultScene() ?? scenes[0];

    if (!scene || !scene.listChildren().length) {
        throw new Error('El archivo no contiene ninguna escena con nodos.');
    }

    return scene;
};

/*
 * Matrices 4x4 en orden de columnas (como glTF).
 */
const multiply = (a: mat4, b: mat4): mat4 => {
    const out = new Array(16).fill(0) as unknown as mat4;

    for (let column = 0; column < 4; column++) {
        for (let row = 0; row < 4; row++) {
            let sum = 0;

            for (let k = 0; k < 4; k++) {
                sum += a[k * 4 + row] * b[column * 4 + k];
            }

            out[column * 4 + row] = sum;
        }
    }

    return out;
};

const transformPoint = (m: mat4, [x, y, z]: number[]): vec3 => [
    m[0] * x + m[4] * y + m[8] * z + m[12],
    m[1] * x + m[5] * y + m[9] * z + m[13],
    m[2] * x + m[6] * y + m[10] * z + m[14],
];

const IDENTITY: mat4 = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1];

const expand = (bounds: Bounds, point: vec3) => {
    for (let axis = 0; axis < 3; axis++) {
        bounds.min[axis] = Math.min(bounds.min[axis], point[axis]);
        bounds.max[axis] = Math.max(bounds.max[axis], point[axis]);
    }
};

/*
 * Caja de una malla con piel en la pose de enlace: cada vértice se transforma
 * con (matriz mundial de la articulación × matriz inversa de enlace),
 * ponderado por sus pesos. La transformación del nodo de la malla se ignora,
 * como indica la especificación de glTF.
 */
const expandSkinnedMesh = (node: Node, bounds: Bounds): boolean => {
    const skin = node.getSkin();
    const mesh = node.getMesh();

    if (!skin || !mesh) {
        return false;
    }

    const inverseBind = skin.getInverseBindMatrices();
    const jointMatrices = skin.listJoints().map((joint, index) => {
        const ibm = inverseBind
            ? (inverseBind.getElement(index, new Array(16).fill(0)) as mat4)
            : IDENTITY;

        return multiply(joint.getWorldMatrix(), ibm);
    });

    for (const primitive of mesh.listPrimitives()) {
        const position = primitive.getAttribute('POSITION');

        if (!position) {
            continue;
        }

        const sets = [0, 1, 2, 3]
            .map((set) => ({
                joints: primitive.getAttribute(`JOINTS_${set}`),
                weights: primitive.getAttribute(`WEIGHTS_${set}`),
            }))
            .filter((set) => set.joints && set.weights);

        if (!sets.length) {
            return false;
        }

        const local = [0, 0, 0];
        const joints = [0, 0, 0, 0];
        const weights = [0, 0, 0, 0];

        for (let i = 0; i < position.getCount(); i++) {
            position.getElement(i, local);

            const point: vec3 = [0, 0, 0];
            let totalWeight = 0;

            for (const set of sets) {
                set.joints!.getElement(i, joints);
                set.weights!.getElement(i, weights);

                for (let k = 0; k < 4; k++) {
                    const matrix = jointMatrices[joints[k]];

                    if (!weights[k] || !matrix) {
                        continue;
                    }

                    const skinned = transformPoint(matrix, local);

                    point[0] += skinned[0] * weights[k];
                    point[1] += skinned[1] * weights[k];
                    point[2] += skinned[2] * weights[k];
                    totalWeight += weights[k];
                }
            }

            if (totalWeight > 0) {
                expand(bounds, [
                    point[0] / totalWeight,
                    point[1] / totalWeight,
                    point[2] / totalWeight,
                ]);
            }
        }
    }

    return true;
};

/*
 * Caja de la escena. Sin pieles se usa getBounds; con pieles se recorre la
 * escena y las mallas con piel se miden en la pose de enlace.
 */
const computeBounds = (scene: Scene): Bounds => {
    const hasSkins = listSceneNodes(scene).some(
        (node) => node.getMesh() && node.getSkin(),
    );

    if (!hasSkins) {
        const { min, max } = getBounds(scene);

        return { min, max, skinned: false, approximate: false };
    }

    const bounds: Bounds = {
        min: [Infinity, Infinity, Infinity],
        max: [-Infinity, -Infinity, -Infinity],
        skinned: true,
        approximate: false,
    };

    for (const node of listSceneNodes(scene)) {
        const mesh = node.getMesh();

        if (!mesh) {
            continue;
        }

        if (node.getSkin()) {
            try {
                if (expandSkinnedMesh(node, bounds)) {
                    continue;
                }
            } catch {
                // Se usa la caja aproximada del nodo.
            }

            bounds.approximate = true;
        }

        expandMesh(node, bounds);
    }

    return bounds;
};

/*
 * Vértices de la malla del nodo (sin sus hijos) en coordenadas del mundo.
 */
const expandMesh = (node: Node, bounds: Bounds) => {
    const world = node.getWorldMatrix();
    const local = [0, 0, 0];

    for (const primitive of node.getMesh()?.listPrimitives() ?? []) {
        const position = primitive.getAttribute('POSITION');

        for (let i = 0; i < (position?.getCount() ?? 0); i++) {
            expand(bounds, transformPoint(world, position!.getElement(i, local)));
        }
    }
};

const listSceneNodes = (scene: Scene): Node[] => {
    const nodes: Node[] = [];

    scene.traverse((node) => {
        nodes.push(node);
    });

    return nodes;
};

/*
 * Inversa de una matriz afín 4x4 (última fila 0 0 0 1), en orden de columnas.
 */
const invertAffine = (m: mat4): mat4 => {
    const [a, b, c, , d, e, f, , g, h, i] = m;
    const det = a * (e * i - f * h) - d * (b * i - c * h) + g * (b * f - c * e);

    if (!det) {
        throw new Error('La matriz del modelo no es invertible.');
    }

    const r = [
        (e * i - h * f) / det,
        (h * c - b * i) / det,
        (b * f - e * c) / det,
        (g * f - d * i) / det,
        (a * i - g * c) / det,
        (d * c - a * f) / det,
        (d * h - g * e) / det,
        (g * b - a * h) / det,
        (a * e - d * b) / det,
    ];
    const [tx, ty, tz] = [m[12], m[13], m[14]];

    return [
        r[0],
        r[1],
        r[2],
        0,
        r[3],
        r[4],
        r[5],
        0,
        r[6],
        r[7],
        r[8],
        0,
        -(r[0] * tx + r[3] * ty + r[6] * tz),
        -(r[1] * tx + r[4] * ty + r[7] * tz),
        -(r[2] * tx + r[5] * ty + r[8] * tz),
        1,
    ] as unknown as mat4;
};

const transformVector = (m: mat4, [x, y, z]: number[]): vec3 => [
    m[0] * x + m[4] * y + m[8] * z,
    m[1] * x + m[5] * y + m[9] * z,
    m[2] * x + m[6] * y + m[10] * z,
];

// Normales: la transpuesta de la inversa de la parte lineal.
const transformNormal = (inverse: mat4, [x, y, z]: number[]): vec3 => [
    inverse[0] * x + inverse[1] * y + inverse[2] * z,
    inverse[4] * x + inverse[5] * y + inverse[6] * z,
    inverse[8] * x + inverse[9] * y + inverse[10] * z,
];

const normalize = ([x, y, z]: vec3): vec3 => {
    const length = Math.hypot(x, y, z) || 1;

    return [x / length, y / length, z / length];
};

const matricesMatch = (a: mat4, b: mat4): boolean =>
    a.every(
        (value, index) =>
            Math.abs(value - b[index]) <=
            SKIN_MATCH_TOLERANCE * Math.max(1, Math.abs(value)),
    );

/*
 * Accesor propio del dueño: si otro elemento lo comparte, se trabaja sobre
 * una copia para no modificar al otro.
 */
const ownAccessor = (
    document: Document,
    accessor: Accessor,
    owner: Property,
): Accessor => {
    const root = document.getRoot();
    const shared = accessor
        .listParents()
        .some((parent) => parent !== root && parent !== owner);

    return shared ? accessor.clone() : accessor;
};

const mapAccessor = (
    accessor: Accessor,
    map: (element: number[]) => number[],
) => {
    const element: number[] = [];

    for (let i = 0; i < accessor.getCount(); i++) {
        accessor.setElement(i, map(accessor.getElement(i, element)));
    }
};

/*
 * ViroReact dibuja una malla con piel quieta con la matriz mundial de su
 * nodo, pero animada con las articulaciones (matriz mundial × inversa de
 * enlace), como pide glTF. Si las dos no coinciden en la pose de enlace
 * (p. ej. una escala sin aplicar en el Armature de Blender), el modelo
 * cambia de tamaño al empezar la animación. Aquí se expresa la malla en el
 * espacio de su nodo: se transforman sus vértices y se ajustan las matrices
 * inversas de enlace para que ambas coincidan. El modelo según glTF (el que
 * se mide) no cambia; la jerarquía y las animaciones tampoco.
 */
const alignSkinnedMeshes = (document: Document, scene: Scene) => {
    for (const node of listSceneNodes(scene)) {
        const skin = node.getSkin();
        const mesh = node.getMesh();
        const inverseBind = skin?.getInverseBindMatrices();

        if (!skin || !mesh || !inverseBind || !skin.listJoints().length) {
            continue;
        }

        // Una malla usada por varios nodos no se puede ajustar para uno solo.
        const meshUsers = mesh
            .listParents()
            .filter((parent) => parent.propertyType === 'Node');

        if (meshUsers.length > 1) {
            continue;
        }

        const binds = skin
            .listJoints()
            .map((joint, index) =>
                multiply(
                    joint.getWorldMatrix(),
                    inverseBind.getElement(
                        index,
                        new Array(16).fill(0),
                    ) as mat4,
                ),
            );
        const bind = binds[0];

        // Sin una matriz de enlace común no hay a qué alinear el nodo.
        if (binds.some((matrix) => !matricesMatch(matrix, bind))) {
            continue;
        }

        const world = node.getWorldMatrix();

        if (matricesMatch(world, bind)) {
            continue;
        }

        // Vértices: de la pose de enlace al espacio del nodo (W⁻¹ × B).
        const toNode = multiply(invertAffine(world), bind);
        const toNodeInverse = invertAffine(toNode);

        for (const primitive of mesh.listPrimitives()) {
            const attributes: [string, (value: number[]) => number[]][] = [
                ['POSITION', (value) => transformPoint(toNode, value)],
                [
                    'NORMAL',
                    (value) => normalize(transformNormal(toNodeInverse, value)),
                ],
                [
                    'TANGENT',
                    (value) => [
                        ...normalize(transformVector(toNode, value)),
                        value[3],
                    ],
                ],
            ];

            for (const [semantic, map] of attributes) {
                const accessor = primitive.getAttribute(semantic);

                if (accessor) {
                    const own = ownAccessor(document, accessor, primitive);

                    mapAccessor(own, map);
                    primitive.setAttribute(semantic, own);
                }
            }

            // Los morph targets son desplazamientos: solo la parte lineal.
            for (const target of primitive.listTargets()) {
                for (const semantic of target.listSemantics()) {
                    const accessor = target.getAttribute(semantic)!;
                    const own = ownAccessor(document, accessor, target);

                    mapAccessor(own, (value) =>
                        semantic === 'NORMAL'
                            ? transformNormal(toNodeInverse, value)
                            : transformVector(toNode, value),
                    );
                    target.setAttribute(semantic, own);
                }
            }
        }

        // Inversas de enlace: IBM × B⁻¹ × W, así articulación × IBM = W.
        const correction = multiply(invertAffine(bind), world);
        const ownInverseBind = ownAccessor(document, inverseBind, skin);

        mapAccessor(ownInverseBind, (value) =>
            multiply(value as mat4, correction),
        );
        skin.setInverseBindMatrices(ownInverseBind);
    }
};

const dimensionsOf = (bounds: Bounds): GlbDimensions => ({
    height: bounds.max[1] - bounds.min[1],
    width: bounds.max[0] - bounds.min[0],
    depth: bounds.max[2] - bounds.min[2],
});

const assertMeasurable = (dimensions: GlbDimensions) => {
    if (!Number.isFinite(dimensions.height) || dimensions.height <= 0) {
        throw new Error(
            'No se pudo medir el alto del modelo (es 0 o no tiene geometría).',
        );
    }
};

const extensionWarnings = (
    extensionsUsed: string[],
    supported: readonly string[],
): string[] => {
    const unsupported = extensionsUsed.filter(
        (name) =>
            !supported.includes(name) &&
            !(COMPRESSION_EXTENSIONS as readonly string[]).includes(name),
    );

    return unsupported.length
        ? [
              `El modelo usa extensiones que podrían no mostrarse bien en la app: ${unsupported.join(', ')}.`,
          ]
        : [];
};

const SKINNED_WARNING =
    'El modelo tiene esqueleto (skinning): se midió en su pose de enlace; el tamaño en la app puede variar al animarse.';

const APPROXIMATE_SKIN_WARNING =
    'No se pudo medir el esqueleto del modelo con precisión: el alto y el origen pueden variar ligeramente.';

/**
 * Información del archivo para mostrarla en el formulario antes de guardar.
 */
export async function inspectGlb(
    buffer: ArrayBuffer | Uint8Array,
    options: GlbOptions = {},
): Promise<GlbInspection> {
    const document = await readDocument(buffer);
    const bounds = computeBounds(getScene(document));
    const dimensions = dimensionsOf(bounds);

    assertMeasurable(dimensions);

    const extensionsUsed = document
        .getRoot()
        .listExtensionsUsed()
        .map((extension) => extension.extensionName);

    return {
        ...dimensions,
        hasSkins: document.getRoot().listSkins().length > 0,
        extensionsUsed,
        warnings: [
            ...extensionWarnings(extensionsUsed, options.supportedExtensions ?? []),
            ...(bounds.skinned ? [SKINNED_WARNING] : []),
            ...(bounds.approximate ? [APPROXIMATE_SKIN_WARNING] : []),
        ],
    };
}

/**
 * Escala el modelo para que mida `targetHeight` metros de alto y deja su
 * origen en el centro inferior. No modifica los vértices: agrega un nodo
 * "ScaleRoot" con la escala y el desplazamiento, así que las animaciones y
 * los esqueletos siguen funcionando.
 */
export async function resizeGlb(
    buffer: ArrayBuffer | Uint8Array,
    targetHeight: number,
    options: GlbOptions = {},
): Promise<GlbResizeResult> {
    if (!Number.isFinite(targetHeight) || targetHeight <= 0) {
        throw new Error('El alto final debe ser mayor que 0.');
    }

    const document = await readDocument(buffer);
    const root = document.getRoot();
    const scene = getScene(document);

    const extensionsUsed = root
        .listExtensionsUsed()
        .map((extension) => extension.extensionName);

    alignSkinnedMeshes(document, scene);

    const bounds = computeBounds(scene);
    const original = dimensionsOf(bounds);

    assertMeasurable(original);

    const factor = targetHeight / original.height;
    const centerX = (bounds.min[0] + bounds.max[0]) / 2;
    const centerZ = (bounds.min[2] + bounds.max[2]) / 2;

    const scaleRoot = document
        .createNode(SCALE_ROOT_NAME)
        .setScale([factor, factor, factor])
        .setRotation([0, 0, 0, 1])
        .setTranslation([
            -centerX * factor,
            -bounds.min[1] * factor,
            -centerZ * factor,
        ]);

    for (const child of scene.listChildren()) {
        scene.removeChild(child);
        scaleRoot.addChild(child);
    }

    scene.addChild(scaleRoot);
    root.setDefaultScene(scene);

    /*
     * Salida sin compresión para ViroReact.
     */
    await document.transform(dequantize(), unpartition());

    for (const extension of root.listExtensionsUsed()) {
        if (
            (COMPRESSION_EXTENSIONS as readonly string[]).includes(
                extension.extensionName,
            )
        ) {
            extension.dispose();
        }
    }

    const io = await getIO();
    const glb = await io.writeBinary(document);

    const final = await verifyOutput(glb, targetHeight);

    return {
        glb,
        originalHeight: original.height,
        finalHeight: final.height,
        width: final.width,
        depth: final.depth,
        factor,
        warnings: [
            ...extensionWarnings(extensionsUsed, options.supportedExtensions ?? []),
            ...(bounds.skinned ? [SKINNED_WARNING] : []),
            ...(bounds.approximate ? [APPROXIMATE_SKIN_WARNING] : []),
        ],
    };
}

/*
 * Vuelve a leer el archivo generado y comprueba el contrato del origen.
 */
async function verifyOutput(
    glb: Uint8Array,
    targetHeight: number,
): Promise<GlbDimensions> {
    const document = await readDocument(glb);
    const bounds = computeBounds(getScene(document));
    const dimensions = dimensionsOf(bounds);

    const remaining = document
        .getRoot()
        .listExtensionsUsed()
        .map((extension) => extension.extensionName)
        .filter((name) =>
            (COMPRESSION_EXTENSIONS as readonly string[]).includes(name),
        );

    if (remaining.length) {
        throw new Error(
            `El archivo generado aún usa compresión (${remaining.join(', ')}).`,
        );
    }

    if (
        Math.abs(dimensions.height - targetHeight) >
        targetHeight * HEIGHT_TOLERANCE
    ) {
        throw new Error(
            `El modelo generado mide ${dimensions.height.toFixed(3)} m de alto en lugar de ${targetHeight.toFixed(3)} m.`,
        );
    }

    const verticalTolerance = Math.max(
        ORIGIN_ABSOLUTE_TOLERANCE,
        dimensions.height * ORIGIN_RELATIVE_TOLERANCE,
    );

    if (Math.abs(bounds.min[1]) > verticalTolerance) {
        throw new Error(
            'El modelo generado no queda apoyado en el suelo (Y mínima distinta de 0).',
        );
    }

    const horizontalTolerance = Math.max(
        ORIGIN_ABSOLUTE_TOLERANCE,
        Math.max(dimensions.width, dimensions.depth) * ORIGIN_RELATIVE_TOLERANCE,
    );

    const centerX = (bounds.min[0] + bounds.max[0]) / 2;
    const centerZ = (bounds.min[2] + bounds.max[2]) / 2;

    if (
        Math.abs(centerX) > horizontalTolerance ||
        Math.abs(centerZ) > horizontalTolerance
    ) {
        throw new Error(
            'El modelo generado no queda centrado en el origen (X/Z).',
        );
    }

    return dimensions;
}
