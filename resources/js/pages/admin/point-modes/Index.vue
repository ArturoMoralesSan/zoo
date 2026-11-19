<script setup lang="ts">
import { HugeiconsIcon } from '@hugeicons/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    CalendarRange,
    CircleOff,
    Copy,
    EyeOff,
    Pencil,
    Plus,
    Power,
    RotateCcw,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { modeChanges, percentChange } from '@/lib/pointModes';
import type {
    PointMode,
    PointModeChange,
    PointRuleRow,
} from '@/lib/pointModes';
import { resolveSeasonColor, seasonHugeIcon } from '@/lib/pointSeasonStyles';
import admin from '@/routes/admin';

const props = defineProps<{
    modes: PointMode[];
    rules: PointRuleRow[];
}>();

const PREVIEW_LIMIT = 4;

const activeMode = computed(
    () => props.modes.find((mode) => mode.is_active) ?? null,
);

const changesOf = (mode: PointMode) => modeChanges(mode.overrides, props.rules);

const changeLabel = (change: PointModeChange) => {
    if (change.points === 0) {
        return 'No otorga';
    }

    const percent = percentChange(change.base, change.points);

    return percent === null ? '' : `${percent > 0 ? '+' : ''}${percent} %`;
};

const escapeHtml = (text: string) =>
    text.replace(/[&<>"']/g, (char) => `&#${char.charCodeAt(0)};`);

const changesHtml = (mode: PointMode) => {
    const changes = changesOf(mode);

    if (changes.length === 0) {
        return '<p>Este modo usa los puntos base en todas las reglas.</p>';
    }

    const items = changes
        .map(
            (change) =>
                `<li><strong>${escapeHtml(change.rule.name)}</strong>: ${change.base} → ${change.points} pts</li>`,
        )
        .join('');

    return `<p>A partir de ahora estas reglas cambian:</p><ul style="text-align:left;margin:12px auto 0;max-width:380px;line-height:1.7">${items}</ul><p style="margin-top:12px;font-size:13px;color:#718477">Las demás usan sus puntos base. Los puntos ya otorgados no cambian.</p>`;
};

const confirmOptions = {
    icon: 'question' as const,
    showCancelButton: true,
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
    focusCancel: true,
    confirmButtonColor: '#187744',
    cancelButtonColor: '#84958a',
};

const activate = async (mode: PointMode) => {
    const result = await Swal.fire({
        ...confirmOptions,
        title: `¿Activar "${mode.name}"?`,
        html: changesHtml(mode),
        confirmButtonText: 'Sí, activar',
    });

    if (result.isConfirmed) {
        router.post(
            admin.pointModes.activate(mode.id).url,
            {},
            { preserveScroll: true },
        );
    }
};

const deactivate = async () => {
    const result = await Swal.fire({
        ...confirmOptions,
        title: '¿Volver a los puntos base?',
        text: 'Ningún modo quedará activo y cada regla dará sus puntos base.',
        confirmButtonText: 'Sí, volver',
    });

    if (result.isConfirmed) {
        router.post(
            admin.pointModes.deactivate().url,
            {},
            { preserveScroll: true },
        );
    }
};

const duplicate = (mode: PointMode) => {
    router.post(admin.pointModes.duplicate(mode.id).url);
};

const destroy = async (mode: PointMode) => {
    const result = await Swal.fire({
        ...confirmOptions,
        icon: 'warning',
        title: `¿Eliminar "${mode.name}"?`,
        text: 'El historial de puntos se conserva, pero ya no mostrará este modo. Esta acción no se puede deshacer.',
        confirmButtonText: 'Sí, eliminar',
    });

    if (result.isConfirmed) {
        router.delete(admin.pointModes.destroy(mode.id).url, {
            preserveScroll: true,
        });
    }
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: admin.dashboard() },
            { title: 'Modos de puntos', href: admin.pointModes.index() },
        ],
    },
});
</script>

<template>
    <Head title="Modos de puntos" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto bg-[#F2F7F4] p-4 sm:p-5"
    >
        <PageHeader
            title="Modos de puntos"
            description="Crea modos para temporadas o eventos y cambia entre ellos cuando quieras."
            section="Gamificación"
            :icon="CalendarRange"
            :action-icon="Plus"
            action-label="Nuevo modo"
            :action-href="admin.pointModes.create().url"
        />

        <!-- Estado actual -->
        <section
            v-if="activeMode"
            class="flex flex-col gap-4 rounded-2xl border border-[#BFE3CB] bg-[#E2F3E8] p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#187744] text-white"
                >
                    <Zap class="h-5 w-5" aria-hidden="true" />
                </div>

                <div class="min-w-0">
                    <p
                        class="text-xs font-bold tracking-wide text-[#187744] uppercase"
                    >
                        Modo activo
                    </p>
                    <h2 class="truncate text-lg font-semibold text-[#244334]">
                        {{ activeMode.name }}
                    </h2>
                    <p class="mt-0.5 text-sm text-[#435D4C]">
                        {{ changesOf(activeMode).length }}
                        {{
                            changesOf(activeMode).length === 1
                                ? 'regla cambiada'
                                : 'reglas cambiadas'
                        }}; las demás dan sus puntos base.
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <Link
                    :href="admin.pointModes.edit(activeMode.id).url"
                    class="inline-flex items-center gap-2 rounded-xl border border-[#BFE3CB] bg-white px-4 py-2.5 text-sm font-semibold text-[#187744] transition-colors hover:bg-[#F7FAF8]"
                >
                    <Pencil class="h-4 w-4" aria-hidden="true" />
                    Editar
                </Link>

                <AdminButton
                    variant="secondary"
                    :icon="RotateCcw"
                    @click="deactivate"
                >
                    Volver a puntos base
                </AdminButton>
            </div>
        </section>

        <section
            v-else
            class="flex items-start gap-3 rounded-2xl border border-[#DCE7DF] bg-white p-5 sm:p-6"
        >
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#F2F7F4] text-[#718477]"
            >
                <CircleOff class="h-5 w-5" aria-hidden="true" />
            </div>

            <div>
                <p
                    class="text-xs font-bold tracking-wide text-[#718477] uppercase"
                >
                    Sin modo activo
                </p>
                <h2 class="text-lg font-semibold text-[#244334]">
                    Puntos base
                </h2>
                <p class="mt-0.5 text-sm text-[#435D4C]">
                    Cada regla da los puntos configurados en "Reglas de puntos".
                    Activa un modo para cambiarlos durante una temporada o
                    evento.
                </p>
            </div>
        </section>

        <!-- Modos -->
        <div
            v-if="modes.length > 0"
            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="mode in modes"
                :key="mode.id"
                class="flex flex-col rounded-2xl border bg-white transition-colors"
                :class="
                    mode.is_active
                        ? 'border-[#187744]'
                        : 'border-[#DCE7DF] hover:border-[#A9CBB5]'
                "
            >
                <div class="flex flex-1 flex-col gap-3 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h3
                            class="text-base font-semibold break-words text-[#244334]"
                        >
                            {{ mode.name }}
                        </h3>

                        <span
                            v-if="mode.is_active"
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-[#187744] px-2.5 py-1 text-xs font-semibold text-white"
                        >
                            <Zap class="h-3 w-3" aria-hidden="true" />
                            Activo
                        </span>
                    </div>

                    <!-- Aviso en la app: con su ícono y color, o "oculto" -->
                    <div
                        v-if="mode.is_public"
                        class="flex items-center gap-2.5 rounded-xl px-3 py-2"
                        :style="{
                            backgroundColor: resolveSeasonColor(mode.color)
                                .soft,
                            color: resolveSeasonColor(mode.color).text,
                        }"
                        :title="`La app muestra: ${mode.public_title}`"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                            :style="{
                                backgroundColor: resolveSeasonColor(mode.color)
                                    .accent,
                                color: resolveSeasonColor(mode.color).on_accent,
                            }"
                        >
                            <HugeiconsIcon
                                :icon="seasonHugeIcon(mode.icon)"
                                :size="16"
                                :stroke-width="1.8"
                            />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block text-[11px] font-semibold tracking-wide uppercase opacity-80"
                            >
                                Visible en la app
                            </span>
                            <span class="block truncate text-sm font-semibold">
                                {{ mode.public_title }}
                            </span>
                        </span>
                    </div>

                    <span
                        v-else
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-[#DCE7DF] bg-[#F2F7F4] px-2 py-0.5 text-xs font-medium text-[#718477]"
                        title="Cambia los puntos sin avisar en la app"
                    >
                        <EyeOff class="h-3 w-3" aria-hidden="true" />
                        Oculto en la app
                    </span>

                    <p
                        v-if="mode.description"
                        class="line-clamp-2 text-sm text-[#718477]"
                    >
                        {{ mode.description }}
                    </p>

                    <div>
                        <p
                            class="text-xs font-semibold tracking-wide text-[#435D4C] uppercase"
                        >
                            {{ changesOf(mode).length }}
                            {{
                                changesOf(mode).length === 1
                                    ? 'regla cambiada'
                                    : 'reglas cambiadas'
                            }}
                        </p>

                        <ul
                            v-if="changesOf(mode).length > 0"
                            class="mt-2 space-y-1.5"
                        >
                            <li
                                v-for="change in changesOf(mode).slice(
                                    0,
                                    PREVIEW_LIMIT,
                                )"
                                :key="change.rule.id"
                                class="flex items-center justify-between gap-2 text-sm"
                            >
                                <span class="truncate text-[#244334]">{{
                                    change.rule.name
                                }}</span>

                                <span
                                    class="flex shrink-0 items-center gap-1.5 text-[#435D4C]"
                                >
                                    <span class="text-[#84958A] line-through">{{
                                        change.base
                                    }}</span>
                                    <span class="font-semibold">{{
                                        change.points
                                    }}</span>
                                    <span
                                        class="inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            change.points < change.base
                                                ? 'bg-amber-50 text-amber-800'
                                                : 'bg-[#E2F3E8] text-[#187744]'
                                        "
                                    >
                                        <ArrowDown
                                            v-if="change.points < change.base"
                                            class="h-3 w-3"
                                            aria-hidden="true"
                                        />
                                        <ArrowUp
                                            v-else
                                            class="h-3 w-3"
                                            aria-hidden="true"
                                        />
                                        {{ changeLabel(change) }}
                                    </span>
                                </span>
                            </li>
                        </ul>

                        <p
                            v-if="changesOf(mode).length > PREVIEW_LIMIT"
                            class="mt-1.5 text-xs text-[#718477]"
                        >
                            y {{ changesOf(mode).length - PREVIEW_LIMIT }} más
                        </p>

                        <p
                            v-if="changesOf(mode).length === 0"
                            class="mt-1 text-sm text-[#718477]"
                        >
                            Usa los puntos base en todas las reglas.
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 border-t border-[#E5ECE7] bg-[#FAFCFA] px-5 py-3"
                >
                    <AdminButton
                        v-if="!mode.is_active"
                        :icon="Power"
                        @click="activate(mode)"
                    >
                        Activar
                    </AdminButton>

                    <span
                        v-else
                        class="inline-flex items-center gap-1.5 px-1 text-sm font-semibold text-[#187744]"
                    >
                        <Zap class="h-4 w-4" aria-hidden="true" />
                        En uso
                    </span>

                    <div class="ml-auto flex items-center gap-1">
                        <Link
                            :href="admin.pointModes.edit(mode.id).url"
                            class="rounded-lg p-2 text-[#435D4C] transition-colors hover:bg-[#E4F2E8] hover:text-[#187744]"
                            title="Editar"
                            aria-label="Editar"
                        >
                            <Pencil class="h-4 w-4" />
                        </Link>

                        <button
                            type="button"
                            class="rounded-lg p-2 text-[#435D4C] transition-colors hover:bg-[#E4F2E8] hover:text-[#187744]"
                            title="Duplicar"
                            aria-label="Duplicar"
                            @click="duplicate(mode)"
                        >
                            <Copy class="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            class="rounded-lg p-2 text-[#435D4C] transition-colors hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-[#435D4C]"
                            :title="
                                mode.is_active
                                    ? 'Activa otro modo o vuelve a los puntos base para eliminarlo'
                                    : 'Eliminar'
                            "
                            aria-label="Eliminar"
                            :disabled="mode.is_active"
                            @click="destroy(mode)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <!-- Sin modos -->
        <section
            v-else
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-[#C8DCCF] bg-white px-6 py-12 text-center"
        >
            <div
                class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <CalendarRange class="h-6 w-6" aria-hidden="true" />
            </div>

            <h2 class="text-base font-semibold text-[#244334]">
                Todavía no hay modos
            </h2>
            <p class="max-w-md text-sm text-[#718477]">
                Un modo cambia cuántos puntos da cada regla, por ejemplo una
                temporada más difícil con la mitad de puntos por captura.
            </p>

            <Link
                :href="admin.pointModes.create().url"
                class="mt-1 inline-flex items-center gap-2 rounded-xl bg-[#187744] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#145f37]"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                Crear primer modo
            </Link>
        </section>
    </div>
</template>
