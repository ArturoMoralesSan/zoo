<script setup lang="ts">
import { MessageSquareText } from 'lucide-vue-next';
import { computed } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import DuelMessageFields from '@/components/admin/cards/DuelMessageFields.vue';
import type { CardRaritySetting, DuelSettingsSource } from '@/lib/cardRarity';
import { DUEL_MESSAGE_FIELDS, renderDuelMessage } from '@/lib/duelMessages';
import type { DuelMessageField, DuelMessages } from '@/lib/duelMessages';

type MessagesSource = 'default' | 'card';

/*
 * Mensajes del duelo de la tarjeta: los predeterminados o personalizados.
 */
const props = defineProps<{
    // Rareza y su configuración: solo para los intentos y la espera de la vista previa.
    rarity: string;
    raritySettings: CardRaritySetting[];
    // Mensajes predeterminados del panel.
    defaults: DuelMessages;
    cardName: string;
    // Para la vista previa: intentos y espera efectivos de la tarjeta.
    duelSettingsSource: DuelSettingsSource;
    retries: number | null;
    cooldownMinutes: number | null;
    errors: Partial<Record<'messages_source' | DuelMessageField, string>>;
}>();

const source = defineModel<MessagesSource>('source', { required: true });
const winMessage = defineModel<string | null>('winMessage', { required: true });
const retryMessage = defineModel<string | null>('retryMessage', {
    required: true,
});
const lockedMessage = defineModel<string | null>('lockedMessage', {
    required: true,
});

const raritySetting = computed(() =>
    props.raritySettings.find((setting) => setting.rarity === props.rarity),
);

const previewContext = computed(() => {
    const custom = props.duelSettingsSource === 'card';

    return {
        cardName: props.cardName.trim() || 'Tarjeta',
        retries: (custom ? props.retries : raritySetting.value?.retries) ?? 3,
        cooldownMinutes:
            (custom
                ? props.cooldownMinutes
                : raritySetting.value?.cooldown_minutes) ?? 1440,
    };
});

const sourceOptions: {
    value: MessagesSource;
    title: string;
    description: string;
}[] = [
    {
        value: 'default',
        title: 'Predeterminados (Default)',
        description: 'Usa los mensajes predeterminados del panel',
    },
    {
        value: 'card',
        title: 'Personalizado',
        description: 'Mensajes exclusivos para esta tarjeta',
    },
];

// El editor trabaja con textos; los campos vacíos se guardan como null.
const textModel = (model: typeof winMessage) =>
    computed({
        get: () => model.value ?? '',
        set: (value: string) => {
            model.value = value;
        },
    });

const winText = textModel(winMessage);
const retryText = textModel(retryMessage);
const lockedText = textModel(lockedMessage);
</script>

<template>
    <AdminCard
        title="Mensajes del duelo"
        description="Usa los mensajes predeterminados o personaliza lo que verá el jugador al terminar un duelo por esta tarjeta."
        :icon="MessageSquareText"
    >
        <div class="space-y-5">
            <!-- Origen de los mensajes -->
            <div class="form-field">
                <label class="form-label">
                    Origen de los mensajes
                    <span class="text-red-500">*</span>
                </label>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label
                        v-for="option in sourceOptions"
                        :key="option.value"
                        class="flex flex-1 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition"
                        :class="
                            source === option.value
                                ? 'border-[#187744] bg-[#F2F7F4] text-[#187744]'
                                : 'border-[#C8DCCF] bg-white text-[#435D4C]'
                        "
                    >
                        <input
                            v-model="source"
                            type="radio"
                            :value="option.value"
                            class="accent-[#187744]"
                        />
                        <div>
                            <p class="text-sm font-semibold">
                                {{ option.title }}
                            </p>
                            <p class="text-xs text-[#718477]">
                                {{ option.description }}
                            </p>
                        </div>
                    </label>
                </div>

                <p v-if="errors.messages_source" class="form-error">
                    {{ errors.messages_source }}
                </p>
            </div>

            <!-- Mensajes predeterminados -->
            <div
                v-if="source === 'default'"
                class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4"
            >
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div
                            v-for="field in DUEL_MESSAGE_FIELDS"
                            :key="field.key"
                            class="rounded-xl border border-[#DCE7DF] bg-white p-3.5"
                        >
                            <p class="text-xs font-medium text-[#718477]">
                                {{ field.label }}
                                <span class="font-mono text-[#84958A]">
                                    ({{ field.outcome }})
                                </span>
                            </p>
                            <p class="mt-1 text-sm text-[#244334]">
                                {{
                                    renderDuelMessage(
                                        field.key,
                                        defaults[field.key],
                                        previewContext,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <p class="form-help">
                        Los mensajes predeterminados se editan en la lista de
                        tarjetas, en "Mensajes del duelo".
                    </p>
                </div>
            </div>

            <!-- Mensajes personalizados -->
            <DuelMessageFields
                v-else
                v-model:win-message="winText"
                v-model:retry-message="retryText"
                v-model:locked-message="lockedText"
                :fallbacks="defaults"
                fallback-label="predeterminado"
                :context="previewContext"
                id-prefix="card_duel"
                :errors="errors"
            />
        </div>
    </AdminCard>
</template>
