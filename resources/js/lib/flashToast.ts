import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (data) {
            toast[data.type](data.message);
        }

        /*
         * Advertencias del modelo 3D al guardar una tarjeta (no impiden guardar).
         */
        const modelWarnings = flash?.model_warnings as string[] | undefined;

        if (modelWarnings?.length) {
            toast.warning('Modelo 3D guardado con advertencias', {
                description: modelWarnings.join(' '),
                duration: 10000,
            });
        }
    });
}
