<script setup lang="ts">
import { Check } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        id?: string;
        label?: string;
        description?: string;
        error?: string;
        disabled?: boolean;
    }>(),
    {
        id: 'is_active',
        label: 'Registro activo',
        description: 'Determina si este registro estará disponible para su uso en el sistema.',
        error: '',
        disabled: false,
    },
);

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="form-field">
        <label class="form-label" :for="id">
            Estado
        </label>

        <label
            :for="id"
            class="flex items-center justify-between gap-4 rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-4 transition hover:border-[#BFCFC6]"
            :class="{
                'cursor-pointer': !disabled,
                'cursor-not-allowed opacity-60': disabled,
            }"
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                >
                    <Check class="h-5 w-5" />
                </div>

                <div class="min-w-0">
                    <p class="text-sm font-semibold text-[#244334]">
                        {{ label }}
                    </p>

                    <p class="mt-1 text-xs leading-5 text-[#718477]">
                        {{ description }}
                    </p>
                </div>
            </div>

            <input
                :id="id"
                v-model="model"
                type="checkbox"
                class="form-checkbox shrink-0"
                :disabled="disabled"
            />
        </label>

        <p v-if="error" class="form-error">
            {{ error }}
        </p>
    </div>
</template>