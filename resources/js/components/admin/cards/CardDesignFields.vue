<script setup lang="ts">
import { ChevronDown, Palette } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

import AdminCard from '@/components/admin/AdminCard.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DEFAULT_FADE_PRESETS, autoAccent, isHexColor } from '@/lib/cardColors';
import type { AccentColor } from '@/lib/cardColors';
import {
    CARD_FONTS,
    TITLE_SIZES,
    WEIGHT_LABELS,
    cardFontOptions,
    ensureCardFont,
    nearestWeight,
} from '@/lib/cardFonts';
import type { CardFontId, TitleSize } from '@/lib/cardFonts';

defineProps<{
    errors: Partial<
        Record<
            | 'fade_color'
            | 'accent_color'
            | 'title_font_family'
            | 'title_font_weight'
            | 'title_font_size',
            string
        >
    >;
}>();

const fadeColor = defineModel<string>('fadeColor', { required: true });
const accentColor = defineModel<AccentColor | null>('accentColor', {
    required: true,
});
const titleFontFamily = defineModel<CardFontId>('titleFontFamily', {
    required: true,
});
const titleFontWeight = defineModel<number>('titleFontWeight', {
    required: true,
});
const titleFontSize = defineModel<TitleSize>('titleFontSize', {
    required: true,
});

/*
 * Campo de texto del color: solo se aplica cuando es un hexadecimal válido.
 */
const fadeColorText = ref(fadeColor.value);

watch(fadeColor, (value) => {
    if (value.toLowerCase() !== fadeColorText.value.toLowerCase()) {
        fadeColorText.value = value;
    }
});

const onFadeColorText = () => {
    const value = fadeColorText.value.trim();
    const hex = value.startsWith('#') ? value : `#${value}`;

    if (isHexColor(hex)) {
        fadeColor.value = hex.toLowerCase();
    }
};

const accentLabels: Record<AccentColor, string> = {
    white: 'Blanco',
    black: 'Negro',
};

const autoAccentLabel = computed(
    () => accentLabels[autoAccent(fadeColor.value)],
);

/*
 * Selectores de fuente: cada opción se muestra con su propia fuente.
 */
const fontStyle = (id: CardFontId, weight?: number) => ({
    fontFamily: `"${CARD_FONTS[id].family}"`,
    fontWeight: weight ?? nearestWeight(id, 400),
});

onMounted(() => {
    cardFontOptions.forEach((font) => {
        void ensureCardFont(font.id, nearestWeight(font.id, 400));
    });
});

const weights = computed(() => CARD_FONTS[titleFontFamily.value].weights);

// Al abrir el selector de peso se cargan todos los pesos de la familia.
const loadFamilyWeights = (open: boolean) => {
    if (open) {
        weights.value.forEach((weight) => {
            void ensureCardFont(titleFontFamily.value, weight);
        });
    }
};

/*
 * La fuente se carga antes de aplicarla: así el selector, los botones de
 * tamaño y la tarjeta cambian a la vez, sin mostrar antes una fuente genérica.
 */
let fontSelection = 0;

const applyFont = async (family: CardFontId, weight: number) => {
    const selection = ++fontSelection;

    await ensureCardFont(family, weight);

    // Solo se aplica la última selección si se eligieron varias seguidas.
    if (selection === fontSelection) {
        titleFontFamily.value = family;
        titleFontWeight.value = weight;
    }
};

// Al cambiar de familia se conserva el peso más cercano disponible.
const selectFamily = (family: unknown) => {
    const id = family as CardFontId;

    void applyFont(id, nearestWeight(id, titleFontWeight.value));
};

const selectWeight = (weight: unknown) => {
    void applyFont(titleFontFamily.value, Number(weight));
};

const sizeOptions = Object.entries(TITLE_SIZES) as [
    TitleSize,
    (typeof TITLE_SIZES)[TitleSize],
][];

/*
 * Mismo aspecto que .form-select (las utilidades de Tailwind ganan a la clase).
 */
const triggerClass =
    "h-auto min-h-11 w-full cursor-pointer rounded-xl border-[#C8DCCF] bg-[#FCFEFC] px-4 text-base text-[#244334] shadow-none hover:border-[#A9CBB5] focus-visible:border-[#187744] focus-visible:ring-2 focus-visible:ring-[#187744]/10 data-[state=open]:border-[#187744] dark:bg-[#FCFEFC] dark:hover:bg-[#FCFEFC] [&_svg]:opacity-100 [&_svg:not([class*='text-'])]:text-[#718477]";

/*
 * El componente Select usa los colores del tema (oscuros en modo oscuro);
 * el formulario del panel usa siempre colores claros fijos.
 */
const contentClass =
    'rounded-xl border-[#C8DCCF] bg-[#FCFEFC] text-[#244334] shadow-md';

const itemClass =
    "cursor-pointer text-base focus:bg-[#EAF5EE] focus:text-[#187744] [&_svg:not([class*='text-'])]:text-[#187744]";
</script>

<template>
    <AdminCard
        title="Diseño de la tarjeta"
        description="Colores de la plantilla y fuente del nombre de la especie."
        :icon="Palette"
    >
        <div class="space-y-5">
            <div class="form-grid">
                <!-- Color del degradado -->
                <div class="form-field">
                    <label for="fade_color_text" class="form-label">
                        Color del degradado
                        <span class="text-red-500">*</span>
                    </label>

                    <div class="flex items-center gap-3">
                        <input
                            id="fade_color"
                            v-model="fadeColor"
                            type="color"
                            class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-[#DCE7DF] bg-white p-1"
                            aria-label="Seleccionar color del degradado"
                        />

                        <input
                            id="fade_color_text"
                            v-model="fadeColorText"
                            type="text"
                            maxlength="7"
                            placeholder="#4f6b5a"
                            class="form-input font-mono uppercase"
                            :class="{
                                'form-input-error': errors.fade_color,
                            }"
                            @input="onFadeColorText"
                        />
                    </div>

                    <!-- Colores sugeridos -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="preset in DEFAULT_FADE_PRESETS"
                            :key="preset.color"
                            type="button"
                            :title="preset.name"
                            :aria-label="preset.name"
                            :aria-pressed="fadeColor === preset.color"
                            class="h-7 w-7 rounded-full border border-black/10 transition hover:scale-110"
                            :class="{
                                'ring-2 ring-[#187744] ring-offset-2':
                                    fadeColor === preset.color,
                            }"
                            :style="{ backgroundColor: preset.color }"
                            @click="fadeColor = preset.color"
                        />
                    </div>

                    <p v-if="errors.fade_color" class="form-error">
                        {{ errors.fade_color }}
                    </p>
                </div>

                <!-- Color de acento -->
                <div class="form-field">
                    <label for="accent_color" class="form-label">
                        Color de acento
                    </label>

                    <div class="form-select-wrapper">
                        <select
                            id="accent_color"
                            v-model="accentColor"
                            class="form-select appearance-none"
                            :class="{
                                'form-input-error': errors.accent_color,
                            }"
                        >
                            <option :value="null">
                                Automático ({{ autoAccentLabel }})
                            </option>
                            <option value="white">Blanco</option>
                            <option value="black">Negro</option>
                        </select>

                        <ChevronDown :size="16" class="form-select-icon" />
                    </div>

                    <p class="form-help">
                        Logo, nombres y estadísticas. En automático se elige el
                        más legible sobre el degradado.
                    </p>

                    <p v-if="errors.accent_color" class="form-error">
                        {{ errors.accent_color }}
                    </p>
                </div>
            </div>

            <div class="form-grid">
                <!-- Fuente del nombre -->
                <div class="form-field">
                    <label for="title_font_family" class="form-label">
                        Fuente del nombre
                        <span class="text-red-500">*</span>
                    </label>

                    <Select
                        :model-value="titleFontFamily"
                        @update:model-value="selectFamily"
                    >
                        <SelectTrigger
                            id="title_font_family"
                            :class="triggerClass"
                            :aria-invalid="Boolean(errors.title_font_family)"
                        >
                            <SelectValue>
                                <span :style="fontStyle(titleFontFamily)">
                                    {{ CARD_FONTS[titleFontFamily].family }}
                                </span>
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent :class="contentClass">
                            <SelectItem
                                v-for="font in cardFontOptions"
                                :key="font.id"
                                :value="font.id"
                                :class="itemClass"
                                :style="fontStyle(font.id)"
                            >
                                {{ font.family }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p v-if="errors.title_font_family" class="form-error">
                        {{ errors.title_font_family }}
                    </p>
                </div>

                <!-- Peso -->
                <div class="form-field">
                    <label for="title_font_weight" class="form-label">
                        Peso
                        <span class="text-red-500">*</span>
                    </label>

                    <Select
                        :model-value="titleFontWeight"
                        @update:model-value="selectWeight"
                        @update:open="loadFamilyWeights"
                    >
                        <SelectTrigger
                            id="title_font_weight"
                            :class="triggerClass"
                            :aria-invalid="Boolean(errors.title_font_weight)"
                        >
                            <SelectValue>
                                <span
                                    :style="
                                        fontStyle(
                                            titleFontFamily,
                                            titleFontWeight,
                                        )
                                    "
                                >
                                    {{ titleFontWeight }} ·
                                    {{ WEIGHT_LABELS[titleFontWeight] }}
                                </span>
                            </SelectValue>
                        </SelectTrigger>

                        <SelectContent :class="contentClass">
                            <SelectItem
                                v-for="weight in weights"
                                :key="weight"
                                :value="weight"
                                :class="itemClass"
                                :style="fontStyle(titleFontFamily, weight)"
                            >
                                {{ weight }} · {{ WEIGHT_LABELS[weight] }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <p v-if="errors.title_font_weight" class="form-error">
                        {{ errors.title_font_weight }}
                    </p>
                </div>
            </div>

            <!-- Tamaño -->
            <div class="form-field">
                <span class="form-label">
                    Tamaño del nombre
                    <span class="text-red-500">*</span>
                </span>

                <div
                    role="radiogroup"
                    aria-label="Tamaño del nombre"
                    class="grid grid-cols-4 gap-2"
                >
                    <button
                        v-for="[value, size] in sizeOptions"
                        :key="value"
                        type="button"
                        role="radio"
                        :aria-checked="titleFontSize === value"
                        class="flex h-20 flex-col items-center justify-end gap-1 rounded-xl border px-2 pb-2 transition-colors"
                        :class="
                            titleFontSize === value
                                ? 'border-[#187744] bg-[#EAF5EE] text-[#187744]'
                                : 'border-[#C8DCCF] bg-[#FCFEFC] text-[#435D4C] hover:border-[#A9CBB5]'
                        "
                        @click="titleFontSize = value"
                    >
                        <span
                            class="leading-none"
                            :style="{
                                ...fontStyle(titleFontFamily, titleFontWeight),
                                fontSize: `${size.previewPx}px`,
                            }"
                        >
                            Aa
                        </span>
                        <span class="text-xs font-semibold">
                            {{ size.label }}
                        </span>
                    </button>
                </div>

                <p class="form-help">
                    {{ TITLE_SIZES[titleFontSize].hint }}
                </p>

                <p v-if="errors.title_font_size" class="form-error">
                    {{ errors.title_font_size }}
                </p>
            </div>
        </div>
    </AdminCard>
</template>
