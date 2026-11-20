<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Download,
    FileArchive,
    History,
    Image,
    KeyRound,
    LoaderCircle,
    PackageOpen,
    PackagePlus,
    QrCode,
    RefreshCw,
    Settings2,
    ShieldAlert,
    Tag,
    Trash2,
    TriangleAlert,
    Upload,
    Copyright,
    HelpCircle,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import { getRarityLabel } from '@/lib/cardRarity';
import admin from '@/routes/admin';
import type {
    CardImportMode,
    CardTransfer,
    CardTransferState,
    CardTransferStatus,
} from '@/types/cardTransfer';

/*
 * Pestaña "Importar / Exportar": paquetes .zip con todas las tarjetas.
 * El servidor trabaja en la cola; aquí se consulta el estado cada 2 s
 * mientras algo está en curso.
 */

const POLL_MS = 2000;

const state = ref<CardTransferState | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);

// Exportar
const includeQrTokens = ref(false);
const exporting = ref(false);

// Importar
const activeImportId = ref<number | null>(null);
const dragging = ref(false);
const uploadProgress = ref<number | null>(null);
const uploadError = ref<string | null>(null);
const mode = ref<CardImportMode>('skip');
const replaceSettings = ref(false);
const speciesChoice = ref<Record<string, string>>({});
const confirming = ref(false);
const showCards = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

let pollTimer: ReturnType<typeof setTimeout> | null = null;

const RUNNING: CardTransferStatus[] = ['pending', 'analyzing', 'processing'];

const isRunning = (transfer: CardTransfer | null | undefined) =>
    !!transfer && RUNNING.includes(transfer.status);

// Sin un `queue:work` corriendo, los trabajos se quedan en "pending" para siempre.
const QUEUE_STALL_MS = 15000;

const QUEUE_STALL_MESSAGE =
    'Lleva un rato en cola y el servidor no lo ha empezado. ¿Está corriendo «php artisan queue:work»? En cuanto arranque, seguirá solo.';

const now = ref(Date.now());

const isStalled = (transfer: CardTransfer | null | undefined) => {
    const since = transfer?.updated_at ?? transfer?.created_at;

    return (
        transfer?.status === 'pending' &&
        !!since &&
        now.value - Date.parse(since) > QUEUE_STALL_MS
    );
};

const megabytes = (bytes: number | null | undefined) =>
    `${((bytes ?? 0) / 1048576).toFixed(bytes && bytes < 1048576 ? 2 : 1)} MB`;

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('es-MX', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const available = computed(() => state.value?.available ?? true);

const transfers = computed(() => state.value?.transfers ?? []);

const latestExport = computed(() =>
    transfers.value.find((transfer) => transfer.type === 'export'),
);

const activeImport = computed(() =>
    activeImportId.value === null
        ? null
        : (transfers.value.find(
              (transfer) => transfer.id === activeImportId.value,
          ) ?? null),
);

const preview = computed(() => activeImport.value?.summary?.preview ?? null);

const result = computed(() => activeImport.value?.summary?.result ?? null);

const progress = computed(() => activeImport.value?.summary?.progress ?? null);

const STATUS_LABELS: Record<CardTransferStatus, string> = {
    pending: 'En cola',
    analyzing: 'Revisando',
    awaiting_confirmation: 'Por confirmar',
    processing: 'En curso',
    completed: 'Listo',
    failed: 'Con error',
};

const STATUS_CLASSES: Record<CardTransferStatus, string> = {
    pending: 'border-[#dce7df] bg-[#f2f7f4] text-[#435d4c]',
    analyzing: 'border-sky-200 bg-sky-50 text-sky-700',
    awaiting_confirmation: 'border-amber-200 bg-amber-50 text-amber-700',
    processing: 'border-sky-200 bg-sky-50 text-sky-700',
    completed: 'border-[#bfe0cb] bg-[#e2f3e8] text-[#187744]',
    failed: 'border-red-200 bg-red-50 text-red-700',
};

const INCLUDED = [
    { icon: Tag, text: 'Datos, diseño y estadísticas de cada tarjeta' },
    { icon: HelpCircle, text: 'Preguntas y respuestas de los duelos' },
    { icon: QrCode, text: 'Códigos QR (etiqueta, estado y vigencia)' },
    { icon: Copyright, text: 'Créditos y licencias de cada recurso' },
    { icon: Settings2, text: 'Configuración por rareza y mensajes de duelo' },
    { icon: Image, text: 'Imágenes y modelos 3D (original y escalado)' },
];

/*
 * ---------------------------------------------------------------------
 * Estado
 * ---------------------------------------------------------------------
 */

const schedulePoll = () => {
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    if (transfers.value.some(isRunning)) {
        pollTimer = setTimeout(() => load(true), POLL_MS);
    }
};

const load = async (silent = false) => {
    if (!silent) {
        loading.value = true;
    }

    loadError.value = null;

    try {
        const response = await fetch(admin.cards.transfers.index().url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        state.value = (await response.json()).data as CardTransferState;
        now.value = Date.now();

        // Retoma una importación que quedó a medias (otra pestaña, recarga…).
        if (activeImportId.value === null) {
            const pending = state.value.transfers.find(
                (transfer) =>
                    transfer.type === 'import' &&
                    (isRunning(transfer) ||
                        transfer.status === 'awaiting_confirmation'),
            );

            activeImportId.value = pending?.id ?? null;
        }
    } catch {
        loadError.value = 'No se pudo cargar el estado de los paquetes.';
    } finally {
        loading.value = false;
        schedulePoll();
    }
};

const upsert = (transfer: CardTransfer) => {
    if (!state.value) {
        return;
    }

    const others = state.value.transfers.filter(
        (item) => item.id !== transfer.id,
    );

    state.value.transfers = [transfer, ...others].sort((a, b) => b.id - a.id);
    schedulePoll();
};

const request = async (
    url: string,
    method: 'POST' | 'DELETE',
    body?: object,
): Promise<CardTransfer | null> => {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        throw new Error(errorMessage(response.status, data));
    }

    return data?.data ?? null;
};

const errorMessage = (
    status: number,
    data: {
        message?: string;
        errors?: Record<string, string[]>;
    } | null,
) => {
    const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;

    if (firstError) {
        return firstError;
    }

    if (status === 419) {
        return 'Tu sesión expiró. Recarga la página.';
    }

    if (status === 413) {
        return 'El archivo pesa más de lo que permite el servidor.';
    }

    if (status === 429) {
        return 'Demasiados intentos. Espera un minuto.';
    }

    return data?.message || 'Algo salió mal. Inténtalo de nuevo.';
};

const toastError = (error: unknown) =>
    Swal.fire({
        icon: 'error',
        title: 'No se pudo completar',
        text: error instanceof Error ? error.message : String(error),
        confirmButtonColor: '#187744',
    });

/*
 * ---------------------------------------------------------------------
 * Exportar
 * ---------------------------------------------------------------------
 */

const startExport = async () => {
    exporting.value = true;

    try {
        const transfer = await request(
            admin.cards.transfers.export().url,
            'POST',
            { include_qr_tokens: includeQrTokens.value },
        );

        if (transfer) {
            upsert(transfer);
        }
    } catch (error) {
        toastError(error);
    } finally {
        exporting.value = false;
    }
};

const downloadUrl = (transfer: CardTransfer) =>
    admin.cards.transfers.download(transfer.id).url;

/*
 * ---------------------------------------------------------------------
 * Importar
 * ---------------------------------------------------------------------
 */

const maxUpload = computed(() => state.value?.max_upload_bytes ?? 0);

const chooseFile = () => fileInput.value?.click();

const onFileInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (file) {
        upload(file);
    }
};

const onDrop = (event: DragEvent) => {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (file) {
        upload(file);
    }
};

const upload = (file: File) => {
    uploadError.value = null;

    if (!file.name.toLowerCase().endsWith('.zip')) {
        uploadError.value = 'Elige un archivo .zip exportado desde ZooDeck.';

        return;
    }

    if (maxUpload.value && file.size > maxUpload.value) {
        uploadError.value = `El archivo pesa ${megabytes(file.size)} y el servidor acepta hasta ${megabytes(maxUpload.value)}. Usa el comando "php artisan cards:import" en el servidor.`;

        return;
    }

    // XMLHttpRequest para mostrar el avance de la subida.
    const body = new FormData();
    body.append('package', file);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', admin.cards.transfers.import().url);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());

    uploadProgress.value = 0;

    xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
            uploadProgress.value = Math.round(
                (event.loaded / event.total) * 100,
            );
        }
    };

    xhr.onload = () => {
        uploadProgress.value = null;

        let data = null;

        try {
            data = JSON.parse(xhr.responseText);
        } catch {
            // Respuesta vacía o HTML.
        }

        if (xhr.status >= 200 && xhr.status < 300 && data?.data) {
            resetImportOptions();
            activeImportId.value = data.data.id;
            upsert(data.data);
        } else {
            uploadError.value = errorMessage(xhr.status, data);
        }
    };

    xhr.onerror = () => {
        uploadProgress.value = null;
        uploadError.value =
            'Se perdió la conexión al subir el paquete. Inténtalo de nuevo.';
    };

    xhr.send(body);
};

const resetImportOptions = () => {
    mode.value = 'skip';
    replaceSettings.value = false;
    speciesChoice.value = {};
    showCards.value = false;
};

const finishImport = () => {
    activeImportId.value = null;
    uploadError.value = null;
    resetImportOptions();
};

// Tarjetas que de verdad se importarán con las opciones elegidas.
const importableCount = computed(() => {
    if (!preview.value) {
        return 0;
    }

    return preview.value.cards.filter((card) => {
        if (card.status === 'invalid') {
            return false;
        }

        if (card.status === 'existing' && mode.value === 'skip') {
            return false;
        }

        return (
            card.species.match !== null ||
            !!speciesChoice.value[card.species.key]
        );
    }).length;
});

const hasWarnings = computed(
    () =>
        !!preview.value &&
        (preview.value.licenses.missing.length > 0 ||
            preview.value.licenses.unknown.length > 0 ||
            preview.value.missing_point_rules.length > 0 ||
            preview.value.qr_token_conflicts > 0 ||
            preview.value.counts.invalid > 0),
);

const confirmImport = async () => {
    if (!activeImport.value) {
        return;
    }

    if (mode.value === 'overwrite' || replaceSettings.value) {
        const changes = [
            mode.value === 'overwrite'
                ? `se sobrescribirán ${preview.value?.counts.existing ?? 0} tarjetas existentes`
                : null,
            replaceSettings.value
                ? 'se reemplazará la configuración por rareza y los mensajes de duelo'
                : null,
        ].filter(Boolean);

        const confirmation = await Swal.fire({
            title: '¿Importar el paquete?',
            text: `Además de crear las tarjetas nuevas, ${changes.join(' y ')}. No se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, importar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#187744',
        });

        if (!confirmation.isConfirmed) {
            return;
        }
    }

    confirming.value = true;

    try {
        const transfer = await request(
            admin.cards.transfers.confirm(activeImport.value.id).url,
            'POST',
            {
                mode: mode.value,
                replace_settings: replaceSettings.value,
                species_map: (preview.value?.missing_species ?? []).map(
                    (species) => ({
                        key: species.key,
                        species_id: speciesChoice.value[species.key]
                            ? Number(speciesChoice.value[species.key])
                            : null,
                    }),
                ),
            },
        );

        if (transfer) {
            upsert(transfer);
        }
    } catch (error) {
        toastError(error);
    } finally {
        confirming.value = false;
    }
};

const cancelImport = async () => {
    if (!activeImport.value) {
        return;
    }

    try {
        await request(
            admin.cards.transfers.destroy(activeImport.value.id).url,
            'DELETE',
        );

        if (state.value) {
            state.value.transfers = state.value.transfers.filter(
                (transfer) => transfer.id !== activeImportId.value,
            );
        }

        finishImport();
    } catch (error) {
        toastError(error);
    }
};

// Al terminar una importación, la lista de tarjetas se actualiza.
watch(
    () => activeImport.value?.status,
    (status, previous) => {
        if (status === 'completed' && previous && previous !== 'completed') {
            router.reload({ only: ['cards'] });
        }
    },
);

/*
 * ---------------------------------------------------------------------
 * Historial
 * ---------------------------------------------------------------------
 */

const removeTransfer = async (transfer: CardTransfer) => {
    const confirmation = await Swal.fire({
        title: '¿Borrar el paquete?',
        text: `Se borrará «${transfer.original_name ?? 'paquete'}» del servidor.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#b91c1c',
    });

    if (!confirmation.isConfirmed) {
        return;
    }

    try {
        await request(admin.cards.transfers.destroy(transfer.id).url, 'DELETE');

        if (state.value) {
            state.value.transfers = state.value.transfers.filter(
                (item) => item.id !== transfer.id,
            );
        }

        if (activeImportId.value === transfer.id) {
            finishImport();
        }
    } catch (error) {
        toastError(error);
    }
};

const transferSummary = (transfer: CardTransfer) => {
    const summary = transfer.summary;

    if (transfer.status === 'failed') {
        return transfer.error ?? 'Error';
    }

    if (transfer.type === 'export' && transfer.status === 'completed') {
        return `${summary?.cards ?? 0} tarjetas · ${summary?.files ?? 0} archivos`;
    }

    if (transfer.type === 'import' && summary?.result) {
        return `${summary.result.created} creadas · ${summary.result.updated} actualizadas · ${summary.result.skipped.length} omitidas`;
    }

    if (transfer.type === 'import' && summary?.preview) {
        return `${summary.preview.counts.total} tarjetas en el paquete`;
    }

    return '';
};

onMounted(() => load());

onBeforeUnmount(() => {
    if (pollTimer) {
        clearTimeout(pollTimer);
    }
});
</script>

<template>
    <div class="space-y-4">
        <!-- Sin la extensión zip -->
        <div
            v-if="state && !available"
            role="alert"
            class="flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 sm:p-5"
        >
            <ShieldAlert :size="22" class="mt-0.5 shrink-0" />
            <div>
                <p class="font-semibold">
                    Importar y exportar no está disponible
                </p>
                <p class="mt-1">{{ state.unavailable_message }}</p>
            </div>
        </div>

        <p v-if="loadError" class="form-error">
            {{ loadError }}
            <button
                type="button"
                class="ml-2 font-semibold underline"
                @click="load()"
            >
                Reintentar
            </button>
        </p>

        <div
            v-if="!state && loading"
            class="flex items-center gap-2 rounded-2xl border border-[#dce7df] bg-white p-6 text-sm text-[#718477]"
        >
            <LoaderCircle :size="18" class="animate-spin" />
            Cargando…
        </div>

        <div v-if="state" class="grid gap-4 xl:grid-cols-2">
            <!-- ============ Exportar ============ -->
            <AdminCard
                v-if="state.can_export"
                title="Exportar tarjetas"
                description="Descarga un .zip con todas las tarjetas para respaldarlas o llevarlas a otro servidor."
                :icon="PackagePlus"
            >
                <div class="space-y-4">
                    <ul
                        class="grid gap-2 text-sm text-[#435d4c] sm:grid-cols-2"
                    >
                        <li
                            v-for="item in INCLUDED"
                            :key="item.text"
                            class="flex items-start gap-2"
                        >
                            <component
                                :is="item.icon"
                                :size="16"
                                class="mt-0.5 shrink-0 text-[#187744]"
                            />
                            {{ item.text }}
                        </li>
                    </ul>

                    <p class="text-xs text-[#718477]">
                        No incluye las tarjetas obtenidas por los visitantes ni
                        sus intentos. Las especies deben existir en el servidor
                        donde se importe.
                    </p>

                    <label class="form-checkbox-row cursor-pointer">
                        <input
                            v-model="includeQrTokens"
                            type="checkbox"
                            class="form-checkbox"
                            :disabled="!available"
                        />
                        <span>
                            <span
                                class="form-checkbox-title flex items-center gap-1.5"
                            >
                                <KeyRound :size="14" />
                                Incluir tokens de QR
                            </span>
                            <span class="form-checkbox-description block">
                                Los QR impresos seguirán funcionando después de
                                importar.
                                <strong
                                    v-if="includeQrTokens"
                                    class="text-amber-700"
                                >
                                    Cualquiera con el archivo podría iniciar
                                    duelos: guárdalo en un lugar seguro.
                                </strong>
                            </span>
                        </span>
                    </label>

                    <!-- Último paquete -->
                    <div
                        v-if="latestExport"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#dce7df] bg-[#fafcfa] px-4 py-3 text-sm"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <LoaderCircle
                                v-if="isRunning(latestExport)"
                                :size="18"
                                class="shrink-0 animate-spin text-sky-600"
                            />
                            <CircleCheck
                                v-else-if="latestExport.status === 'completed'"
                                :size="18"
                                class="shrink-0 text-[#187744]"
                            />
                            <CircleX
                                v-else
                                :size="18"
                                class="shrink-0 text-red-600"
                            />

                            <div class="min-w-0">
                                <p class="font-semibold text-[#244334]">
                                    <template
                                        v-if="latestExport.status === 'pending'"
                                    >
                                        En cola…
                                    </template>
                                    <template
                                        v-else-if="isRunning(latestExport)"
                                    >
                                        Generando paquete…
                                    </template>
                                    <template
                                        v-else-if="
                                            latestExport.status === 'completed'
                                        "
                                    >
                                        Paquete listo ·
                                        {{ megabytes(latestExport.file_size) }}
                                    </template>
                                    <template v-else>
                                        No se pudo generar el paquete
                                    </template>
                                </p>
                                <p class="truncate text-xs text-[#718477]">
                                    {{ transferSummary(latestExport) }} ·
                                    {{ formatDate(latestExport.created_at) }}
                                </p>
                                <p
                                    v-if="
                                        latestExport.summary?.missing_files
                                            ?.length
                                    "
                                    class="text-xs text-amber-700"
                                >
                                    Faltaron
                                    {{
                                        latestExport.summary.missing_files
                                            .length
                                    }}
                                    archivos que no estaban en el servidor.
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="isStalled(latestExport)"
                            role="alert"
                            class="w-full text-xs text-amber-700"
                        >
                            {{ QUEUE_STALL_MESSAGE }}
                        </p>

                        <a
                            v-if="latestExport.can_download"
                            :href="downloadUrl(latestExport)"
                            class="inline-flex min-h-10 items-center gap-2 rounded-lg border-b-[3px] border-b-[#092f1a] bg-[#187642] px-4 py-2 text-sm font-semibold text-white hover:bg-[#126236]"
                        >
                            <Download :size="16" />
                            Descargar .zip
                        </a>
                    </div>

                    <div class="flex justify-end">
                        <AdminButton
                            :icon="FileArchive"
                            :loading="exporting || isRunning(latestExport)"
                            loading-text="Generando…"
                            :disabled="!available || state.card_count === 0"
                            @click="startExport"
                        >
                            Generar paquete ({{ state.card_count }} tarjetas)
                        </AdminButton>
                    </div>
                </div>
            </AdminCard>

            <!-- ============ Importar ============ -->
            <AdminCard
                v-if="state.can_import"
                title="Importar tarjetas"
                description="Sube un .zip exportado desde ZooDeck. Antes de importar verás qué se va a crear o actualizar."
                :icon="PackageOpen"
            >
                <!-- 1. Elegir archivo -->
                <div
                    v-if="!activeImport && uploadProgress === null"
                    class="space-y-3"
                >
                    <button
                        type="button"
                        class="flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            dragging
                                ? 'border-[#187744] bg-[#e2f3e8]'
                                : 'border-[#c8dccf] bg-[#fafcfa] hover:border-[#a9cbb5] hover:bg-[#f2f7f4]'
                        "
                        :disabled="!available"
                        @click="chooseFile"
                        @dragover.prevent="dragging = available"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="available && onDrop($event)"
                    >
                        <Upload :size="28" class="text-[#187744]" />
                        <span class="font-semibold text-[#244334]">
                            Arrastra el .zip aquí o haz clic para elegirlo
                        </span>
                        <span v-if="maxUpload" class="text-xs text-[#718477]">
                            Máximo {{ megabytes(maxUpload) }}
                        </span>
                    </button>

                    <input
                        ref="fileInput"
                        type="file"
                        accept=".zip,application/zip"
                        class="hidden"
                        @change="onFileInput"
                    />

                    <p v-if="uploadError" class="form-error">
                        {{ uploadError }}
                    </p>
                </div>

                <!-- 2. Subiendo -->
                <div v-else-if="uploadProgress !== null" class="space-y-2">
                    <p class="text-sm font-semibold text-[#244334]">
                        Subiendo paquete… {{ uploadProgress }} %
                    </p>
                    <div class="h-2 overflow-hidden rounded-full bg-[#e5ece7]">
                        <div
                            class="h-full rounded-full bg-[#187744] transition-all"
                            :style="{ width: `${uploadProgress}%` }"
                        />
                    </div>
                </div>

                <!-- 3. Revisando o importando -->
                <div
                    v-else-if="activeImport && isRunning(activeImport)"
                    class="space-y-3"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                    >
                        <LoaderCircle
                            :size="18"
                            class="animate-spin text-sky-600"
                        />
                        <template v-if="activeImport.status === 'processing'">
                            Importando tarjetas…
                            <span v-if="progress" class="font-normal">
                                {{ progress.done }} de {{ progress.total }}
                            </span>
                        </template>
                        <template
                            v-else-if="
                                activeImport.status === 'pending' &&
                                activeImport.options?.mode
                            "
                        >
                            Importación en cola…
                        </template>
                        <template v-else-if="activeImport.status === 'pending'">
                            «{{ activeImport.original_name }}» en cola para
                            revisarse…
                        </template>
                        <template v-else>
                            Revisando «{{ activeImport.original_name }}»…
                        </template>
                    </p>

                    <div
                        v-if="progress"
                        class="h-2 overflow-hidden rounded-full bg-[#e5ece7]"
                    >
                        <div
                            class="h-full rounded-full bg-[#187744] transition-all"
                            :style="{
                                width: `${Math.round((progress.done / Math.max(progress.total, 1)) * 100)}%`,
                            }"
                        />
                    </div>

                    <p
                        v-if="isStalled(activeImport)"
                        role="alert"
                        class="text-xs text-amber-700"
                    >
                        {{ QUEUE_STALL_MESSAGE }}
                    </p>
                    <p v-else class="text-xs text-[#718477]">
                        Puedes salir de esta pestaña; el servidor sigue
                        trabajando.
                    </p>
                </div>

                <!-- 4. Vista previa -->
                <div
                    v-else-if="
                        activeImport?.status === 'awaiting_confirmation' &&
                        preview
                    "
                    class="space-y-4"
                >
                    <div class="text-sm text-[#435d4c]">
                        <p class="font-semibold text-[#244334]">
                            {{ activeImport.original_name }}
                        </p>
                        <p class="text-xs text-[#718477]">
                            Exportado
                            {{ formatDate(preview.manifest.exported_at) }}
                            <template v-if="preview.manifest.source_url">
                                desde {{ preview.manifest.source_url }}
                            </template>
                            · {{ megabytes(preview.manifest.total_bytes) }}
                            <template v-if="preview.manifest.include_qr_tokens">
                                · incluye tokens de QR
                            </template>
                        </p>
                    </div>

                    <!-- Resumen -->
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div
                            class="rounded-xl border border-[#bfe0cb] bg-[#e2f3e8] p-3"
                        >
                            <p class="text-2xl font-bold text-[#187744]">
                                {{ preview.counts.new }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Nuevas</p>
                        </div>
                        <div
                            class="rounded-xl border border-[#dce7df] bg-[#f2f7f4] p-3"
                        >
                            <p class="text-2xl font-bold text-[#244334]">
                                {{ preview.counts.existing }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Ya existen</p>
                        </div>
                        <div
                            class="rounded-xl border p-3"
                            :class="
                                preview.counts.invalid
                                    ? 'border-red-200 bg-red-50'
                                    : 'border-[#dce7df] bg-[#f2f7f4]'
                            "
                        >
                            <p
                                class="text-2xl font-bold"
                                :class="
                                    preview.counts.invalid
                                        ? 'text-red-700'
                                        : 'text-[#244334]'
                                "
                            >
                                {{ preview.counts.invalid }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Con errores</p>
                        </div>
                    </div>

                    <!-- Especies que faltan -->
                    <div
                        v-if="preview.missing_species.length"
                        class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4"
                    >
                        <p
                            class="flex items-center gap-2 text-sm font-semibold text-amber-800"
                        >
                            <TriangleAlert :size="16" />
                            Especies que no existen en este servidor
                        </p>
                        <p class="text-xs text-amber-800">
                            Elige a qué especie asignar sus tarjetas o déjalas
                            fuera de la importación.
                        </p>

                        <div
                            v-for="species in preview.missing_species"
                            :key="species.key"
                            class="grid gap-2 sm:grid-cols-[1fr_minmax(0,16rem)] sm:items-center"
                        >
                            <div class="text-sm text-[#244334]">
                                <span class="font-semibold">
                                    {{
                                        species.common_name ??
                                        species.scientific_name ??
                                        species.slug
                                    }}
                                </span>
                                <span
                                    v-if="species.scientific_name"
                                    class="text-[#718477] italic"
                                >
                                    ({{ species.scientific_name }})
                                </span>
                                <span class="block text-xs text-[#718477]">
                                    {{ species.cards }}
                                    {{
                                        species.cards === 1
                                            ? 'tarjeta'
                                            : 'tarjetas'
                                    }}
                                </span>
                            </div>

                            <select
                                v-model="speciesChoice[species.key]"
                                class="form-select"
                                :aria-label="`Especie para ${species.common_name ?? species.key}`"
                            >
                                <option value="">Omitir sus tarjetas</option>
                                <option
                                    v-for="option in state.species"
                                    :key="option.id"
                                    :value="String(option.id)"
                                >
                                    {{ option.common_name }} ({{
                                        option.scientific_name
                                    }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Avisos -->
                    <ul
                        v-if="hasWarnings"
                        class="space-y-1.5 rounded-xl border border-[#dce7df] bg-[#fafcfa] p-4 text-xs text-[#435d4c]"
                    >
                        <li
                            v-if="preview.counts.invalid"
                            class="flex gap-2 text-red-700"
                        >
                            <CircleX :size="14" class="mt-0.5 shrink-0" />
                            {{ preview.counts.invalid }} tarjetas tienen datos
                            inválidos y se omitirán (ver la lista).
                        </li>
                        <li
                            v-if="preview.licenses.missing.length"
                            class="flex gap-2"
                        >
                            <TriangleAlert
                                :size="14"
                                class="mt-0.5 shrink-0 text-amber-600"
                            />
                            Se crearán las licencias
                            {{ preview.licenses.missing.join(', ') }}
                            desactivadas, para que alguien las revise.
                        </li>
                        <li
                            v-if="preview.licenses.unknown.length"
                            class="flex gap-2"
                        >
                            <TriangleAlert
                                :size="14"
                                class="mt-0.5 shrink-0 text-amber-600"
                            />
                            Algunos créditos quedarán sin licencia ({{
                                preview.licenses.unknown.join(', ')
                            }}).
                        </li>
                        <li
                            v-if="preview.missing_point_rules.length"
                            class="flex gap-2"
                        >
                            <TriangleAlert
                                :size="14"
                                class="mt-0.5 shrink-0 text-amber-600"
                            />
                            Si reemplazas la configuración, las rarezas con las
                            reglas {{ preview.missing_point_rules.join(', ') }}
                            quedarán sin puntos.
                        </li>
                        <li
                            v-if="preview.qr_token_conflicts"
                            class="flex gap-2"
                        >
                            <TriangleAlert
                                :size="14"
                                class="mt-0.5 shrink-0 text-amber-600"
                            />
                            {{ preview.qr_token_conflicts }} QR ya los usa otra
                            tarjeta: se crearán con un token nuevo.
                        </li>
                    </ul>

                    <!-- Tarjetas del paquete -->
                    <div>
                        <button
                            type="button"
                            class="text-sm font-semibold text-[#187744] hover:underline"
                            @click="showCards = !showCards"
                        >
                            {{ showCards ? 'Ocultar' : 'Ver' }} las
                            {{ preview.counts.total }} tarjetas del paquete
                        </button>

                        <ul
                            v-if="showCards"
                            class="mt-2 max-h-72 divide-y divide-[#e5ece7] overflow-y-auto rounded-xl border border-[#c8dccf]"
                        >
                            <li
                                v-for="card in preview.cards"
                                :key="card.uuid"
                                class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm"
                            >
                                <span class="min-w-0">
                                    <span class="font-semibold text-[#244334]">
                                        {{ card.name }}
                                    </span>
                                    <span class="text-xs text-[#718477]">
                                        ·
                                        {{ getRarityLabel(card.rarity ?? '') }}
                                        · {{ card.species.common_name }} ·
                                        {{ card.questions }} preguntas
                                    </span>
                                    <span
                                        v-if="card.errors.length"
                                        class="block text-xs text-red-700"
                                    >
                                        {{ card.errors[0] }}
                                    </span>
                                    <span
                                        v-else-if="
                                            card.species.match ===
                                            'scientific_name'
                                        "
                                        class="block text-xs text-amber-700"
                                    >
                                        Especie encontrada por nombre científico
                                    </span>
                                </span>
                                <span
                                    class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                    :class="{
                                        'border-[#bfe0cb] bg-[#e2f3e8] text-[#187744]':
                                            card.status === 'new',
                                        'border-[#dce7df] bg-[#f2f7f4] text-[#435d4c]':
                                            card.status === 'existing',
                                        'border-red-200 bg-red-50 text-red-700':
                                            card.status === 'invalid',
                                    }"
                                >
                                    {{
                                        {
                                            new: 'Nueva',
                                            existing: 'Ya existe',
                                            invalid: 'Inválida',
                                        }[card.status]
                                    }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Opciones -->
                    <fieldset v-if="preview.counts.existing" class="space-y-2">
                        <legend
                            class="mb-2 text-sm font-semibold text-[#244334]"
                        >
                            Tarjetas que ya existen
                        </legend>
                        <label class="form-checkbox-row cursor-pointer">
                            <input
                                v-model="mode"
                                type="radio"
                                value="skip"
                                class="form-checkbox"
                            />
                            <span>
                                <span class="form-checkbox-title block">
                                    Omitirlas
                                </span>
                                <span class="form-checkbox-description block">
                                    Solo se crean las nuevas; las existentes no
                                    cambian.
                                </span>
                            </span>
                        </label>
                        <label class="form-checkbox-row cursor-pointer">
                            <input
                                v-model="mode"
                                type="radio"
                                value="overwrite"
                                class="form-checkbox"
                            />
                            <span>
                                <span class="form-checkbox-title block">
                                    Sobrescribirlas
                                </span>
                                <span class="form-checkbox-description block">
                                    Sus datos, archivos y créditos se reemplazan
                                    por los del paquete. Las preguntas que no
                                    vengan se desactivan (no se borran).
                                </span>
                            </span>
                        </label>
                    </fieldset>

                    <label class="form-checkbox-row cursor-pointer">
                        <input
                            v-model="replaceSettings"
                            type="checkbox"
                            class="form-checkbox"
                        />
                        <span>
                            <span class="form-checkbox-title block">
                                Reemplazar la configuración global
                            </span>
                            <span class="form-checkbox-description block">
                                Usa la configuración por rareza y los mensajes
                                de duelo del paquete en lugar de los de este
                                servidor.
                            </span>
                        </span>
                    </label>

                    <div class="flex flex-wrap justify-end gap-2">
                        <AdminButton
                            variant="secondary"
                            :disabled="confirming"
                            @click="cancelImport"
                        >
                            Cancelar
                        </AdminButton>
                        <AdminButton
                            :icon="Upload"
                            :loading="confirming"
                            loading-text="Iniciando…"
                            :disabled="
                                !available ||
                                (importableCount === 0 && !replaceSettings)
                            "
                            @click="confirmImport"
                        >
                            Importar {{ importableCount }}
                            {{ importableCount === 1 ? 'tarjeta' : 'tarjetas' }}
                        </AdminButton>
                    </div>
                </div>

                <!-- 5. Terminado -->
                <div
                    v-else-if="activeImport?.status === 'completed' && result"
                    class="space-y-4"
                >
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-[#187744]"
                    >
                        <CircleCheck :size="18" />
                        Importación terminada
                    </p>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div
                            class="rounded-xl border border-[#bfe0cb] bg-[#e2f3e8] p-3"
                        >
                            <p class="text-2xl font-bold text-[#187744]">
                                {{ result.created }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Creadas</p>
                        </div>
                        <div
                            class="rounded-xl border border-[#dce7df] bg-[#f2f7f4] p-3"
                        >
                            <p class="text-2xl font-bold text-[#244334]">
                                {{ result.updated }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Actualizadas</p>
                        </div>
                        <div
                            class="rounded-xl border border-[#dce7df] bg-[#f2f7f4] p-3"
                        >
                            <p class="text-2xl font-bold text-[#244334]">
                                {{ result.skipped.length }}
                            </p>
                            <p class="text-xs text-[#435d4c]">Omitidas</p>
                        </div>
                    </div>

                    <ul
                        v-if="result.failed.length || result.skipped.length"
                        class="max-h-56 space-y-1 overflow-y-auto rounded-xl border border-[#dce7df] bg-[#fafcfa] p-4 text-xs"
                    >
                        <li
                            v-for="item in result.failed"
                            :key="`f-${item.name}`"
                            class="text-red-700"
                        >
                            <strong>{{ item.name }}:</strong> {{ item.message }}
                        </li>
                        <li
                            v-for="item in result.skipped"
                            :key="`s-${item.name}`"
                            class="text-[#435d4c]"
                        >
                            <strong>{{ item.name }}:</strong> {{ item.reason }}
                        </li>
                    </ul>

                    <p
                        v-if="result.settings_replaced"
                        class="text-xs text-[#435d4c]"
                    >
                        Se reemplazó la configuración por rareza y los mensajes
                        de duelo.
                    </p>
                    <p
                        v-if="result.licenses_created.length"
                        class="text-xs text-amber-700"
                    >
                        Licencias creadas desactivadas:
                        {{ result.licenses_created.join(', ') }}.
                    </p>
                    <p
                        v-if="result.qr_tokens_regenerated"
                        class="text-xs text-amber-700"
                    >
                        {{ result.qr_tokens_regenerated }} QR recibieron un
                        token nuevo: hay que volver a imprimirlos.
                    </p>

                    <div class="flex justify-end">
                        <AdminButton :icon="Upload" @click="finishImport">
                            Importar otro paquete
                        </AdminButton>
                    </div>
                </div>

                <!-- 6. Con error -->
                <div
                    v-else-if="activeImport?.status === 'failed'"
                    class="space-y-4"
                >
                    <div
                        role="alert"
                        class="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                    >
                        <CircleX :size="18" class="mt-0.5 shrink-0" />
                        <div>
                            <p class="font-semibold">
                                No se pudo importar «{{
                                    activeImport.original_name
                                }}»
                            </p>
                            <p class="mt-1">{{ activeImport.error }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <AdminButton :icon="Upload" @click="finishImport">
                            Elegir otro archivo
                        </AdminButton>
                    </div>
                </div>
            </AdminCard>
        </div>

        <!-- ============ Historial ============ -->
        <AdminCard
            v-if="state && transfers.length"
            title="Paquetes recientes"
            :description="`Los paquetes se borran del servidor después de 7 días.`"
            :icon="History"
            :padding="false"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th
                                v-for="column in [
                                    'Tipo',
                                    'Archivo',
                                    'Estado',
                                    'Fecha',
                                    'Acciones',
                                ]"
                                :key="column"
                                class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3 text-left text-xs font-bold tracking-wide text-[#435d4c] uppercase"
                            >
                                {{ column }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5ece7]">
                        <tr v-for="transfer in transfers" :key="transfer.id">
                            <td
                                class="px-5 py-3 whitespace-nowrap text-[#244334]"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <component
                                        :is="
                                            transfer.type === 'export'
                                                ? PackagePlus
                                                : PackageOpen
                                        "
                                        :size="16"
                                        class="text-[#187744]"
                                    />
                                    {{
                                        transfer.type === 'export'
                                            ? 'Exportación'
                                            : 'Importación'
                                    }}
                                </span>
                            </td>
                            <td class="max-w-xs px-5 py-3">
                                <p class="truncate font-medium text-[#244334]">
                                    {{ transfer.original_name ?? '—' }}
                                </p>
                                <p class="truncate text-xs text-[#718477]">
                                    {{ transferSummary(transfer) }}
                                </p>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                    :class="STATUS_CLASSES[transfer.status]"
                                >
                                    <LoaderCircle
                                        v-if="isRunning(transfer)"
                                        :size="12"
                                        class="animate-spin"
                                    />
                                    {{ STATUS_LABELS[transfer.status] }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3 whitespace-nowrap text-[#435d4c]"
                            >
                                {{ formatDate(transfer.created_at) }}
                                <span
                                    v-if="transfer.user"
                                    class="block text-xs text-[#718477]"
                                >
                                    {{ transfer.user }}
                                </span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <a
                                        v-if="transfer.can_download"
                                        :href="downloadUrl(transfer)"
                                        class="rounded-lg p-2 text-[#187744] hover:bg-[#e2f3e8]"
                                        title="Descargar"
                                        aria-label="Descargar"
                                    >
                                        <Download :size="16" />
                                    </a>
                                    <button
                                        v-if="
                                            !isRunning(transfer) &&
                                            (transfer.type === 'export'
                                                ? state.can_export
                                                : state.can_import)
                                        "
                                        type="button"
                                        class="rounded-lg p-2 text-red-700 hover:bg-red-50"
                                        title="Borrar"
                                        aria-label="Borrar"
                                        @click="removeTransfer(transfer)"
                                    >
                                        <Trash2 :size="16" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </AdminCard>

        <div v-if="state" class="flex justify-end">
            <AdminButton
                variant="secondary"
                :icon="RefreshCw"
                :loading="loading"
                @click="load()"
            >
                Actualizar
            </AdminButton>
        </div>
    </div>
</template>
