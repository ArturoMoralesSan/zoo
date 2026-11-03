import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';

/**
 * Crea una URL temporal (blob:) para previsualizar un archivo local
 * y la libera cuando el archivo cambia o el componente se desmonta.
 */
export function useObjectUrl(
    file: () => File | null | undefined,
): Ref<string | null> {
    const url = ref<string | null>(null);

    const revoke = () => {
        if (url.value) {
            URL.revokeObjectURL(url.value);
            url.value = null;
        }
    };

    watch(
        file,
        (value) => {
            revoke();

            if (value) {
                url.value = URL.createObjectURL(value);
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(revoke);

    return url;
}
