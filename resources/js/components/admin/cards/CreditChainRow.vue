<script setup lang="ts">
import { ExternalLink, Sparkles } from 'lucide-vue-next';

import TableActions from '@/components/admin/TableActions.vue';
import type { CreditRole } from '@/lib/credits/types';

/*
 * Un crédito en la cadena de un recurso (lista de créditos de la tarjeta).
 * El origen se muestra destacado; los demás llevan su insignia, que usa el
 * mismo término que la app ("Basado en", "Incluye").
 */
defineProps<{
    credit: {
        id: number;
        role: CreditRole;
        role_label: string;
        source_label: string | null;
        source_url: string | null;
        license_short_name: string;
        license_url: string | null;
        attribution: string;
        is_ai: boolean;
        ai_checked_at: string | null;
    };
    editUrl: string;
}>();

defineEmits<{ delete: [] }>();

const ROLE_CLASSES: Record<CreditRole, string> = {
    origin: 'border-[#C8DCCF] bg-[#EAF5EE] text-[#187744]',
    based_on: 'border-sky-200 bg-sky-50 text-sky-800',
    component: 'border-violet-200 bg-violet-50 text-violet-800',
};
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
        <div class="min-w-0 flex-1 space-y-1.5">
            <p
                class="text-[#244334]"
                :class="
                    credit.role === 'origin'
                        ? 'text-sm font-semibold'
                        : 'text-sm'
                "
            >
                {{ credit.attribution }}
            </p>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <span
                    v-if="credit.role !== 'origin'"
                    class="rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                    :class="ROLE_CLASSES[credit.role]"
                >
                    {{ credit.role_label }}
                </span>

                <span
                    v-if="credit.is_ai"
                    class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold"
                    :class="
                        credit.ai_checked_at
                            ? 'border-violet-200 bg-violet-50 text-violet-800'
                            : 'border-amber-200 bg-amber-50 text-amber-800'
                    "
                >
                    <Sparkles :size="11" />
                    IA ·
                    {{ credit.ai_checked_at ? 'revisada' : 'sin revisar' }}
                </span>

                <a
                    v-if="credit.license_url"
                    :href="credit.license_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-[#187744] hover:underline"
                >
                    {{ credit.license_short_name }}
                    <ExternalLink :size="11" />
                </a>
                <span v-else class="text-xs font-semibold text-[#435D4C]">
                    {{ credit.license_short_name }}
                </span>

                <a
                    v-if="credit.source_url"
                    :href="credit.source_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1 text-xs text-[#718477] hover:underline"
                >
                    {{ credit.source_label ?? 'Ver origen' }}
                    <ExternalLink :size="11" />
                </a>
                <span
                    v-else-if="credit.source_label"
                    class="text-xs text-[#718477]"
                >
                    {{ credit.source_label }}
                </span>
            </div>
        </div>

        <TableActions
            :edit-url="editUrl"
            :show-view="false"
            item-name="crédito"
            @delete="$emit('delete')"
        />
    </div>
</template>
