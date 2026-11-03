<script setup lang="ts">
import { Box, Eye, IdCard } from 'lucide-vue-next';

import type { PreviewTab } from '@/composables/usePreviewTab';

defineProps<{
    hasModel: boolean;
}>();

const tab = defineModel<PreviewTab>('tab', { required: true });

const TABS: { id: PreviewTab; label: string; icon: typeof Box }[] = [
    { id: 'card', label: 'Tarjeta', icon: IdCard },
    { id: 'model', label: 'Modelo 3D', icon: Box },
];
</script>

<template>
    <!--
        Vista previa lateral con pestañas: una sola vista a la vez, siempre
        del alto de la pantalla. Ambas pestañas siguen montadas: la tarjeta se
        usa para generar el PNG al guardar. La inactiva se oculta con opacidad
        (no con visibility/display, que se copiarían al PNG o dejarían la
        vista sin tamaño).
    -->
    <section
        class="overflow-hidden rounded-2xl border border-[#DCE7DF] bg-white"
    >
        <div
            class="flex flex-wrap items-center gap-3 border-b border-[#E5ECE7] px-5 py-3 sm:px-6"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <Eye :size="18" aria-hidden="true" />
            </div>

            <h2 class="text-base font-semibold text-[#244334]">Vista previa</h2>

            <div
                class="ml-auto flex items-center gap-1 rounded-xl border border-[#DCE7DF] bg-[#F8FAF8] p-1"
                role="tablist"
                aria-label="Vista previa"
            >
                <button
                    v-for="item in TABS"
                    :id="`preview-tab-${item.id}`"
                    :key="item.id"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.id"
                    :aria-controls="`preview-panel-${item.id}`"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-semibold transition-colors"
                    :class="
                        tab === item.id
                            ? 'bg-[#EAF5EE] text-[#187744]'
                            : 'text-[#435D4C] hover:bg-[#F2F7F4]'
                    "
                    @click="tab = item.id"
                >
                    <component :is="item.icon" :size="15" />
                    {{ item.label }}

                    <span
                        v-if="item.id === 'model' && !hasModel"
                        class="text-[11px] font-medium text-[#8A9A8F]"
                    >
                        · Sin modelo
                    </span>
                </button>
            </div>
        </div>

        <div class="grid p-5 sm:p-6">
            <div
                id="preview-panel-card"
                role="tabpanel"
                aria-labelledby="preview-tab-card"
                class="col-start-1 row-start-1 min-w-0 transition-opacity duration-200"
                :class="tab === 'card' ? 'opacity-100' : 'pointer-events-none opacity-0'"
                :aria-hidden="tab !== 'card'"
                :inert="tab !== 'card'"
            >
                <slot name="card" />
            </div>

            <div
                id="preview-panel-model"
                role="tabpanel"
                aria-labelledby="preview-tab-model"
                class="col-start-1 row-start-1 min-w-0 transition-opacity duration-200"
                :class="tab === 'model' ? 'opacity-100' : 'pointer-events-none opacity-0'"
                :aria-hidden="tab !== 'model'"
                :inert="tab !== 'model'"
            >
                <slot name="model" />
            </div>
        </div>
    </section>
</template>
