<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Save, Swords } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { getRarityClasses, getRarityLabel } from '@/lib/cardRarity';
import type { CardRaritySetting } from '@/lib/cardRarity';
import admin from '@/routes/admin';

const props = defineProps<{
    settings: CardRaritySetting[];
}>();

/*
 * Acordeón cerrado por defecto. Se recuerda si el usuario lo dejó abierto,
 * así sigue abierto al volver a la página o después de guardar.
 */
const OPEN_STORAGE_KEY = 'admin.cards.rarity-settings.open';

const isOpen = ref(false);

// Se lee al montar para que coincida con el HTML renderizado en el servidor.
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
    settings: props.settings.map((setting) => ({
        rarity: setting.rarity,
        questions_per_duel: setting.questions_per_duel,
        retries: setting.retries,
        cooldown_minutes: setting.cooldown_minutes,
        reveal_answers_on_lockout: setting.reveal_answers_on_lockout,
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
    <Collapsible v-model:open="isOpen">
        <AdminCard :padding="false">
            <CollapsibleTrigger as-child>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-[#F7FAF8] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#187744]/20 sm:px-6"
                    :aria-expanded="isOpen"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
                    >
                        <Swords :size="20" :stroke-width="2" aria-hidden="true" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold text-[#244334]">
                            Configuración por rareza
                        </h2>

                        <p class="mt-1 text-sm leading-5 text-[#718477]">
                            Define las preguntas por duelo, el límite de reintentos y el tiempo de espera que usarán por defecto las tarjetas de cada rareza.
                        </p>
                    </div>

                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-[#435D4C] transition-colors"
                        :class="isOpen ? 'bg-[#E4F2E8] text-[#187744]' : 'bg-[#F2F7F4]'"
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
                <form class="border-t border-[#E5ECE7]" @submit.prevent="submit">
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

                                    <th class="border-b border-[#dce7df] bg-[#edf4ef] px-5 py-3.5 text-left text-xs font-bold uppercase tracking-wide text-[#435d4c] sm:px-6">
                                        Mostrar respuestas al agotar intentos
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

                                    <!-- Repaso con las respuestas correctas -->
                                    <td class="px-5 py-4 sm:px-6">
                                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#435D4C]">
                                            <input
                                                v-model="setting.reveal_answers_on_lockout"
                                                type="checkbox"
                                                class="h-4 w-4 cursor-pointer accent-[#187744]"
                                            />
                                            {{ setting.reveal_answers_on_lockout ? 'Sí' : 'No' }}
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-[#DCE7DF] bg-[#FAFCFA] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p class="text-xs text-[#718477]">
                            Preguntas, reintentos y espera no afectan a las tarjetas con configuración
                            personalizada. Mostrar respuestas aplica a todas las tarjetas de la rareza:
                            al agotar los intentos, la app recibe un repaso con las respuestas correctas.
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
            </CollapsibleContent>
        </AdminCard>
    </Collapsible>
</template>
