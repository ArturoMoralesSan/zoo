<script setup lang="ts">
import {
    CalendarRange,
    Percent,
    RotateCcw,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AdminButton from '@/components/admin/AdminButton.vue';
import AdminCard from '@/components/admin/AdminCard.vue';
import { percentChange, scalePoints } from '@/lib/pointModes';
import type { PointModeFormRule, PointRuleRow } from '@/lib/pointModes';

const props = defineProps<{
    rules: PointRuleRow[];
    errors: Record<string, string | undefined>;
}>();

const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });
const items = defineModel<PointModeFormRule[]>('items', { required: true });

const percent = ref(100);

const ruleById = computed(
    () => new Map(props.rules.map((rule) => [rule.id, rule])),
);

const changedCount = computed(
    () =>
        items.value.filter((item) => {
            const rule = ruleById.value.get(item.point_rule_id);

            return item.points !== null && item.points !== rule?.points;
        }).length,
);

// Un input vacío vuelve a los puntos base (null).
const setPoints = (index: number, raw: string) => {
    const value =
        raw.trim() === '' ? null : Math.max(0, Math.trunc(Number(raw)));

    items.value[index].points = Number.isNaN(value) ? null : value;
};

const toggleAwards = (index: number, awards: boolean) => {
    items.value[index].points = awards ? null : 0;
};

const applyPercent = () => {
    items.value = items.value.map((item) => {
        const base = ruleById.value.get(item.point_rule_id)?.points ?? 0;
        const points = scalePoints(base, percent.value);

        return { ...item, points: points === base ? null : points };
    });
};

const resetToBase = () => {
    items.value = items.value.map((item) => ({ ...item, points: null }));
    percent.value = 100;
};

type ChangeBadge = { label: string; classes: string };

const changeBadge = (item: PointModeFormRule): ChangeBadge => {
    const base = ruleById.value.get(item.point_rule_id)?.points ?? 0;

    if (item.points === null || item.points === base) {
        return {
            label: 'Igual que base',
            classes: 'border-[#DCE7DF] bg-[#F2F7F4] text-[#718477]',
        };
    }

    if (item.points === 0) {
        return {
            label: 'No otorga puntos',
            classes: 'border-red-200 bg-red-50 text-red-700',
        };
    }

    const change = percentChange(base, item.points);
    const label =
        change === null
            ? `${item.points} pts`
            : `${change > 0 ? '+' : ''}${change} %`;

    return item.points < base
        ? { label, classes: 'border-amber-200 bg-amber-50 text-amber-800' }
        : { label, classes: 'border-[#BFE3CB] bg-[#E2F3E8] text-[#187744]' };
};

const rowError = (index: number) =>
    props.errors[`rules.${index}.points`] ??
    props.errors[`rules.${index}.point_rule_id`];
</script>

<template>
    <AdminCard
        title="Datos del modo"
        description="Ponle un nombre que identifique la temporada o el evento."
        :icon="CalendarRange"
    >
        <div class="form-grid">
            <div class="form-field form-field-full">
                <label for="name" class="form-label">
                    Nombre <span class="form-required">*</span>
                </label>
                <input
                    id="name"
                    v-model="name"
                    type="text"
                    maxlength="255"
                    required
                    placeholder="Ej. Temporada alta, Halloween, Vacaciones de verano"
                    class="form-input"
                    :class="{ 'form-input-error': errors.name }"
                />
                <p v-if="errors.name" class="form-error">{{ errors.name }}</p>
            </div>

            <div class="form-field form-field-full">
                <label for="description" class="form-label">Descripción</label>
                <textarea
                    id="description"
                    v-model="description"
                    maxlength="1000"
                    rows="2"
                    placeholder="Ej. Más difícil durante diciembre: las capturas dan menos puntos."
                    class="form-textarea"
                    :class="{ 'form-textarea-error': errors.description }"
                />
                <p v-if="errors.description" class="form-error">
                    {{ errors.description }}
                </p>
            </div>
        </div>
    </AdminCard>

    <AdminCard
        title="Ajuste rápido"
        description="Cambia todas las reglas a la vez a un porcentaje de sus puntos base. Después puedes afinar regla por regla."
        :icon="SlidersHorizontal"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="form-field sm:max-w-[200px]">
                <label for="percent" class="form-label"
                    >Porcentaje de los puntos base</label
                >
                <div class="relative">
                    <input
                        id="percent"
                        v-model.number="percent"
                        type="number"
                        min="0"
                        max="1000"
                        class="form-input pr-10"
                    />
                    <Percent
                        class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 text-[#718477]"
                        aria-hidden="true"
                    />
                </div>
            </div>

            <AdminButton :icon="Percent" @click="applyPercent">
                Aplicar a todas
            </AdminButton>

            <AdminButton
                variant="secondary"
                :icon="RotateCcw"
                @click="resetToBase"
            >
                Restablecer a puntos base
            </AdminButton>
        </div>

        <p class="form-help mt-3">
            Ej. 50 % deja cada regla en la mitad (una temporada más difícil);
            150 % da 50 % más.
        </p>
    </AdminCard>

    <AdminCard :padding="false">
        <div
            class="flex flex-col gap-1 border-b border-[#E5ECE7] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div>
                <h2 class="text-base font-semibold text-[#244334]">
                    Puntos por regla
                </h2>
                <p class="mt-1 text-sm text-[#718477]">
                    Deja el campo vacío para usar los puntos base. Apaga una
                    regla para que no otorgue puntos en este modo.
                </p>
            </div>

            <span
                class="shrink-0 rounded-lg border border-[#DCE7DF] bg-[#F2F7F4] px-3 py-1.5 text-xs font-semibold text-[#435D4C]"
            >
                {{ changedCount }} de {{ rules.length }} reglas cambiadas
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr
                        class="bg-[#edf4ef] text-xs font-bold tracking-wide text-[#435d4c] uppercase"
                    >
                        <th class="px-5 py-3.5 sm:px-6">Regla</th>
                        <th class="px-5 py-3.5 sm:px-6">Base</th>
                        <th class="px-5 py-3.5 sm:px-6">Puntos en este modo</th>
                        <th class="px-5 py-3.5 sm:px-6">Otorga puntos</th>
                        <th class="px-5 py-3.5 sm:px-6">Cambio</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#e8eee9]">
                    <tr
                        v-for="(item, index) in items"
                        :key="item.point_rule_id"
                        class="transition-colors hover:bg-[#f7faf8]"
                    >
                        <td class="px-5 py-4 sm:px-6">
                            <p class="font-semibold text-[#244334]">
                                {{ ruleById.get(item.point_rule_id)?.name }}
                            </p>
                            <p class="mt-0.5 font-mono text-xs text-[#718477]">
                                {{ ruleById.get(item.point_rule_id)?.type }}
                            </p>
                            <span
                                v-if="
                                    !ruleById.get(item.point_rule_id)?.is_active
                                "
                                class="mt-1.5 inline-flex rounded-md border border-[#DCE7DF] bg-[#F2F7F4] px-2 py-0.5 text-[11px] font-semibold text-[#718477]"
                                title="Una regla inactiva no otorga puntos en ningún modo."
                            >
                                Regla inactiva
                            </span>
                        </td>

                        <td
                            class="px-5 py-4 whitespace-nowrap text-[#435D4C] sm:px-6"
                        >
                            {{ ruleById.get(item.point_rule_id)?.points }} pts
                        </td>

                        <td class="px-5 py-4 sm:px-6">
                            <input
                                type="number"
                                min="0"
                                max="100000"
                                :value="item.points ?? ''"
                                :placeholder="`${ruleById.get(item.point_rule_id)?.points ?? 0} (base)`"
                                :disabled="item.points === 0"
                                :aria-label="`Puntos en este modo (${ruleById.get(item.point_rule_id)?.name})`"
                                class="form-input max-w-36"
                                :class="{ 'form-input-error': rowError(index) }"
                                @input="
                                    setPoints(
                                        index,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                            <p v-if="rowError(index)" class="form-error">
                                {{ rowError(index) }}
                            </p>
                        </td>

                        <td class="px-5 py-4 sm:px-6">
                            <label
                                class="inline-flex cursor-pointer items-center gap-2 text-sm text-[#435D4C]"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer accent-[#187744]"
                                    :checked="item.points !== 0"
                                    @change="
                                        toggleAwards(
                                            index,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                {{ item.points === 0 ? 'No' : 'Sí' }}
                            </label>
                        </td>

                        <td class="px-5 py-4 whitespace-nowrap sm:px-6">
                            <span
                                class="inline-flex rounded-lg border px-2.5 py-1 text-xs font-semibold"
                                :class="changeBadge(item).classes"
                            >
                                {{ changeBadge(item).label }}
                            </span>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0">
                        <td
                            colspan="5"
                            class="px-6 py-10 text-center text-sm text-[#718477]"
                        >
                            No hay reglas de puntos registradas todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminCard>
</template>
