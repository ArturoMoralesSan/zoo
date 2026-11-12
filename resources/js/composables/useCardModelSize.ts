import { computed, reactive, ref, watch } from 'vue';
import type { Ref } from 'vue';

import {
    EXTREME_FACTOR_MAX,
    EXTREME_FACTOR_MIN,
    SUSPICIOUS_ORIGINAL_HEIGHT,
    clampMeters,
    formatMeters,
    heightRangeMessage,
    isHeightInRange,
    parseMeters,
    roundMeters,
} from '@/lib/glb/modelSize';
import type { GlbSizeConfig } from '@/lib/glb/modelSize';
import type { GlbDimensions } from '@/lib/glb/resizeGlb';

/**
 * Medidas guardadas del modelo (Edit). null en tarjetas sin modelo.
 */
export interface StoredModel {
    modelFile: string | null;
    originalHeight: number | null;
    finalHeight: number | null;
    finalWidth: number | null;
    finalDepth: number | null;
    /*
     * Ruta autorizada que entrega el original (o el archivo actual en
     * modelos anteriores al escalado).
     */
    originalUrl: string | null;
    /*
     * Peso en bytes del archivo que descarga la app y del original subido.
     */
    fileBytes?: number | null;
    originalBytes?: number | null;
}

/**
 * Campos del modelo que se agregan al formulario al guardar.
 */
export interface ModelFormFields {
    model_original_file?: File;
    model_file?: File;
    model_target_height?: number;
    model_original_height?: number;
    model_final_height?: number;
    model_final_width?: number;
    model_final_depth?: number;
}

const errorMessage = (error: unknown): string =>
    error instanceof Error ? error.message : 'No se pudo procesar el modelo 3D.';

/**
 * Estado del tamaño real del modelo 3D en los formularios de tarjetas.
 *
 * El navegador escala el modelo con resizeGlb (import dinámico, nunca en SSR)
 * y envía el original, el archivo escalado y sus medidas.
 */
export function useCardModelSize(
    config: GlbSizeConfig,
    file: Ref<File | null>,
    stored: StoredModel | null = null,
) {
    /*
     * Alto final pedido, tal como lo escribe el usuario ("1,5" o "1.5").
     * En Edit parte del alto guardado; sin tamaño definido, vacío.
     */
    const heightInput = ref(
        stored?.finalHeight ? String(roundMeters(stored.finalHeight)) : '',
    );

    const storedHeight = stored?.finalHeight
        ? roundMeters(stored.finalHeight)
        : null;

    const inspected = ref<
        (GlbDimensions & { warnings: string[] }) | null
    >(null);

    const inspecting = ref(false);
    const error = ref<string | null>(null);

    /*
     * Peso estimado del archivo optimizado (texturas incluidas) del modelo
     * elegido: se calcula en segundo plano tras medirlo.
     */
    const estimatedBytes = ref<number | null>(null);
    const estimating = ref(false);
    const touched = ref(false);

    const hasModel = computed(() => Boolean(file.value || stored?.modelFile));

    const height = computed(() => parseMeters(heightInput.value));

    /*
     * Medidas del archivo original: del archivo elegido, o derivadas de las
     * guardadas (alto final / factor).
     */
    const original = computed<GlbDimensions | null>(() => {
        if (inspected.value) {
            return inspected.value;
        }

        if (
            !file.value &&
            stored?.originalHeight &&
            stored.finalHeight &&
            stored.finalWidth !== null &&
            stored.finalDepth !== null
        ) {
            const factor = stored.finalHeight / stored.originalHeight;

            return {
                height: stored.originalHeight,
                width: stored.finalWidth / factor,
                depth: stored.finalDepth / factor,
            };
        }

        return null;
    });

    /*
     * Hay que volver a generar el archivo: modelo nuevo o alto distinto.
     */
    const sizeChanged = computed(
        () =>
            !file.value &&
            Boolean(stored?.modelFile) &&
            height.value !== null &&
            height.value !== storedHeight,
    );

    const needsProcessing = computed(
        () => Boolean(file.value) || sizeChanged.value,
    );

    const isRequired = computed(() => Boolean(file.value) || storedHeight !== null);

    const heightError = computed<string | null>(() => {
        if (!hasModel.value) {
            return null;
        }

        if (heightInput.value.trim() === '') {
            return isRequired.value && touched.value
                ? 'Ingresa el alto del modelo en metros.'
                : null;
        }

        return isHeightInRange(height.value, config)
            ? null
            : heightRangeMessage(config);
    });

    const factor = computed(() =>
        original.value && height.value
            ? height.value / original.value.height
            : null,
    );

    const final = computed<GlbDimensions | null>(() => {
        if (!original.value) {
            return null;
        }

        const scale = factor.value ?? 1;

        return {
            height: original.value.height * scale,
            width: original.value.width * scale,
            depth: original.value.depth * scale,
        };
    });

    const warnings = computed(() => {
        const list = [...(inspected.value?.warnings ?? [])];

        if (original.value && original.value.height > SUSPICIOUS_ORIGINAL_HEIGHT) {
            list.push(
                `El archivo mide ${formatMeters(original.value.height)} de alto: probablemente se exportó en centímetros. Revisa el alto final.`,
            );
        }

        if (
            factor.value !== null &&
            (factor.value > EXTREME_FACTOR_MAX || factor.value < EXTREME_FACTOR_MIN)
        ) {
            list.push(
                'La escala es muy grande o muy pequeña: confirma que el alto final sea el correcto.',
            );
        }

        return list;
    });

    /*
     * Medir el archivo elegido para mostrar sus medidas y advertencias.
     */
    let inspectToken = 0;

    watch(
        file,
        async (value) => {
            const token = ++inspectToken;

            inspected.value = null;
            error.value = null;
            touched.value = false;
            estimatedBytes.value = null;
            estimating.value = false;

            if (!value) {
                inspecting.value = false;
                heightInput.value = storedHeight !== null ? String(storedHeight) : '';

                return;
            }

            // Modelo nuevo: el alto se vuelve a pedir.
            heightInput.value = '';
            inspecting.value = true;

            try {
                const buffer = await value.arrayBuffer();
                const { inspectGlb } = await import('@/lib/glb/resizeGlb');
                const result = await inspectGlb(buffer, {
                    supportedExtensions: config.supportedExtensions,
                });

                if (token === inspectToken) {
                    inspected.value = result;
                    void estimate(buffer, result.height, token);
                }
            } catch (exception) {
                if (token === inspectToken) {
                    error.value = errorMessage(exception);
                }
            } finally {
                if (token === inspectToken) {
                    inspecting.value = false;
                }
            }
        },
    );

    /*
     * Mismo proceso que al guardar, con el alto del archivo (escala 1): el
     * alto no cambia el peso, así que el resultado es el peso final.
     */
    async function estimate(buffer: ArrayBuffer, sourceHeight: number, token: number) {
        estimating.value = true;

        try {
            const { resizeGlb } = await import('@/lib/glb/resizeGlb');
            const result = await resizeGlb(buffer, sourceHeight, {
                supportedExtensions: config.supportedExtensions,
                textures: config.textures,
            });

            if (token === inspectToken) {
                estimatedBytes.value = result.glb.byteLength;
            }
        } catch {
            // Sin estimación: si el archivo tiene un problema, se muestra al guardar.
        } finally {
            if (token === inspectToken) {
                estimating.value = false;
            }
        }
    }

    /*
     * Peso del modelo: original → archivo de la app. Con un archivo nuevo,
     * `previous` es el que la app tiene hoy (Edit).
     */
    const fileSizes = computed(() =>
        file.value
            ? {
                  original: file.value.size,
                  optimized: estimatedBytes.value,
                  previous: stored?.fileBytes ?? null,
              }
            : {
                  original: stored?.originalBytes ?? null,
                  optimized: stored?.fileBytes ?? null,
                  previous: null,
              },
    );

    /*
     * Modelos anteriores al escalado: se mide el archivo actual (es el original).
     */
    const inspectStoredOriginal = async () => {
        if (file.value || original.value || !stored?.originalUrl || !stored.modelFile) {
            return;
        }

        inspecting.value = true;

        try {
            const { inspectGlb } = await import('@/lib/glb/resizeGlb');
            const result = await inspectGlb(await fetchOriginal(), {
                supportedExtensions: config.supportedExtensions,
            });

            if (!file.value) {
                inspected.value = result;
            }
        } catch (exception) {
            if (!file.value) {
                error.value = errorMessage(exception);
            }
        } finally {
            inspecting.value = false;
        }
    };

    let originalBuffer: ArrayBuffer | null = null;

    const fetchOriginal = async (): Promise<ArrayBuffer> => {
        if (originalBuffer) {
            return originalBuffer;
        }

        const response = await fetch(stored?.originalUrl ?? '', {
            credentials: 'same-origin',
            headers: { Accept: 'model/gltf-binary' },
        });

        if (!response.ok) {
            throw new Error('No se pudo descargar el archivo original del modelo.');
        }

        originalBuffer = await response.arrayBuffer();

        return originalBuffer;
    };

    const setHeight = (value: number) => {
        heightInput.value = String(clampMeters(value, config));
        touched.value = true;
    };

    /*
     * Texto escrito por el usuario (se valida al interpretarlo).
     */
    const setHeightInput = (value: string) => {
        heightInput.value = value;
    };

    const touch = () => {
        touched.value = true;
    };

    const useFileSize = () => {
        if (original.value) {
            setHeight(original.value.height);
        }
    };

    /*
     * Generar el archivo escalado antes de enviar el formulario.
     * Devuelve los campos a enviar ({} si el modelo no cambió); lanza un
     * error (y lo muestra en el panel) si no se puede procesar.
     */
    const process = async (): Promise<ModelFormFields> => {
        touched.value = true;
        error.value = null;

        if (!needsProcessing.value) {
            return {};
        }

        if (heightError.value || height.value === null) {
            const message = heightError.value ?? 'Ingresa el alto del modelo en metros.';

            error.value = message;

            throw new Error(message);
        }

        try {
            const buffer = file.value
                ? await file.value.arrayBuffer()
                : await fetchOriginal();

            const { resizeGlb } = await import('@/lib/glb/resizeGlb');
            const result = await resizeGlb(buffer, height.value, {
                supportedExtensions: config.supportedExtensions,
                textures: config.textures,
            });

            const name = (file.value?.name ?? 'modelo.glb').replace(/\.glb$/i, '');

            return {
                ...(file.value ? { model_original_file: file.value } : {}),
                model_file: new File([result.glb as BlobPart], `${name}-escalado.glb`, {
                    type: 'model/gltf-binary',
                }),
                model_target_height: height.value,
                model_original_height: result.originalHeight,
                model_final_height: result.finalHeight,
                model_final_width: result.width,
                model_final_depth: result.depth,
            };
        } catch (exception) {
            error.value = errorMessage(exception);

            throw exception;
        }
    };

    return reactive({
        config,
        heightInput,
        height,
        storedHeight,
        hasModel,
        isRequired,
        inspecting,
        error,
        heightError,
        original,
        final,
        factor,
        warnings,
        sizeChanged,
        needsProcessing,
        isNewFile: computed(() => Boolean(file.value)),
        estimating,
        fileSizes,
        setHeight,
        setHeightInput,
        touch,
        useFileSize,
        inspectStoredOriginal,
        process,
    });
}

export type CardModelSize = ReturnType<typeof useCardModelSize>;
