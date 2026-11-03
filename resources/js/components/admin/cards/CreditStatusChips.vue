<script setup lang="ts">
import { CircleAlert, CircleCheck } from 'lucide-vue-next';

import { CREDIT_ASSETS } from '@/lib/credits/types';
import type { CreditAsset } from '@/lib/credits/types';

/*
 * Estado de la licencia de cada recurso subido: "Licencia completa" o
 * "Falta licencia". Los recursos sin archivo no se muestran.
 */
const props = defineProps<{
    // Recursos con archivo y si tienen crédito.
    status: Partial<Record<CreditAsset, boolean>>;
    // Enlace a cada sección (p. ej. "#credit-artwork" o la URL del formulario).
    href?: (asset: CreditAsset) => string;
}>();

const items = () =>
    CREDIT_ASSETS.filter((asset) => props.status[asset.value] !== undefined);
</script>

<template>
    <div v-if="items().length" class="flex flex-wrap items-center gap-2">
        <component
            :is="href ? 'a' : 'span'"
            v-for="asset in items()"
            :key="asset.value"
            :href="href?.(asset.value)"
            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-semibold"
            :class="
                status[asset.value]
                    ? 'border-[#C8DCCF] bg-[#EAF5EE] text-[#187744]'
                    : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
            "
        >
            <CircleCheck v-if="status[asset.value]" :size="14" />
            <CircleAlert v-else :size="14" />
            {{ asset.label }}:
            {{ status[asset.value] ? 'Licencia completa' : 'Falta licencia' }}
        </component>
    </div>
</template>
