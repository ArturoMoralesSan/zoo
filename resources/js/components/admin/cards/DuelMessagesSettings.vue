<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, MessageSquareText, Save } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import DuelMessageFields from '@/components/admin/cards/DuelMessageFields.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { DuelMessageField, DuelMessages } from '@/lib/duelMessages';
import admin from '@/routes/admin';

/*
 * Mensajes predeterminados del duelo: los usan todas las tarjetas
 * que no tienen mensajes propios.
 */
const props = defineProps<{
    // Mensajes guardados (null = texto integrado).
    messages: Record<DuelMessageField, string | null>;
    builtIn: DuelMessages;
}>();

/*
 * Acordeón cerrado por defecto; se recuerda si se dejó abierto.
 */
const OPEN_STORAGE_KEY = 'admin.cards.duel-messages.open';

const isOpen = ref(false);

onMounted(() => {
    try {
        isOpen.value = localStorage.getItem(OPEN_STORAGE_KEY) === '1';
    } catch {
        // Almacenamiento no disponible: se mantiene cerrado.
    }
});

watch(isOpen, (open) => {
    try {
        localStorage.setItem(OPEN_STORAGE_KEY, open ? '1' : '0');
    } catch {
        // Almacenamiento no disponible: no se recuerda el estado.
    }
});

const form = useForm({
    win_message: props.messages.win_message ?? '',
    retry_message: props.messages.retry_message ?? '',
    locked_message: props.messages.locked_message ?? '',
});

// Vista previa con una tarjeta de ejemplo.
const previewContext = {
    cardName: 'Jaguar',
    retries: 3,
    cooldownMinutes: 1440,
};

const submit = () => {
    form.put(admin.cards.duelMessages.update().url, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};
</script>

<template>
    <Collapsible v-model:open="isOpen">
        <AdminCard :padding="false">
            <CollapsibleTrigger as-child>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-[#F7FAF8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#187744]/20 focus-visible:ring-inset sm:px-6"
                    :aria-expanded="isOpen"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                    >
                        <MessageSquareText
                            :size="20"
                            :stroke-width="2"
                            aria-hidden="true"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold text-[#244334]">
                            Mensajes del duelo
                        </h2>

                        <p class="mt-1 text-sm leading-5 text-[#718477]">
                            Mensajes predeterminados que la app muestra al
                            terminar un duelo. Cada tarjeta puede usarlos o
                            tener mensajes propios.
                        </p>
                    </div>

                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#435D4C] transition-colors"
                        :class="
                            isOpen
                                ? 'bg-[#E4F2E8] text-[#187744]'
                                : 'bg-[#F2F7F4]'
                        "
                        aria-hidden="true"
                    >
                        <ChevronDown
                            :size="22"
                            :stroke-width="2.25"
                            class="transition-transform duration-200"
                            :class="{ 'rotate-180': isOpen }"
                        />
                    </span>
                </button>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <form
                    class="border-t border-[#E5ECE7]"
                    @submit.prevent="submit"
                >
                    <div class="px-5 py-5 sm:px-6">
                        <DuelMessageFields
                            v-model:win-message="form.win_message"
                            v-model:retry-message="form.retry_message"
                            v-model:locked-message="form.locked_message"
                            :fallbacks="builtIn"
                            fallback-label="integrado"
                            :context="previewContext"
                            id-prefix="default_duel"
                            :errors="form.errors"
                        />
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <p class="text-xs text-[#718477]">
                            Se envían en el campo <code>message</code> al
                            responder un duelo, salvo en las tarjetas con
                            mensajes personalizados.
                        </p>

                        <AdminButton
                            type="submit"
                            :icon="Save"
                            :loading="form.processing"
                            loading-text="Guardando..."
                            :disabled="!form.isDirty"
                        >
                            Guardar mensajes
                        </AdminButton>
                    </div>
                </form>
            </CollapsibleContent>
        </AdminCard>
    </Collapsible>
</template>
