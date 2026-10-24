<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CheckCircle2,
    CircleOff,
    Download,
    FilterX,
    Plus,
    QrCode,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { ref } from 'vue';

import CardQrImage from '@/components/admin/cards/CardQrImage.vue';
import DataTable from '@/components/admin/DataTable.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import TableActions from '@/components/admin/TableActions.vue';
import {
    downloadQrPng,
    formatQrDate,
    isQrExpired,
    qrFilename,
} from '@/lib/cardQrCode';
import type { CardQrCode } from '@/lib/cardQrCode';
import admin from '@/routes/admin';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface QrCodesPagination {
    data: CardQrCode[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    card: { id: number; name: string };
    qrCodes: QrCodesPagination;
    filters: {
        search?: string;
        is_active?: string | null;
    };
}>();

const search = ref(props.filters?.search ?? '');
const isActive = ref(props.filters?.is_active ?? '');

const hasFilters = (): boolean => Boolean(search.value.trim() || isActive.value);

const applyFilters = (): void => {
    router.get(
        admin.cards.qrCodes.index(props.card.id).url,
        {
            search: search.value.trim() || undefined,
            is_active: isActive.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const clearFilters = (): void => {
    search.value = '';
    isActive.value = '';

    applyFilters();
};

const download = (qrCode: CardQrCode): Promise<void> =>
    downloadQrPng(qrCode.token, qrFilename(props.card.name, qrCode.id));

const deleteQrCode = async (qrCode: CardQrCode): Promise<void> => {
    const result = await Swal.fire({
        title: '¿Eliminar código QR?',
        text: 'Los QR impresos con este código dejarán de funcionar. Esta acción no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        focusCancel: true,
        confirmButtonColor: '#187744',
        cancelButtonColor: '#84958a',
    });

    if (!result.isConfirmed) {
        return;
    }

    router.delete(
        admin.cards.qrCodes.destroy({
            card: props.card.id,
            qrCode: qrCode.id,
        }).url,
        { preserveScroll: true },
    );
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Panel',
                href: admin.dashboard(),
            },
            {
                title: 'Tarjetas',
                href: admin.cards.index(),
            },
            {
                title: 'Códigos QR',
                href: admin.cards.index(),
            },
        ],
    },
});
</script>

<template>
    <Head :title="`Códigos QR: ${card.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
        <PageHeader
            :title="card.name"
            description="Cada código QR permite iniciar un duelo por esta tarjeta al escanearlo desde la app."
            section="Códigos QR de tarjeta"
            :icon="QrCode"
            :action-icon="Plus"
            action-label="Nuevo QR"
            :action-href="admin.cards.qrCodes.create(card.id).url"
        />

        <DataTable
            v-model:search="search"
            :pagination="qrCodes"
            :is-empty="qrCodes.data.length === 0"
            :empty-colspan="6"
            search-placeholder="Buscar por etiqueta..."
            :has-filters="hasFilters()"
            @search="applyFilters"
            @clear-filters="clearFilters"
        >
            <template #filters>
                <select
                    v-model="isActive"
                    aria-label="Filtrar por estado"
                    class="h-[42px] w-full min-w-0 rounded-lg border border-[#bfcfc6] bg-white px-3 text-sm text-[#244334] outline-none transition focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 sm:w-auto sm:min-w-[170px]"
                    @change="applyFilters"
                >
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </template>

            <template #active-filters>
                <span
                    v-if="search.trim()"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Búsqueda: "{{ search.trim() }}"
                </span>

                <span
                    v-if="isActive"
                    class="rounded-full border border-[#dce7df] bg-[#f2f7f4] px-3 py-1.5 text-xs text-[#244334]"
                >
                    Estado: {{ isActive === '1' ? 'Activos' : 'Inactivos' }}
                </span>
            </template>

            <template #table-head>
                <tr>
                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        QR
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Etiqueta
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Estado
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Vencimiento
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Escaneos
                    </th>

                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-right text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                        Acciones
                    </th>
                </tr>
            </template>

            <template #table-body>
                <tr
                    v-for="qrCode in qrCodes.data"
                    :key="qrCode.id"
                    class="transition-colors hover:bg-[#f7faf8]"
                >
                    <!-- QR -->
                    <td class="px-5 py-4 sm:px-6">
                        <CardQrImage
                            :token="qrCode.token"
                            :size="96"
                            :alt="`Código QR ${qrCode.label ?? qrCode.id}`"
                        />
                    </td>

                    <!-- Etiqueta -->
                    <td class="px-5 py-4 sm:px-6">
                        <div class="min-w-[160px]">
                            <div class="text-sm font-semibold text-[#244334]">
                                {{ qrCode.label || 'Sin etiqueta' }}
                            </div>

                            <div class="mt-1 text-xs text-[#84958a]">
                                ID {{ qrCode.id }} · Creado {{ formatQrDate(qrCode.created_at) }}
                            </div>
                        </div>
                    </td>

                    <!-- Estado -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="qrCode.is_active"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#c9e4d2] bg-[#dcefe2] px-3 py-1.5 text-xs font-semibold text-[#176b3d]"
                        >
                            <span class="h-2 w-2 rounded-full bg-[#187744]" />
                            Activo
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-full border border-[#e1e7e2] bg-[#f2f5f3] px-3 py-1.5 text-xs font-medium text-[#718477]"
                        >
                            <CircleOff :size="14" />
                            Inactivo
                        </span>
                    </td>

                    <!-- Vencimiento -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <span
                            v-if="!qrCode.expires_at"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#dce7df] bg-white px-2.5 py-1.5 text-xs font-medium text-[#435d4c]"
                        >
                            <CheckCircle2 :size="14" class="text-[#187744]" />
                            Sin vencimiento
                        </span>

                        <span
                            v-else-if="isQrExpired(qrCode)"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-semibold text-red-600"
                            :title="formatQrDate(qrCode.expires_at)"
                        >
                            <CalendarClock :size="14" />
                            Vencido · {{ formatQrDate(qrCode.expires_at) }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700"
                        >
                            <CalendarClock :size="14" />
                            {{ formatQrDate(qrCode.expires_at) }}
                        </span>
                    </td>

                    <!-- Escaneos -->
                    <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                        <div class="text-sm font-semibold text-[#244334]">
                            {{ qrCode.scans_count.toLocaleString('es-MX') }}
                        </div>

                        <div class="mt-1 text-xs text-[#84958a]">
                            {{
                                qrCode.last_scanned_at
                                    ? `Último: ${formatQrDate(qrCode.last_scanned_at)}`
                                    : 'Sin escaneos'
                            }}
                        </div>
                    </td>

                    <!-- Acciones -->
                    <TableActions
                        :edit-url="admin.cards.qrCodes.edit({
                            card: card.id,
                            qrCode: qrCode.id,
                        }).url"
                        :show-view="false"
                        item-name="código QR"
                        @delete="deleteQrCode(qrCode)"
                    >
                        <button
                            type="button"
                            title="Descargar PNG"
                            aria-label="Descargar PNG"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-[#52685a] transition hover:bg-[#e4f2e8] hover:text-[#187744] focus:outline-none focus:ring-2 focus:ring-[#187744]/15"
                            @click="download(qrCode)"
                        >
                            <Download :size="16" />
                            PNG
                        </button>
                    </TableActions>
                </tr>
            </template>

            <template #empty>
                <div class="mx-auto flex max-w-md flex-col items-center justify-center py-6 text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-[#edf4ef] text-[#187744]">
                        <FilterX v-if="hasFilters()" :size="27" />
                        <QrCode v-else :size="27" />
                    </div>

                    <h3 class="text-base font-semibold text-[#244334]">
                        {{
                            hasFilters()
                                ? 'No se encontraron resultados'
                                : 'Aún no hay códigos QR'
                        }}
                    </h3>

                    <p class="mt-2 max-w-sm text-sm leading-6 text-[#718477]">
                        {{
                            hasFilters()
                                ? 'No encontramos códigos QR que coincidan con los filtros seleccionados.'
                                : 'Crea un código QR para que los visitantes puedan iniciar un duelo por esta tarjeta.'
                        }}
                    </p>
                </div>
            </template>
        </DataTable>
    </div>
</template>
