<script setup lang="ts">
import OptimizeImagesPanel from '@/components/admin/OptimizeImagesPanel.vue';
import { SPECIES_IMAGE_PROFILES } from '@/lib/imageOptimize';
import type { SpeciesImageKind } from '@/lib/imageOptimize';
import admin from '@/routes/admin';

// Panel de optimización con las rutas y perfiles de las especies. Solo
// aparece mientras haya imágenes pendientes.

interface PendingSpeciesImage {
    image_id: number;
    species_id: number;
    species_name: string;
    image: SpeciesImageKind;
    path: string;
    url: string;
    bytes: number;
    width: number | null;
    height: number | null;
}

const labels: Record<SpeciesImageKind, string> = {
    main: 'Imagen principal',
    thumbnail: 'Miniatura',
    gallery: 'Galería',
};
</script>

<template>
    <OptimizeImagesPanel
        description="Convierte a WebP las imágenes de especies guardadas antes de la compresión automática."
        :pending-url="admin.species.images.pending().url"
        :optimize-url="
            (item: PendingSpeciesImage) =>
                admin.species.images.optimize(item.image_id).url
        "
        :item-key="(item: PendingSpeciesImage) => String(item.image_id)"
        :item-name="(item: PendingSpeciesImage) => item.species_name"
        :labels="labels"
        :profiles="SPECIES_IMAGE_PROFILES"
        :reload-only="['species']"
        hide-when-empty
    />
</template>
