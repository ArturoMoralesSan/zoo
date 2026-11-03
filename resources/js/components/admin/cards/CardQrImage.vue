<script setup lang="ts">
import { QrCode } from 'lucide-vue-next';
import { ref, watch } from 'vue';

import { qrDataUrl } from '@/lib/cardQrCode';

const props = withDefaults(
    defineProps<{
        token: string;
        size?: number;
        alt?: string;
    }>(),
    {
        size: 96,
        alt: 'Código QR',
    },
);

const dataUrl = ref<string | null>(null);

watch(
    () => [props.token, props.size] as const,
    async ([token, size]) => {
        try {
            // Al doble de tamaño para que se vea nítido en pantallas de alta densidad.
            dataUrl.value = await qrDataUrl(token, size * 2);
        } catch (error) {
            console.error('Error generando el código QR:', error);
            dataUrl.value = null;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e1e9e3] bg-white"
        :style="{ width: `${size}px`, height: `${size}px` }"
    >
        <img
            v-if="dataUrl"
            :src="dataUrl"
            :alt="alt"
            class="h-full w-full object-contain"
        />

        <QrCode v-else :size="Math.round(size / 3)" class="text-[#84958a]" />
    </div>
</template>
