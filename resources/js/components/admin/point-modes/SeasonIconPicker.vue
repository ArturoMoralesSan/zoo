<script setup lang="ts">
import { HugeiconsIcon } from '@hugeicons/vue';
import { Check, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    findSeasonIcon,
    hugeIconsByName,
    searchSeasonIcons,
    seasonHugeIcon,
    seasonIconGroups,
} from '@/lib/pointSeasonStyles';
import type { SeasonColor } from '@/lib/pointSeasonStyles';

/*
 * Selector del ícono del aviso de temporada. null = predeterminado de la app.
 */
const props = defineProps<{
    // Colores con los que se ve el aviso, para que las vistas coincidan.
    color: SeasonColor;
    error?: string;
}>();

const model = defineModel<string | null>({ required: true });

const open = ref(false);
const query = ref('');
const group = ref<string | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const selected = computed(() => findSeasonIcon(model.value));
const results = computed(() => searchSeasonIcons(query.value, group.value));

// "Predeterminado" solo aparece cuando no se está buscando ni filtrando.
const showDefault = computed(() => !query.value.trim() && group.value === null);

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    query.value = '';
    group.value = null;
    await nextTick();
    searchInput.value?.focus();
});

const pick = (key: string | null) => {
    model.value = key;
    open.value = false;
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-3 rounded-xl border bg-[#FCFEFC] px-3 py-2.5 text-left transition-colors hover:border-[#A9CBB5] focus:border-[#187744] focus:ring-2 focus:ring-[#187744]/10 focus:outline-none sm:max-w-sm"
            :class="error ? 'border-red-400' : 'border-[#C8DCCF]'"
            @click="open = true"
        >
            <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                :style="{
                    backgroundColor: props.color.accent,
                    color: props.color.on_accent,
                }"
            >
                <HugeiconsIcon
                    :icon="seasonHugeIcon(model)"
                    :size="22"
                    :stroke-width="1.8"
                />
            </span>

            <span class="min-w-0 flex-1">
                <span
                    class="block truncate text-sm font-semibold text-[#244334]"
                >
                    {{ selected?.label ?? 'Predeterminado' }}
                </span>
                <span class="block text-xs text-[#718477]">
                    {{
                        selected
                            ? 'Toca para cambiarlo'
                            : 'La app elige el ícono'
                    }}
                </span>
            </span>

            <span class="shrink-0 text-sm font-semibold text-[#187744]"
                >Elegir</span
            >
        </button>

        <button
            v-if="model !== null"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium text-[#718477] transition-colors hover:bg-[#F2F7F4] hover:text-[#244334]"
            @click="model = null"
        >
            <X class="h-4 w-4" aria-hidden="true" />
            Usar predeterminado
        </button>
    </div>

    <Dialog v-model:open="open">
        <DialogContent
            class="flex max-h-[85vh] flex-col gap-0 overflow-hidden rounded-2xl border-[#DCE7DF] p-0 shadow-none sm:max-w-2xl"
        >
            <div class="border-b border-[#E5ECE7] px-5 pt-5 pb-4">
                <DialogTitle class="text-lg font-semibold text-[#244334]">
                    Elegir ícono
                </DialogTitle>
                <DialogDescription class="mt-1 text-sm text-[#718477]">
                    Busca por temporada, festividad o animal: "navidad",
                    "muertos", "verano", "panda"…
                </DialogDescription>

                <div class="relative mt-4">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-[#718477]"
                        aria-hidden="true"
                    />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="search"
                        placeholder="Buscar ícono"
                        aria-label="Buscar ícono"
                        class="form-input pl-10"
                    />
                </div>

                <div
                    class="mt-3 flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Grupos de íconos"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="group === null"
                        class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
                        :class="
                            group === null
                                ? 'border-[#187744] bg-[#187744] text-white'
                                : 'border-[#DCE7DF] bg-white text-[#435D4C] hover:border-[#A9CBB5]'
                        "
                        @click="group = null"
                    >
                        Todos
                    </button>

                    <button
                        v-for="item in seasonIconGroups"
                        :key="item.key"
                        type="button"
                        role="tab"
                        :aria-selected="group === item.key"
                        class="rounded-full border px-3 py-1 text-sm font-medium transition-colors"
                        :class="
                            group === item.key
                                ? 'border-[#187744] bg-[#187744] text-white'
                                : 'border-[#DCE7DF] bg-white text-[#435D4C] hover:border-[#A9CBB5]'
                        "
                        @click="group = item.key"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto px-5 py-4">
                <div
                    class="grid grid-cols-3 gap-2 sm:grid-cols-5"
                    role="listbox"
                    aria-label="Íconos"
                >
                    <button
                        v-if="showDefault"
                        type="button"
                        role="option"
                        :aria-selected="model === null"
                        class="relative flex flex-col items-center gap-2 rounded-xl border px-2 py-3 text-center transition-colors focus:ring-2 focus:ring-[#187744]/20 focus:outline-none"
                        :class="
                            model === null
                                ? 'border-[#187744] bg-[#E2F3E8]'
                                : 'border-dashed border-[#C8DCCF] bg-white hover:border-[#A9CBB5] hover:bg-[#F7FAF8]'
                        "
                        @click="pick(null)"
                    >
                        <Check
                            v-if="model === null"
                            class="absolute top-1.5 right-1.5 h-4 w-4 text-[#187744]"
                            aria-hidden="true"
                        />
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F2F7F4] text-[#718477]"
                        >
                            <HugeiconsIcon
                                :icon="seasonHugeIcon(null)"
                                :size="24"
                                :stroke-width="1.8"
                            />
                        </span>
                        <span class="text-xs font-medium text-[#244334]"
                            >Predeterminado</span
                        >
                    </button>

                    <button
                        v-for="icon in results"
                        :key="icon.key"
                        type="button"
                        role="option"
                        :aria-selected="model === icon.key"
                        :title="icon.label"
                        class="relative flex flex-col items-center gap-2 rounded-xl border px-2 py-3 text-center transition-colors focus:ring-2 focus:ring-[#187744]/20 focus:outline-none"
                        :class="
                            model === icon.key
                                ? 'border-[#187744] bg-[#E2F3E8]'
                                : 'border-[#E5ECE7] bg-white hover:border-[#A9CBB5] hover:bg-[#F7FAF8]'
                        "
                        @click="pick(icon.key)"
                    >
                        <Check
                            v-if="model === icon.key"
                            class="absolute top-1.5 right-1.5 h-4 w-4 text-[#187744]"
                            aria-hidden="true"
                        />
                        <span
                            class="flex h-11 w-11 items-center justify-center rounded-xl"
                            :style="{
                                backgroundColor: props.color.soft,
                                color: props.color.text,
                            }"
                        >
                            <HugeiconsIcon
                                :icon="hugeIconsByName[icon.icon]"
                                :size="24"
                                :stroke-width="1.8"
                            />
                        </span>
                        <span
                            class="line-clamp-2 text-xs font-medium text-[#244334]"
                        >
                            {{ icon.label }}
                        </span>
                    </button>
                </div>

                <p
                    v-if="results.length === 0"
                    class="py-10 text-center text-sm text-[#718477]"
                >
                    No hay íconos para "{{ query }}". Prueba con otra palabra o
                    elige "Todos".
                </p>
            </div>
        </DialogContent>
    </Dialog>

    <p v-if="error" class="form-error">{{ error }}</p>
</template>
