import { useMediaQuery } from '@vueuse/core';
import { ref } from 'vue';

export type PreviewTab = 'card' | 'model';

/**
 * Pestaña activa de la vista previa lateral (Tarjeta | Modelo 3D).
 *
 * Cambia sola según la sección del formulario que se está editando: las
 * secciones marcadas con data-preview="card" o data-preview="model" eligen
 * la pestaña; las demás no la cambian.
 */
export function usePreviewTab(initial: PreviewTab = 'card') {
    const tab = ref<PreviewTab>(initial);

    /*
     * La vista previa lateral fija solo existe en pantallas anchas (xl).
     * En el servidor se asume escritorio.
     */
    const isWide = useMediaQuery('(min-width: 1280px)', { ssrWidth: 1280 });

    const onFormInteraction = (event: Event) => {
        const section = (event.target as HTMLElement | null)?.closest<HTMLElement>(
            '[data-preview]',
        );
        const value = section?.dataset.preview;

        if (value === 'card' || value === 'model') {
            tab.value = value;
        }
    };

    return { tab, isWide, onFormInteraction };
}
