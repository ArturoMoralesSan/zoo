<script setup lang="ts" generic="T extends PendingImage">
import { router } from '@inertiajs/vue3';
import { ImageDown, RefreshCw, Server, Sparkles } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, ref, shallowRef } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import { encodeWebp } from '@/lib/imageOptimize';
import type { ImageProfile } from '@/lib/imageOptimize';

/*
 * Convierte a WebP las imágenes ya guardadas, una a la vez. Si el servidor
 * tiene Imagick o GD, codifica él; si no, el navegador. Cada original se
 * borra en cuanto su reemplazo queda guardado. Lo usan tarjetas y especies;
 * cada una da sus rutas, etiquetas y perfiles.
 */

export interface PendingImage {
    image: string;
    path: string;
    url: string;
    bytes: number;
    width: number | null;
    height: number | null;
}

const props = withDefaults(
    defineProps<{
        description: string;
        pendingUrl: string;
        optimizeUrl: (item: T) => string;
        itemKey: (item: T) => string;
        itemName: (item: T) => string;
        labels: Record<string, string>;
        profiles: Record<string, ImageProfile>;
        // Props de la página que se recargan al terminar.
        reloadOnly: string[];
        // No mostrar nada si no hay imágenes pendientes.
        hideWhenEmpty?: boolean;
    }>(),
    { hideWhenEmpty: false },
);

type ItemStatus = 'pending' | 'working' | 'done' | 'skipped';

const loading = ref(false);
const running = ref(false);
const loadError = ref<string | null>(null);
const serverEncoder = ref<string | null>(null);
const items = shallowRef<T[]>([]);
const loaded = ref(false);
const status = ref<Record<string, ItemStatus>>({});
const messages = ref<Record<string, string>>({});
const savedBytes = ref(0);

const key = (item: T) => props.itemKey(item);

const totalBytes = computed(() =>
    items.value.reduce((sum, item) => sum + item.bytes, 0),
);

const processed = computed(
    () =>
        Object.values(status.value).filter(
            (value) => value === 'done' || value === 'skipped',
        ).length,
);

const megabytes = (bytes: number) => `${(bytes / 1048576).toFixed(2)} MB`;

const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const load = async () => {
    loading.value = true;
    loadError.value = null;

    try {
        const response = await fetch(props.pendingUrl, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const body = await response.json();

        serverEncoder.value = body.data.server_encoder;
        items.value = body.data.images;
        status.value = {};
        messages.value = {};
        loaded.value = true;
    } catch {
        loadError.value = 'No se pudo cargar la lista de imágenes.';
    } finally {
        loading.value = false;
    }
};

/**
 * Archivo WebP del navegador, o null para que codifique el servidor.
 */
const browserFile = async (item: T): Promise<File | null> => {
    const response = await fetch(item.url);

    if (!response.ok) {
        throw new Error('No se pudo descargar la imagen.');
    }

    const file = await encodeWebp(
        await response.blob(),
        props.profiles[item.image],
        item.path.split('/').pop() ?? 'imagen',
    );

    if (!file) {
        throw new Error('Este navegador no puede crear WebP.');
    }

    if (file.size >= item.bytes) {
        throw new Error('El WebP no pesa menos que el original.');
    }

    return file;
};

const optimize = async (item: T) => {
    const body = new FormData();

    body.append('expected_path', item.path);

    if (!serverEncoder.value) {
        const file = await browserFile(item);

        if (file) {
            body.append('file', file);
        }
    }

    const response = await fetch(props.optimizeUrl(item), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body,
    });

    const result = await response.json().catch(() => null);

    if (!response.ok) {
        throw new Error(
            result?.message ??
                (response.status === 419
                    ? 'Tu sesión expiró. Recarga la página.'
                    : 'No se pudo optimizar.'),
        );
    }

    return result.data as { original_bytes: number; optimized_bytes: number };
};

const run = async () => {
    const confirmation = await Swal.fire({
        title: '¿Optimizar las imágenes?',
        text: `Se convertirán ${items.value.length} imágenes a WebP y se borrarán los originales. No se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, optimizar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#187744',
    });

    if (!confirmation.isConfirmed) {
        return;
    }

    running.value = true;
    savedBytes.value = 0;

    // Una a la vez: cada imagen grande ocupa bastante memoria al codificarse.
    for (const item of items.value) {
        const id = key(item);

        if (status.value[id] === 'done') {
            continue;
        }

        status.value[id] = 'working';

        try {
            const result = await optimize(item);

            savedBytes.value += result.original_bytes - result.optimized_bytes;
            status.value[id] = 'done';
            messages.value[id] =
                `${megabytes(result.original_bytes)} → ${megabytes(result.optimized_bytes)}`;
        } catch (error) {
            status.value[id] = 'skipped';
            messages.value[id] =
                error instanceof Error
                    ? error.message
                    : 'No se pudo optimizar.';
        }
    }

    running.value = false;

    // Las miniaturas de la lista apuntan a los archivos nuevos.
    router.reload({ only: props.reloadOnly });
};

onMounted(load);
</script>

<template>
    <AdminCard
        v-if="!hideWhenEmpty || !loaded || items.length || loadError"
        title="Optimizar imágenes"
        :description="description"
        :icon="ImageDown"
    >
        <div class="space-y-4">
            <div
                class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-[#435d4c]"
            >
                <span class="inline-flex items-center gap-1.5">
                    <Server :size="16" />
                    {{
                        serverEncoder
                            ? `Codifica el servidor (${serverEncoder})`
                            : 'Codifica este navegador'
                    }}
                </span>

                <span v-if="items.length">
                    {{ items.length }} pendientes · {{ megabytes(totalBytes) }}
                </span>

                <span
                    v-if="savedBytes > 0"
                    class="font-semibold text-[#187744]"
                >
                    Ahorro: {{ megabytes(savedBytes) }}
                </span>
            </div>

            <p v-if="loadError" class="form-error">{{ loadError }}</p>

            <p
                v-else-if="!loading && !items.length"
                class="text-sm text-[#718477]"
            >
                Todas las imágenes ya están optimizadas.
            </p>

            <ul
                v-if="items.length"
                class="max-h-80 divide-y divide-[#E5ECE7] overflow-y-auto rounded-xl border border-[#c8dccf]"
            >
                <li
                    v-for="item in items"
                    :key="key(item)"
                    class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm"
                >
                    <span class="min-w-0 text-[#244334]">
                        <span class="font-semibold">{{ itemName(item) }}</span>
                        · {{ labels[item.image] ?? item.image }}
                        <span class="text-[#718477]">
                            ({{ megabytes(item.bytes)
                            }}<template v-if="item.width">
                                , {{ item.width }}×{{ item.height }}</template
                            >)
                        </span>
                    </span>

                    <span
                        class="text-xs"
                        :class="{
                            'text-[#718477]': !status[key(item)],
                            'text-[#187744]': status[key(item)] === 'done',
                            'text-[#b45309]': status[key(item)] === 'skipped',
                        }"
                    >
                        <template v-if="status[key(item)] === 'working'">
                            Optimizando…
                        </template>
                        <template v-else>
                            {{ messages[key(item)] ?? 'Pendiente' }}
                        </template>
                    </span>
                </li>
            </ul>

            <div class="flex flex-wrap justify-end gap-2">
                <AdminButton
                    variant="secondary"
                    :icon="RefreshCw"
                    :loading="loading"
                    :disabled="running"
                    @click="load"
                >
                    Actualizar
                </AdminButton>

                <AdminButton
                    :icon="Sparkles"
                    :loading="running"
                    :loading-text="`Optimizando ${processed + 1} de ${items.length}…`"
                    :disabled="loading || !items.length"
                    @click="run"
                >
                    Optimizar imágenes
                </AdminButton>
            </div>
        </div>
    </AdminCard>
</template>
