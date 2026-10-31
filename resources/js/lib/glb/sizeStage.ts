/**
 * Referencia de tamaño en la vista previa del modelo (solo navegador).
 *
 * Escala el modelo de forma uniforme respecto a su escala original, lo apoya
 * en el suelo con su centro inferior en el origen (el mismo contrato que el
 * archivo final) y agrega una cuadrícula de 1 m y un objeto de referencia.
 */
import type { TransformNode as BabylonTransformNode } from '@babylonjs/core/Meshes/transformNode';
import type { HTML3DElement } from '@babylonjs/viewer';

export type SizeReference = 'person' | 'cube' | 'none';

export const PERSON_HEIGHT = 1.7;

type ViewerDetails = NonNullable<HTML3DElement['viewerDetails']>;

export interface SizeStage {
    /*
     * Alto del modelo tal como viene en el archivo (m).
     */
    readonly rawHeight: number;
    update: (targetHeight: number | null, reference: SizeReference) => number;
    fitCamera: () => number;
    dispose: () => void;
}

export async function createSizeStage(
    details: ViewerDetails,
): Promise<SizeStage | null> {
    const root = details.model?.assetContainer.rootNodes[0] as
        | BabylonTransformNode
        | undefined;

    if (!root) {
        return null;
    }

    const [
        { CreateLineSystem },
        { CreateBox },
        { CreateCapsule },
        { CreateSphere },
        { StandardMaterial },
        { Color3 },
        { Vector3 },
        { TransformNode },
    ] = await Promise.all([
        import('@babylonjs/core/Meshes/Builders/linesBuilder'),
        import('@babylonjs/core/Meshes/Builders/boxBuilder'),
        import('@babylonjs/core/Meshes/Builders/capsuleBuilder'),
        import('@babylonjs/core/Meshes/Builders/sphereBuilder'),
        import('@babylonjs/core/Materials/standardMaterial'),
        import('@babylonjs/core/Maths/math.color'),
        import('@babylonjs/core/Maths/math.vector'),
        import('@babylonjs/core/Meshes/transformNode'),
    ]);

    const { scene, camera } = details;

    /*
     * Escala y posición originales (el __root__ de glTF trae (1, 1, -1)).
     */
    const originalScaling = root.scaling.clone();
    const originalPosition = root.position.clone();

    const measure = () => root.getHierarchyBoundingVectors(true);

    const raw = measure();
    const rawHeight = raw.max.y - raw.min.y;

    if (!Number.isFinite(rawHeight) || rawHeight <= 0) {
        return null;
    }

    const helpers = new TransformNode('size-helpers', scene);
    // El visor solo dibuja cuando algo cambia.
    const refresh = () => details.markSceneMutated();

    /*
     * Con WebGPU el visor graba los comandos de dibujo una vez y los repite
     * en cada cuadro (snapshot rendering). Agregar, quitar o mostrar mallas
     * requiere volver a grabarlos; si no, el visor puede dibujar de más o
     * dejar de usar la grabación. No es una API pública: si no existe, no
     * se hace nada (WebGL).
     */
    const snapshot = (
        details.viewer as unknown as {
            _snapshotHelper?: {
                disableSnapshotRendering: () => void;
                enableSnapshotRendering: () => void;
            };
        }
    )._snapshotHelper;

    const mutate = <T>(change: () => T): T => {
        snapshot?.disableSnapshotRendering();

        try {
            return change();
        } finally {
            snapshot?.enableSnapshotRendering();
            refresh();
        }
    };

    /*
     * Material opaco y congelado: sin transparencias que ordenar en cada
     * cuadro mientras se reproduce una animación.
     */
    const material = new StandardMaterial('size-reference-material', scene);
    material.diffuseColor = Color3.FromHexString('#B7CCBE');
    material.specularColor = Color3.Black();
    material.freeze();

    /*
     * Persona de 1.70 m: cuerpo (cápsula) y cabeza (esfera).
     */
    const person = new TransformNode('size-person', scene);
    person.parent = helpers;

    const body = CreateCapsule(
        'size-person-body',
        { height: 1.45, radius: 0.2, tessellation: 12 },
        scene,
    );
    body.position.y = 1.45 / 2;
    body.parent = person;

    const head = CreateSphere('size-person-head', { diameter: 0.25, segments: 8 }, scene);
    head.position.y = PERSON_HEIGHT - 0.125;
    head.parent = person;

    const cube = CreateBox('size-cube', { size: 1 }, scene);
    cube.position.y = 0.5;
    cube.parent = helpers;

    const prepareStatic = (mesh: typeof body) => {
        mesh.isPickable = false;
        mesh.doNotSyncBoundingInfo = true;
        mesh.alwaysSelectAsActiveMesh = true;
    };

    for (const mesh of [body, head, cube]) {
        mesh.material = material;
        prepareStatic(mesh);
    }

    let grid: ReturnType<typeof CreateLineSystem> | null = null;
    let gridSize = 0;
    let fitMin = new Vector3();
    let fitMax = new Vector3();

    /*
     * Los ayudantes no se mueven mientras se anima el modelo: se congelan sus
     * matrices y solo se recalculan al cambiar el alto o la referencia.
     */
    const freezeHelpers = () => {
        for (const node of [helpers, person, body, head, cube, grid]) {
            node?.unfreezeWorldMatrix();
            node?.freezeWorldMatrix();
        }
    };

    /*
     * Cuadrícula con líneas cada 1 m que cubre el modelo y la referencia.
     * Solo se vuelve a crear si cambia su tamaño.
     */
    const buildGrid = (halfSize: number) => {
        const size = Math.min(150, Math.max(2, Math.ceil(halfSize)));

        if (grid && size === gridSize) {
            return;
        }

        grid?.dispose();
        gridSize = size;

        const lines = [];

        for (let i = -size; i <= size; i++) {
            lines.push([new Vector3(i, 0, -size), new Vector3(i, 0, size)]);
            lines.push([new Vector3(-size, 0, i), new Vector3(size, 0, i)]);
        }

        // Color opaco (sin alpha) para no pasar por el dibujo de transparencias.
        grid = CreateLineSystem('size-grid', { lines }, scene);
        grid.color = Color3.FromHexString('#C3D6C9');
        prepareStatic(grid);
        grid.parent = helpers;
    };

    const update = (targetHeight: number | null, reference: SizeReference) =>
        mutate(() => {
            const factor =
                targetHeight && targetHeight > 0 ? targetHeight / rawHeight : 1;

            root.scaling.copyFrom(originalScaling.scale(factor));
            root.position.copyFrom(originalPosition);
            root.computeWorldMatrix(true);

            /*
             * Centro inferior del modelo en el origen.
             */
            const { min, max } = measure();

            root.position.addInPlace(
                new Vector3(-(min.x + max.x) / 2, -min.y, -(min.z + max.z) / 2),
            );
            root.computeWorldMatrix(true);

            const height = max.y - min.y;
            const halfWidth = (max.x - min.x) / 2;
            const halfDepth = (max.z - min.z) / 2;

            /*
             * La referencia va a la izquierda del modelo.
             */
            const referenceHalfWidth = reference === 'person' ? 0.2 : 0.5;
            const referenceHeight =
                reference === 'person' ? PERSON_HEIGHT : reference === 'cube' ? 1 : 0;
            const gap = Math.max(0.2, 0.15 * Math.max(height, referenceHeight));
            const referenceX = -(halfWidth + gap + referenceHalfWidth);

            person.setEnabled(reference === 'person');
            cube.setEnabled(reference === 'cube');
            person.position.x = referenceX;
            cube.position.x = referenceX;

            const left =
                reference === 'none' ? -halfWidth : referenceX - referenceHalfWidth;

            fitMin = new Vector3(left, 0, -Math.max(halfDepth, referenceHalfWidth));
            fitMax = new Vector3(
                halfWidth,
                Math.max(height, referenceHeight),
                Math.max(halfDepth, referenceHalfWidth),
            );

            buildGrid(Math.max(-left, halfWidth, halfDepth) * 1.5 + 1);
            freezeHelpers();

            return height;
        });

    /*
     * Encuadra el modelo y la referencia; devuelve el radio de la cámara.
     */
    const fitCamera = () => {
        const center = fitMin.add(fitMax).scale(0.5);
        const radius = Math.max(0.01, fitMax.subtract(fitMin).length() / 2);
        const distance = (radius / Math.sin(camera.fov / 2)) * 1.05;

        camera.lowerRadiusLimit = distance * 0.01;
        camera.upperRadiusLimit = distance * 5;
        camera.minZ = distance * 0.01;
        camera.maxZ = distance * 100;

        details.viewer.updateCamera({
            targetX: center.x,
            targetY: center.y,
            targetZ: center.z,
            radius: distance,
        });

        refresh();

        return distance;
    };

    const dispose = () =>
        mutate(() => {
            grid?.dispose();
            helpers.dispose(false, true);
            material.dispose();

            if (!root.isDisposed()) {
                root.scaling.copyFrom(originalScaling);
                root.position.copyFrom(originalPosition);
            }
        });

    return { rawHeight, update, fitCamera, dispose };
}
