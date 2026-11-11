<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImageDown, RefreshCw, Server, Sparkles } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onMounted, ref } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import { CARD_IMAGE_PROFILES, encodeWebp } from '@/lib/imageOptimize';
import type { CardImageKind } from '@/lib/imageOptimize';
import admin from '@/routes/admin';

/*
 * Convierte a WebP las imágenes ya guardadas, una a la vez. Si el servidor
 * tiene Imagick o GD, codifica él; si no, el navegador. Cada original se
 * borra en cuanto su reemplazo queda guardado.
 */

interface PendingImage {
    card_id: number;
    card_name: string;
    image: CardImageKind;
    path: string;
    url: string;
    bytes: number;
    width: number | null;
    height: number | null;
}

type ItemStatus = 'pending' | 'working' | 'done' | 'skipped';

const IMAGE_LABELS: Record<CardImageKind, string> = {
    generated: 'Tarjeta generada',
    thumbnail: 'Miniatura',
    artwork: 'Ilustración',
};

const loading = ref(false);
const running = ref(false);
const loadError = ref<string | null>(null);
const serverEncoder = ref<string | null>(null);
const items = ref<PendingImage[]>([]);
const status = ref<Record<string, ItemStatus>>({});
const messages = ref<Record<string, string>>({});
const savedBytes = ref(0);

const key = (item: PendingImage) => `${item.card_id}:${item.image}`;

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
        const response = await fetch(admin.cards.images.pending().url, {
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
    } catch {
        loadError.value = 'No se pudo cargar la lista de imágenes.';
    } finally {
        loading.value = false;
    }
};

/**
 * Archivo WebP del navegador, o null para que codifique el servidor.
 */
const browserFile = async (item: PendingImage): Promise<File | null> => {
    const response = await fetch(item.url);

    if (!response.ok) {
        throw new Error('No se pudo descargar la imagen.');
    }

    const file = await encodeWebp(
        await response.blob(),
        CARD_IMAGE_PROFILES[item.image],
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

const optimize = async (item: PendingImage) => {
    const body = new FormData();

    body.append('expected_path', item.path);

    if (!serverEncoder.value) {
        const file = await browserFile(item);

        if (file) {
            body.append('file', file);
        }
    }

    const response = await fetch(
        admin.cards.images.optimize({ card: item.card_id, image: item.image })
            .url,
        {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body,
        },
    );

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
    router.reload({ only: ['cards'] });
};

onMounted(load);
</script>

<template>
    <AdminCard
        title="Optimizar imágenes"
        description="Convierte a WebP las imágenes de las tarjetas guardadas antes de la compresión automática."
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
                        <span class="font-semibold">{{ item.card_name }}</span>
                        · {{ IMAGE_LABELS[item.image] }}
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
