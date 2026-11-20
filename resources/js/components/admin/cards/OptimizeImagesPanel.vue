<script setup lang="ts">
import OptimizeImagesPanel from '@/components/admin/OptimizeImagesPanel.vue';
import { CARD_IMAGE_PROFILES } from '@/lib/imageOptimize';
import type { CardImageKind } from '@/lib/imageOptimize';
import admin from '@/routes/admin';

// Panel de optimización con las rutas y perfiles de las tarjetas.

interface PendingCardImage {
    card_id: number;
    card_name: string;
    image: CardImageKind;
    path: string;
    url: string;
    bytes: number;
    width: number | null;
    height: number | null;
}

const labels: Record<CardImageKind, string> = {
    generated: 'Tarjeta generada',
    thumbnail: 'Miniatura',
    artwork: 'Ilustración',
};
</script>

<template>
    <OptimizeImagesPanel
        description="Convierte a WebP las imágenes de las tarjetas guardadas antes de la compresión automática."
        :pending-url="admin.cards.images.pending().url"
        :optimize-url="
            (item: PendingCardImage) =>
                admin.cards.images.optimize({
                    card: item.card_id,
                    image: item.image,
                }).url
        "
        :item-key="(item: PendingCardImage) => `${item.card_id}:${item.image}`"
        :item-name="(item: PendingCardImage) => item.card_name"
        :labels="labels"
        :profiles="CARD_IMAGE_PROFILES"
        :reload-only="['summary']"
    />
</template>
