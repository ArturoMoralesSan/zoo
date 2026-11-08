<script setup lang="ts">
import { FileImage, Trash2, Upload } from 'lucide-vue-next';

import { useObjectUrl } from '@/composables/useObjectUrl';
import { storageUrl } from '@/lib/modelPreview';

/*
 * Campo de imagen de la tarjeta (miniatura o imagen de la plantilla):
 * imagen actual con opción de eliminarla y selector de archivo nuevo.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        help: string;
        currentPath?: string | null;
        alt?: string;
        error?: string;
        disabled?: boolean;
    }>(),
    {
        currentPath: null,
        alt: '',
        error: '',
        disabled: false,
    },
);

const file = defineModel<File | null>('file', { required: true });

const emit = defineEmits<{
    delete: [];
}>();

const filePreviewUrl = useObjectUrl(() => file.value);

const handleFile = (event: Event) => {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
};
</script>

<template>
    <div class="space-y-4">
        <!-- Imagen actual -->
        <div
            v-if="currentPath"
            class="flex flex-col gap-5 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 sm:flex-row sm:items-start"
        >
            <img
                :src="storageUrl(currentPath) ?? ''"
                :alt="alt"
                class="h-44 w-32 shrink-0 rounded-lg border border-[#DCE7DF] bg-white object-cover"
            />

            <div class="flex min-w-0 flex-1 flex-col items-start gap-4">
                <div>
                    <p
                        class="flex items-center gap-2 text-sm font-semibold text-[#244334]"
                    >
                        <FileImage :size="16" />
                        Imagen actual
                    </p>

                    <p class="mt-2 text-xs break-all text-[#718477]">
                        {{ currentPath }}
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="props.disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="emit('delete')"
                >
                    <Trash2 :size="16" />
                    Eliminar imagen
                </button>
            </div>
        </div>

        <!-- Nueva imagen -->
        <div class="form-field">
            <label :for="id" class="form-label">
                {{ currentPath ? `Reemplazar ${label.toLowerCase()}` : label }}
            </label>

            <label :for="id" class="form-upload">
                <img
                    v-if="filePreviewUrl"
                    :src="filePreviewUrl"
                    :alt="file?.name"
                    class="h-24 w-auto rounded-lg object-contain"
                />

                <div v-else class="form-upload-icon">
                    <Upload :size="22" />
                </div>

                <span class="form-upload-title break-all">
                    {{ file?.name || 'Selecciona una imagen' }}
                </span>

                <span class="form-upload-description">
                    {{ help }}
                </span>

                <span class="form-upload-description">
                    JPG, JPEG, PNG o WEBP · Máximo 5 MB
                </span>

                <input
                    :id="id"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    class="form-upload-input"
                    @change="handleFile"
                />
            </label>

            <p v-if="error" class="form-error">
                {{ error }}
            </p>
        </div>
    </div>
</template>
