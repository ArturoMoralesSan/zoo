import { computed, ref } from 'vue';

import { cropOrigin } from '@/lib/imageCrop';
import { SPECIES_IMAGE_PROFILES } from '@/lib/imageOptimize';

type CropKind = 'main' | 'thumbnail';

/*
 * Recorte de la imagen principal (libre) y de la miniatura (1:1 fija) en los
 * formularios de especie. Un solo diálogo para ambas: cada archivo elegido
 * pasa por él antes de entrar al formulario.
 */
const TEXTS: Record<
    CropKind,
    { title: string; reason: string; guide: 'circle' | null }
> = {
    main: {
        title: 'Recortar imagen principal',
        reason: 'Después podrás marcar el punto focal para que la app no corte al animal.',
        guide: null,
    },
    thumbnail: {
        title: 'Recortar miniatura',
        reason: 'La miniatura se muestra en cuadros y en los círculos del mapa.',
        guide: 'circle',
    },
};

export function useSpeciesImageCrop(
    apply: Record<CropKind, (file: File) => void>,
) {
    const cropOpen = ref(false);
    const cropKind = ref<CropKind>('main');
    const cropFrom = ref<File | string | null>(null);
    const cropFilename = ref('imagen.png');

    const openCrop = (kind: CropKind, source: File | string | null) => {
        if (!source) {
            return;
        }

        cropKind.value = kind;
        // Volver a recortar parte de la foto completa, no del recorte anterior.
        cropFrom.value = source instanceof File ? cropOrigin(source) : source;
        cropFilename.value =
            typeof source === 'string'
                ? (source.split('/').pop() ?? 'imagen.png')
                : source.name;
        cropOpen.value = true;
    };

    /**
     * Manejador del input de archivo: abre el recorte en lugar de aplicarlo.
     */
    const pickFor = (kind: CropKind) => (event: Event) => {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0] ?? null;

        // Permite volver a elegir el mismo archivo tras cancelar.
        input.value = '';

        if (file) {
            openCrop(kind, file);
        }
    };

    const cropDialog = computed(() => ({
        source: cropFrom.value,
        profile: SPECIES_IMAGE_PROFILES[cropKind.value],
        filename: cropFilename.value,
        ...TEXTS[cropKind.value],
    }));

    const onCropped = (file: File) => apply[cropKind.value](file);

    // Solo la principal (sin proporción fija) se puede usar sin recortar.
    const onSkip = () => {
        if (cropFrom.value instanceof File) {
            apply[cropKind.value](cropFrom.value);
        }
    };

    return { cropOpen, cropDialog, openCrop, pickFor, onCropped, onSkip };
}
