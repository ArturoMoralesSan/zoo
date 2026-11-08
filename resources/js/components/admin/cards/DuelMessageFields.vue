<script setup lang="ts">
import { ref } from 'vue';

import {
    DUEL_MESSAGE_FIELDS,
    DUEL_MESSAGE_PLACEHOLDERS,
    renderDuelMessage,
} from '@/lib/duelMessages';
import type {
    DuelMessageContext,
    DuelMessageField,
    DuelMessages,
} from '@/lib/duelMessages';

/*
 * Editor de los tres mensajes del duelo (victoria, reintento, sin intentos):
 * marcadores, campos y vista previa. Lo usan la configuración por rareza
 * y la de cada tarjeta.
 */
const props = withDefaults(
    defineProps<{
        // Mensajes usados cuando un campo está vacío.
        fallbacks: DuelMessages;
        // Origen de esos mensajes, p. ej. "predeterminado" o "de la rareza".
        fallbackLabel: string;
        context: DuelMessageContext;
        idPrefix: string;
        errors?: Partial<Record<DuelMessageField, string>>;
    }>(),
    {
        errors: () => ({}),
    },
);

const winMessage = defineModel<string>('winMessage', { required: true });
const retryMessage = defineModel<string>('retryMessage', { required: true });
const lockedMessage = defineModel<string>('lockedMessage', { required: true });

const models = {
    win_message: winMessage,
    retry_message: retryMessage,
    locked_message: lockedMessage,
};

/*
 * Insertar un marcador en el último campo usado, donde estaba el cursor.
 */
const textareas = ref<Partial<Record<DuelMessageField, HTMLTextAreaElement>>>(
    {},
);

const lastField = ref<DuelMessageField>('win_message');

const insertPlaceholder = (token: string) => {
    const field = lastField.value;
    const element = textareas.value[field];
    const value = models[field].value ?? '';
    const start = element?.selectionStart ?? value.length;
    const end = element?.selectionEnd ?? value.length;

    models[field].value = value.slice(0, start) + token + value.slice(end);

    requestAnimationFrame(() => {
        element?.focus();
        element?.setSelectionRange(start + token.length, start + token.length);
    });
};

const preview = (field: DuelMessageField): string =>
    renderDuelMessage(
        field,
        (models[field].value ?? '').trim() || props.fallbacks[field],
        props.context,
    );
</script>

<template>
    <div class="space-y-5">
        <!-- Marcadores -->
        <div class="rounded-xl border border-[#DCE7DF] bg-[#F7FAF8] p-3">
            <p class="mb-2 text-xs font-semibold text-[#435D4C]">
                Marcadores: haz clic para insertarlos en el último campo usado.
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="placeholder in DUEL_MESSAGE_PLACEHOLDERS"
                    :key="placeholder.token"
                    type="button"
                    :title="placeholder.description"
                    class="rounded-md border border-[#C8DCCF] bg-white px-2 py-1 font-mono text-xs text-[#187744] hover:bg-[#EAF5EE]"
                    @click="insertPlaceholder(placeholder.token)"
                >
                    {{ placeholder.token }}
                </button>
            </div>
        </div>

        <!-- Mensajes -->
        <div class="grid gap-5 lg:grid-cols-3">
            <div
                v-for="field in DUEL_MESSAGE_FIELDS"
                :key="field.key"
                class="form-field"
            >
                <label :for="`${idPrefix}_${field.key}`" class="form-label">
                    {{ field.label }}
                    <span class="font-mono text-xs font-normal text-[#84958A]">
                        ({{ field.outcome }})
                    </span>
                </label>

                <textarea
                    :id="`${idPrefix}_${field.key}`"
                    :ref="
                        (element) =>
                            (textareas[field.key] =
                                element as HTMLTextAreaElement)
                    "
                    v-model="models[field.key].value"
                    rows="3"
                    maxlength="255"
                    :placeholder="fallbacks[field.key]"
                    class="form-textarea"
                    :class="{ 'form-textarea-error': errors[field.key] }"
                    @focus="lastField = field.key"
                />

                <p class="form-help">
                    {{ field.help }} Si se deja vacío, se usa el mensaje
                    {{ fallbackLabel }}.
                </p>

                <p v-if="errors[field.key]" class="form-error">
                    {{ errors[field.key] }}
                </p>

                <!-- Vista previa -->
                <p
                    class="rounded-lg border border-dashed border-[#C8DCCF] bg-white px-3 py-2 text-sm text-[#244334]"
                >
                    <span
                        class="block text-[11px] font-semibold tracking-wide text-[#84958A] uppercase"
                    >
                        Vista previa
                    </span>
                    {{ preview(field.key) }}
                </p>
            </div>
        </div>
    </div>
</template>
