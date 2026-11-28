<script setup lang="ts">
import { AlertTriangle, ArrowLeft, Check, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import AdminButton from '@/components/admin/AdminButton.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    conservationStatusOption,
    dietLabel,
    habitatLabels,
    sortHabitats,
} from '@/lib/speciesCatalogs';
import type { HabitatCode } from '@/lib/speciesCatalogs';
import admin from '@/routes/admin';

/*
 * "Completar con IA": la IA sugiere datos de la especie a partir de sus dos
 * nombres. Nada se guarda aquí: lo aceptado pasa al formulario y la persona
 * lo guarda con el botón de siempre.
 */

export interface SpeciesAiValues {
    description: string;
    origin: string;
    diet: string;
    habitats: HabitatCode[];
    conservation_status: string;
    species_category_id: string;
    tags: number[];
}

type FieldKey = keyof SpeciesAiValues;

interface Suggestions {
    description?: string;
    origin?: string;
    diet?: string;
    habitats?: HabitatCode[];
    conservation_status?: string;
    species_category_id?: number;
    tags?: number[];
}

interface CatalogItem {
    id: number;
    name: string;
}

const props = defineProps<{
    commonName: string;
    scientificName: string;
    values: SpeciesAiValues;
    categories: CatalogItem[];
    tags: CatalogItem[];
    aiEnabled: boolean;
}>();

const emit = defineEmits<{
    apply: [patch: Partial<SpeciesAiValues>];
}>();

// Categoría y etiquetas nunca se marcan solas: cambian cómo se agrupa la especie.
const FIELDS: { key: FieldKey; label: string; optIn?: boolean }[] = [
    { key: 'description', label: 'Descripción' },
    { key: 'origin', label: 'Origen' },
    { key: 'diet', label: 'Alimentación' },
    { key: 'habitats', label: 'Hábitats' },
    { key: 'conservation_status', label: 'Estado de conservación' },
    { key: 'species_category_id', label: 'Categoría', optIn: true },
    { key: 'tags', label: 'Etiquetas', optIn: true },
];

const open = ref(false);
const step = ref<'fields' | 'review'>('fields');
const selected = ref<FieldKey[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const suggestions = ref<Suggestions>({});
const accepted = ref<FieldKey[]>([]);

const namesReady = computed(
    () => props.commonName.trim() !== '' && props.scientificName.trim() !== '',
);

const disabledReason = computed(() => {
    if (!props.aiEnabled) {
        return 'La IA aún no está activada. Avisa al equipo técnico.';
    }

    if (!namesReady.value) {
        return 'Escribe el nombre común y el científico para usar la IA.';
    }

    return null;
});

const isEmpty = (key: FieldKey): boolean => {
    const value = props.values[key];

    return Array.isArray(value) ? value.length === 0 : !value;
};

const emptyFields = computed(() =>
    FIELDS.filter((field) => !field.optIn && isEmpty(field.key)).map(
        (field) => field.key,
    ),
);

const openDialog = () => {
    if (disabledReason.value) {
        return;
    }

    step.value = 'fields';
    error.value = null;
    selected.value = [...emptyFields.value];
    open.value = true;
};

const selectAll = () => {
    selected.value = FIELDS.map((field) => field.key);
};

const selectEmpty = () => {
    selected.value = [...emptyFields.value];
};

const catalogNames = (items: CatalogItem[], ids: number[]): string =>
    items
        .filter((item) => ids.includes(item.id))
        .map((item) => item.name)
        .join(', ');

// Texto legible de un valor, para comparar "actual" y "sugerido".
const display = (key: FieldKey, value: unknown): string => {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    switch (key) {
        case 'diet':
            return dietLabel(String(value)) ?? String(value);
        case 'habitats':
            return habitatLabels(value as string[]).join(', ');
        case 'conservation_status': {
            const option = conservationStatusOption(String(value));

            return option ? `${option.label} (${option.value})` : '';
        }
        case 'species_category_id':
            return catalogNames(props.categories, [Number(value)]);
        case 'tags':
            return catalogNames(props.tags, value as number[]);
        default:
            return String(value);
    }
};

// Las etiquetas sugeridas se suman a las que ya tiene; las demás se reemplazan.
const newTags = computed(() =>
    (suggestions.value.tags ?? []).filter(
        (id) => !props.values.tags.includes(id),
    ),
);

const rows = computed(() =>
    FIELDS.filter((field) => {
        if (field.key === 'tags') {
            return newTags.value.length > 0;
        }

        return suggestions.value[field.key] !== undefined;
    }).map((field) => ({
        ...field,
        current: display(field.key, props.values[field.key]),
        suggested:
            field.key === 'tags'
                ? display('tags', newTags.value)
                : display(field.key, suggestions.value[field.key]),
    })),
);

const errorMessage = (
    status: number,
    body: { success?: boolean; message?: string } | null,
): string => {
    if (body?.success === false && body.message) {
        return body.message;
    }

    if (status === 429) {
        return 'Hiciste muchas solicitudes seguidas. Espera un minuto e intenta de nuevo.';
    }

    if (status === 419) {
        return 'Tu sesión expiró. Recarga la página e intenta de nuevo.';
    }

    if (status === 422 && body?.message) {
        return body.message;
    }

    return 'No se pudieron obtener sugerencias. Intenta de nuevo.';
};

const generate = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(admin.species.aiFill().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                common_name: props.commonName.trim(),
                scientific_name: props.scientificName.trim(),
                fields: selected.value,
            }),
        });

        const body = await response.json().catch(() => null);

        if (!response.ok) {
            error.value = errorMessage(response.status, body);

            return;
        }

        suggestions.value = body.data.suggestions ?? {};
        accepted.value = rows.value.map((row) => row.key);
        step.value = 'review';
    } catch {
        error.value =
            'No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.';
    } finally {
        loading.value = false;
    }
};

const apply = () => {
    const patch: Partial<SpeciesAiValues> = {};
    const s = suggestions.value;
    const take = (key: FieldKey) => accepted.value.includes(key);

    if (take('description') && s.description) {
        patch.description = s.description;
    }

    if (take('origin') && s.origin) {
        patch.origin = s.origin;
    }

    if (take('diet') && s.diet) {
        patch.diet = s.diet;
    }

    if (take('habitats') && s.habitats) {
        patch.habitats = sortHabitats(s.habitats);
    }

    if (take('conservation_status') && s.conservation_status) {
        patch.conservation_status = s.conservation_status;
    }

    if (take('species_category_id') && s.species_category_id) {
        patch.species_category_id = String(s.species_category_id);
    }

    if (take('tags') && newTags.value.length) {
        patch.tags = [...props.values.tags, ...newTags.value];
    }

    emit('apply', patch);
    open.value = false;

    toast.success('Sugerencias aplicadas', {
        description: patch.tags
            ? 'Revisa los datos (las etiquetas están en su pestaña) y guarda la especie.'
            : 'Revisa los datos y guarda la especie.',
    });
};
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-xl border border-dashed border-[#c8dccf] bg-[#F2F7F4] p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-sm text-[#718477]">
            <span class="font-semibold text-[#244334]">
                ¿Ya escribiste los nombres?
            </span>
            La IA puede sugerir el resto de la ficha; tú revisas antes de
            guardar.
        </p>

        <div class="flex flex-col items-start gap-1 sm:items-end">
            <AdminButton
                :icon="Sparkles"
                :disabled="disabledReason !== null"
                :title="disabledReason ?? undefined"
                @click="openDialog"
            >
                Completar con IA
            </AdminButton>

            <span v-if="disabledReason" class="text-xs text-[#718477]">
                {{ disabledReason }}
            </span>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl"
        >
            <DialogTitle class="text-lg font-semibold text-[#244334]">
                Completar con IA
            </DialogTitle>

            <DialogDescription class="text-sm text-[#718477]">
                {{ commonName }}
                <em class="text-[#244334]">({{ scientificName }})</em>
            </DialogDescription>

            <!-- PASO 1: elegir campos -->
            <div v-if="step === 'fields'" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-[#244334]">
                        ¿Qué quieres que sugiera?
                    </p>

                    <div class="flex gap-2 text-xs font-semibold">
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-[#187744] hover:bg-[#EAF4ED]"
                            @click="selectEmpty"
                        >
                            Solo vacíos
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-2 py-1 text-[#187744] hover:bg-[#EAF4ED]"
                            @click="selectAll"
                        >
                            Todos
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <label
                        v-for="field in FIELDS"
                        :key="field.key"
                        class="form-checkbox-row cursor-pointer"
                    >
                        <input
                            v-model="selected"
                            type="checkbox"
                            :value="field.key"
                            class="form-checkbox"
                        />

                        <span>
                            <span class="form-checkbox-title block">
                                {{ field.label }}
                            </span>
                            <span class="form-checkbox-description block">
                                <template v-if="!isEmpty(field.key)">
                                    Ya tiene datos: se reemplazarían.
                                </template>
                                <template v-else-if="field.optIn">
                                    Solo de las que ya existen.
                                </template>
                                <template v-else>Vacío</template>
                            </span>
                        </span>
                    </label>
                </div>

                <p
                    v-if="error"
                    class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    {{ error }}
                </p>

                <div class="flex justify-end">
                    <AdminButton
                        :icon="Sparkles"
                        :disabled="selected.length === 0"
                        :loading="loading"
                        loading-text="Consultando a la IA…"
                        @click="generate"
                    >
                        Generar sugerencias
                    </AdminButton>
                </div>
            </div>

            <!-- PASO 2: revisar -->
            <div v-else class="flex flex-col gap-4">
                <p
                    class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                >
                    <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                    Generado por IA: puede equivocarse. Revisa cada dato antes
                    de guardar.
                </p>

                <p
                    v-if="rows.length === 0"
                    class="rounded-lg border border-[#c8dccf] p-4 text-sm text-[#718477]"
                >
                    La IA no encontró datos que sugerir para los campos
                    elegidos.
                </p>

                <label
                    v-for="row in rows"
                    :key="row.key"
                    class="form-checkbox-row cursor-pointer"
                    :class="{
                        'border-[#187744]/40 bg-[#EAF4ED]': accepted.includes(
                            row.key,
                        ),
                    }"
                >
                    <input
                        v-model="accepted"
                        type="checkbox"
                        :value="row.key"
                        class="form-checkbox"
                    />

                    <span class="flex min-w-0 flex-col gap-1">
                        <span class="form-checkbox-title">
                            {{ row.label }}
                            <span
                                v-if="row.key === 'tags'"
                                class="font-normal text-[#718477]"
                            >
                                (se agregan a las actuales)
                            </span>
                        </span>

                        <span
                            v-if="row.current && row.key !== 'tags'"
                            class="text-xs text-[#718477] line-through"
                        >
                            {{ row.current }}
                        </span>

                        <span class="text-sm text-[#244334]">
                            {{ row.suggested }}
                        </span>

                        <span
                            v-if="row.key === 'conservation_status'"
                            class="text-xs text-amber-700"
                        >
                            Cambia con el tiempo: verifícalo en la Lista Roja de
                            la UICN.
                        </span>
                    </span>
                </label>

                <div class="flex flex-wrap justify-between gap-2">
                    <AdminButton
                        variant="secondary"
                        :icon="ArrowLeft"
                        @click="step = 'fields'"
                    >
                        Volver
                    </AdminButton>

                    <AdminButton
                        :icon="Check"
                        :disabled="accepted.length === 0"
                        @click="apply"
                    >
                        Aplicar seleccionados
                    </AdminButton>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
