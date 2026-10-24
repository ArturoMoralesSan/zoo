<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Save, Swords } from 'lucide-vue-next';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import {
    getRarityClasses,
    getRarityLabel
    
} from '@/lib/cardRarity';
import type {CardRaritySetting} from '@/lib/cardRarity';
import admin from '@/routes/admin';

const props = defineProps<{
    settings: CardRaritySetting[];
}>();

const form = useForm({
    settings: props.settings.map((setting) => ({
        rarity: setting.rarity,
        questions_per_duel: setting.questions_per_duel,
        retries: setting.retries,
        cooldown_minutes: setting.cooldown_minutes,
    })),
});

const fieldError = (index: number, field: 'questions_per_duel' | 'retries' | 'cooldown_minutes') =>
    (form.errors as Record<string, string | undefined>)[
        `settings.${index}.${field}`
    ];

const submit = () => {
    form.put(admin.cards.raritySettings.update().url, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};
</script>

<template>
    <AdminCard
        title="Configuración por rareza"
        description="Define las preguntas por duelo, el límite de reintentos y el tiempo de espera que usarán por defecto las tarjetas de cada rareza."
        :icon="Swords"
        :padding="false"
    >
        <form @submit.prevent="submit">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr>
                            <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                                Rareza
                            </th>

                            <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                                Preguntas por duelo
                            </th>

                            <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                                Reintentos
                            </th>

                            <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                                Espera tras agotar reintentos (min)
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#e8eee9]">
                        <tr
                            v-for="(setting, index) in form.settings"
                            :key="setting.rarity"
                            class="transition-colors hover:bg-[#f7faf8]"
                        >
                            <!-- Rareza -->
                            <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                <span
                                    class="inline-flex rounded-lg border px-2.5 py-1.5 text-xs font-semibold"
                                    :class="getRarityClasses(setting.rarity)"
                                >
                                    {{ getRarityLabel(setting.rarity) }}
                                </span>
                            </td>

                            <!-- Preguntas por duelo -->
                            <td class="px-5 py-4 sm:px-6">
                                <input
                                    v-model.number="setting.questions_per_duel"
                                    type="number"
                                    min="1"
                                    max="50"
                                    required
                                    :aria-label="`Preguntas por duelo (${getRarityLabel(setting.rarity)})`"
                                    class="form-input max-w-[140px]"
                                    :class="{
                                        'form-input-error': fieldError(index, 'questions_per_duel'),
                                    }"
                                />

                                <p
                                    v-if="fieldError(index, 'questions_per_duel')"
                                    class="form-error"
                                >
                                    {{ fieldError(index, 'questions_per_duel') }}
                                </p>
                            </td>

                            <!-- Reintentos -->
                            <td class="px-5 py-4 sm:px-6">
                                <input
                                    v-model.number="setting.retries"
                                    type="number"
                                    min="1"
                                    max="100"
                                    required
                                    :aria-label="`Reintentos (${getRarityLabel(setting.rarity)})`"
                                    class="form-input max-w-[140px]"
                                    :class="{
                                        'form-input-error': fieldError(index, 'retries'),
                                    }"
                                />

                                <p
                                    v-if="fieldError(index, 'retries')"
                                    class="form-error"
                                >
                                    {{ fieldError(index, 'retries') }}
                                </p>
                            </td>

                            <!-- Tiempo de espera -->
                            <td class="px-5 py-4 sm:px-6">
                                <input
                                    v-model.number="setting.cooldown_minutes"
                                    type="number"
                                    min="1"
                                    max="43200"
                                    required
                                    :aria-label="`Espera tras agotar reintentos (${getRarityLabel(setting.rarity)})`"
                                    class="form-input max-w-[140px]"
                                    :class="{
                                        'form-input-error': fieldError(index, 'cooldown_minutes'),
                                    }"
                                />

                                <p
                                    v-if="fieldError(index, 'cooldown_minutes')"
                                    class="form-error"
                                >
                                    {{ fieldError(index, 'cooldown_minutes') }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-xs text-[#718477]">
                    Las tarjetas con configuración personalizada no se ven afectadas.
                </p>

                <AdminButton
                    type="submit"
                    :icon="Save"
                    :loading="form.processing"
                    loading-text="Guardando..."
                    :disabled="!form.isDirty"
                >
                    Guardar configuración
                </AdminButton>
            </div>
        </form>
    </AdminCard>
</template>
