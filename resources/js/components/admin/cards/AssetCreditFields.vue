<script setup lang="ts">
import {
    AlertTriangle,
    ChevronDown,
    Copyright,
    Download,
    ExternalLink,
    Info,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { buildAttribution, creditSourceName } from '@/lib/credits/attribution';
import {
    detectSource,
    isCommonsFileUrl,
    isWikipediaFileUrl,
} from '@/lib/credits/sources';
import type {
    AssetSourceOption,
    AssetSourceValue,
    CreditAsset,
    CreditForm,
    CreditRole,
    License,
} from '@/lib/credits/types';
import admin from '@/routes/admin';

/*
 * Origen, licencia y datos de atribución de un crédito (sección "Créditos"
 * de la tarjeta). El papel (origen, basado en, incluye) y el recurso los
 * elige la página.
 */
const props = withDefaults(
    defineProps<{
        asset: CreditAsset;
        role: CreditRole;
        licenses: License[];
        sources: AssetSourceOption[];
        allowRestricted?: boolean;
        errors: Record<string, string | undefined>;
        // Licencia guardada (se muestra aunque ya no sea seleccionable).
        storedLicenseId?: number | null;
    }>(),
    {
        allowRestricted: false,
        storedLicenseId: null,
    },
);

const credit = defineModel<CreditForm>({ required: true });

const id = (field: string) => `credit_${field}`;

const error = (field: string) => props.errors[field];

const SOURCE_HINTS: Record<AssetSourceValue, string> = {
    own: 'Creado por el zoológico',
    pexels: 'pexels.com',
    unsplash: 'unsplash.com',
    pixabay: 'pixabay.com',
    wikimedia_commons: 'commons.wikimedia.org',
    meshy: 'Modelo generado con IA',
    sketchfab: 'sketchfab.com (CC)',
    poly_pizza: 'poly.pizza (CC0 / CC BY)',
    poly_haven: 'polyhaven.com (CC0)',
    ai: 'Firefly, ChatGPT, Gemini…',
    other: 'Otro sitio o autor (CC)',
};

const STOCK_SOURCES: AssetSourceValue[] = ['pexels', 'unsplash', 'pixabay'];

const isOwn = computed(() => credit.value.source_name === 'own');

const isAi = computed(() => credit.value.source_name === 'ai');

// Propio e IA no tienen autor ni página de origen que citar.
const showsAuthor = computed(() => !isOwn.value && !isAi.value);

const SOURCE_LABELS: Record<CreditRole, string> = {
    origin: 'Origen',
    based_on: '¿De dónde es la obra en que se basa?',
    component: '¿De dónde es el material incluido?',
};

const license = computed(
    () =>
        props.licenses.find((item) => item.id === credit.value.license_id) ??
        null,
);

/*
 * Orígenes de este recurso y papel: como origen, Pexels, Unsplash y Pixabay
 * solo para imágenes y Meshy solo para modelos; como "Basado en" o
 * "Incluye", cualquiera.
 */
const sourceOptions = computed(() =>
    props.sources.filter((option) =>
        option.assets[props.role].includes(props.asset),
    ),
);

const currentSource = computed(
    () =>
        sourceOptions.value.find(
            (option) => option.value === credit.value.source_name,
        ) ?? null,
);

/*
 * Licencias que usa la plataforma elegida (ver AssetSource::allowsLicense).
 */
const licenseOptions = computed(() =>
    props.licenses.filter(
        (item) =>
            (currentSource.value?.licenses.includes(item.slug) ?? false) &&
            (item.is_selectable ||
                props.allowRestricted ||
                item.id === props.storedLicenseId),
    ),
);

const licenseBySlug = (slug: string) =>
    props.licenses.find((item) => item.slug === slug) ?? null;

/*
 * Al cambiar el origen se quita una licencia que no le corresponde. Si la
 * plataforma tiene una sola licencia (Pexels, Unsplash, Pixabay), se elige;
 * "Propio" preselecciona obra propia.
 */
const selectSource = (source: AssetSourceValue) => {
    credit.value.source_name = source;

    const options = licenseOptions.value;

    if (options.some((item) => item.id === credit.value.license_id)) {
        return;
    }

    credit.value.license_id =
        source === 'own'
            ? (licenseBySlug('proprietary')?.id ?? null)
            : options.length === 1
              ? options[0].id
              : null;
};

/*
 * Al escribir la URL se sugiere el origen y la licencia, solo si el
 * administrador aún no los eligió.
 */
watch(
    () => credit.value.source_url,
    (url) => {
        const detected = detectSource(url);

        if (!detected) {
            return;
        }

        const allowed = sourceOptions.value.some(
            (option) => option.value === detected.source,
        );

        if (!allowed) {
            return;
        }

        if (credit.value.source_name === null) {
            credit.value.source_name = detected.source;
        }

        if (
            credit.value.license_id === null &&
            detected.licenseSlug &&
            credit.value.source_name === detected.source
        ) {
            credit.value.license_id =
                licenseBySlug(detected.licenseSlug)?.id ?? null;
        }
    },
);

const isCommons = computed(
    () => credit.value.source_name === 'wikimedia_commons',
);

const wikipediaUrl = computed(
    () => isCommons.value && isWikipediaFileUrl(credit.value.source_url),
);

/*
 * Autocompletar desde Wikimedia Commons. Solo llena el formulario.
 */
const lookingUp = ref(false);
const lookupWarnings = ref<string[]>([]);

const lookupCommons = async () => {
    lookingUp.value = true;
    lookupWarnings.value = [];

    try {
        const response = await fetch(admin.credits.commonsLookup().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({ url: credit.value.source_url }),
        });

        const body = await response.json();

        if (!response.ok) {
            throw new Error(
                body.message ?? 'No se pudieron obtener los datos.',
            );
        }

        const data = body.data;

        credit.value.title = data.title ?? credit.value.title;
        credit.value.author_name = data.author_name ?? credit.value.author_name;
        credit.value.author_url = data.author_url ?? credit.value.author_url;
        credit.value.source_url = data.source_url ?? credit.value.source_url;

        if (data.license_id) {
            credit.value.license_id = data.license_id;
        }

        lookupWarnings.value = data.warnings ?? [];
    } catch (exception) {
        lookupWarnings.value = [
            exception instanceof Error
                ? exception.message
                : 'No se pudieron obtener los datos.',
        ];
    } finally {
        lookingUp.value = false;
    }
};

const needsAttribution = computed(
    () =>
        Boolean(license.value?.requires_attribution) &&
        !license.value?.attribution_template,
);

/*
 * Vista previa del crédito tal como lo mostrará la app (todos los créditos
 * de las tarjetas activas se publican, también el contenido propio).
 */
const preview = computed(() =>
    license.value
        ? buildAttribution({
              ...credit.value,
              role: props.role,
              based_on_asset:
                  props.role === 'origin' ? credit.value.based_on_asset : null,
              source: creditSourceName(credit.value.source_name, props.sources),
              license: license.value,
          })
        : null,
);

/*
 * Revisión de una imagen generada con IA: se guarda solo si se confirman
 * los tres puntos.
 */
const AI_CHECKS = [
    'No reproduce personas reales, personajes conocidos, marcas ni logotipos.',
    'No imita el estilo de un artista vivo en particular.',
    'Revisé que el animal se vea correcto (la app es educativa).',
];

const aiChecks = ref(AI_CHECKS.map(() => credit.value.ai_checked));

watch(
    aiChecks,
    (checks) => {
        credit.value.ai_checked = checks.every(Boolean);
    },
    { deep: true },
);
</script>

<template>
    <div class="rounded-xl border border-[#DCE7DF] bg-[#FCFEFC] p-4 sm:p-5">
        <div class="mb-4 flex items-start gap-3">
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E2F3E8] text-[#187744]"
            >
                <Copyright :size="18" />
            </div>

            <div class="min-w-0">
                <p class="text-sm font-semibold text-[#244334]">
                    Licencia y datos del crédito
                </p>
                <p class="text-xs text-[#718477]">
                    De dónde viene, con qué licencia y cómo se atribuye en la
                    app.
                </p>
            </div>
        </div>

        <div class="space-y-5">
            <!-- Origen -->
            <div class="form-field">
                <span class="form-label">
                    {{ SOURCE_LABELS[role] }}
                    <span class="form-required">*</span>
                </span>

                <div
                    class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
                    role="radiogroup"
                    :aria-labelledby="id('source_name')"
                >
                    <label
                        v-for="option in sourceOptions"
                        :key="option.value"
                        class="flex cursor-pointer flex-col rounded-xl border px-3 py-2.5 transition"
                        :class="
                            credit.source_name === option.value
                                ? 'border-[#187744] bg-[#F2F7F4] text-[#187744]'
                                : 'border-[#C8DCCF] bg-white text-[#435D4C] hover:border-[#A9CBB5]'
                        "
                    >
                        <input
                            type="radio"
                            class="sr-only"
                            :name="id('source_name')"
                            :value="option.value"
                            :checked="credit.source_name === option.value"
                            @change="selectSource(option.value)"
                        />
                        <span class="text-sm font-semibold">
                            {{ option.label }}
                        </span>
                        <span class="text-xs text-[#718477]">
                            {{ SOURCE_HINTS[option.value] }}
                        </span>
                    </label>
                </div>

                <p v-if="error('source_name')" class="form-error">
                    {{ error('source_name') }}
                </p>
            </div>

            <!-- URL de origen -->
            <div v-if="showsAuthor" class="form-field">
                <label :for="id('source_url')" class="form-label">
                    URL de origen
                    <span v-if="needsAttribution" class="form-required">*</span>
                    <span v-else class="text-xs font-normal text-[#84958A]">
                        (opcional)
                    </span>
                </label>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        :id="id('source_url')"
                        v-model.trim="credit.source_url"
                        type="url"
                        inputmode="url"
                        placeholder="https://commons.wikimedia.org/wiki/File:…"
                        class="form-input min-w-0 flex-1"
                    />

                    <button
                        v-if="isCommons"
                        type="button"
                        :disabled="
                            lookingUp || !isCommonsFileUrl(credit.source_url)
                        "
                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#C8DCCF] bg-white px-4 py-2.5 text-sm font-medium text-[#187744] transition hover:bg-[#F2F7F4] disabled:cursor-not-allowed disabled:opacity-50"
                        @click="lookupCommons"
                    >
                        <Download :size="16" />
                        {{ lookingUp ? 'Consultando…' : 'Obtener datos' }}
                    </button>
                </div>

                <p v-if="isCommons" class="form-help">
                    Usa la página del archivo en Commons. "Obtener datos" solo
                    llena el formulario: revisa los datos antes de guardar.
                </p>

                <p v-if="wikipediaUrl" class="form-error">
                    Es una página de archivo de Wikipedia, no de Commons: puede
                    ser un archivo no libre (uso legítimo / fair use). Busca el
                    archivo en commons.wikimedia.org.
                </p>

                <p v-if="error('source_url')" class="form-error">
                    {{ error('source_url') }}
                </p>
            </div>

            <Alert
                v-if="lookupWarnings.length"
                class="border-amber-200 bg-amber-50 text-amber-900"
            >
                <AlertTriangle />
                <AlertTitle>Revisa los datos de Commons</AlertTitle>
                <AlertDescription class="text-amber-800">
                    <p v-for="warning in lookupWarnings" :key="warning">
                        {{ warning }}
                    </p>
                </AlertDescription>
            </Alert>

            <!-- Licencia -->
            <div class="form-field">
                <label :for="id('license_id')" class="form-label">
                    Licencia <span class="form-required">*</span>
                </label>

                <div class="form-select-wrapper">
                    <select
                        :id="id('license_id')"
                        v-model="credit.license_id"
                        class="form-select appearance-none"
                        :disabled="!credit.source_name"
                    >
                        <option :value="null">
                            {{
                                credit.source_name
                                    ? 'Selecciona una licencia'
                                    : 'Primero elige el origen'
                            }}
                        </option>

                        <option
                            v-for="option in licenseOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.short_name }} — {{ option.summary }}
                        </option>
                    </select>

                    <ChevronDown :size="16" class="form-select-icon" />
                </div>

                <div
                    v-if="license"
                    class="mt-2 flex flex-wrap items-start justify-between gap-2 rounded-lg border border-[#DCE7DF] bg-white p-3 text-xs text-[#435D4C]"
                >
                    <p class="min-w-0 flex-1">
                        <span class="font-semibold">{{ license.name }}.</span>
                        {{ license.summary }}
                    </p>

                    <a
                        v-if="license.url"
                        :href="license.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex shrink-0 items-center gap-1 font-medium text-[#187744] hover:underline"
                    >
                        Ver licencia
                        <ExternalLink :size="12" />
                    </a>
                </div>

                <p v-if="error('license_id')" class="form-error">
                    {{ error('license_id') }}
                </p>
            </div>

            <!-- Avisos según la licencia y el origen -->
            <Alert
                v-if="license?.share_alike"
                class="border-sky-200 bg-sky-50 text-sky-900"
            >
                <Info />
                <AlertTitle>Licencia CompartirIgual</AlertTitle>
                <AlertDescription class="text-sky-800">
                    <p>
                        El recurso de la tarjeta hecho con esta obra es una obra
                        derivada: debe compartirse con la misma licencia ({{
                            license.short_name
                        }}) y con atribución.
                    </p>

                    <label class="mt-2 flex cursor-pointer items-start gap-2">
                        <input
                            v-model="credit.share_alike_ack"
                            type="checkbox"
                            class="mt-0.5 accent-[#187744]"
                        />
                        <span class="font-medium">
                            Entiendo que la tarjeta resultante debe compartirse
                            con la misma licencia
                        </span>
                    </label>

                    <p v-if="error('share_alike_ack')" class="form-error">
                        {{ error('share_alike_ack') }}
                    </p>
                </AlertDescription>
            </Alert>

            <Alert
                v-if="
                    credit.source_name &&
                    STOCK_SOURCES.includes(credit.source_name)
                "
                class="border-amber-200 bg-amber-50 text-amber-900"
            >
                <AlertTriangle />
                <AlertTitle>Personas, marcas y logotipos</AlertTitle>
                <AlertDescription class="text-amber-800">
                    La licencia no cubre a las personas reconocibles ni las
                    marcas o logotipos que aparezcan en la imagen. Evita usar
                    estas imágenes si muestran alguno.
                </AlertDescription>
            </Alert>

            <Alert
                v-if="license?.slug === 'meshy-free'"
                class="border-sky-200 bg-sky-50 text-sky-900"
            >
                <Info />
                <AlertTitle>Plan gratuito de Meshy</AlertTitle>
                <AlertDescription class="text-sky-800">
                    Los modelos del plan gratuito son CC BY 4.0: se pueden usar
                    en el juego siempre que el crédito a Meshy aparezca en la
                    app (ver la vista previa abajo).
                </AlertDescription>
            </Alert>

            <Alert
                v-if="
                    credit.source_name === 'sketchfab' ||
                    credit.source_name === 'poly_pizza'
                "
                class="border-amber-200 bg-amber-50 text-amber-900"
            >
                <AlertTriangle />
                <AlertTitle>Revisa la licencia de este modelo</AlertTitle>
                <AlertDescription class="text-amber-800">
                    Cada autor elige la licencia de su modelo. Usa la que
                    aparece en la página del modelo (no la del título ni la
                    descripción) y copia el nombre del autor tal cual.
                </AlertDescription>
            </Alert>

            <!-- Imagen generada con IA -->
            <div
                v-if="isAi"
                class="space-y-4 rounded-xl border border-violet-200 bg-violet-50/50 p-4"
            >
                <p
                    class="flex items-center gap-2 text-sm font-semibold text-violet-900"
                >
                    <Sparkles :size="16" />
                    Imagen generada con IA
                </p>

                <p class="text-xs text-violet-900">
                    Una imagen hecha solo con IA probablemente no tiene derechos
                    de autor: nadie la posee en exclusiva y otros podrían
                    copiarla. Los retoques hechos a mano sí se protegen:
                    descríbelos en "La imagen fue modificada".
                </p>

                <div class="form-field">
                    <label :for="id('ai_prompt')" class="form-label">
                        Indicación (prompt)
                        <span class="text-xs font-normal text-[#84958A]">
                            (opcional, solo el panel)
                        </span>
                    </label>

                    <textarea
                        :id="id('ai_prompt')"
                        v-model="credit.ai_prompt"
                        rows="3"
                        class="form-input"
                        placeholder="Texto usado para generar la imagen; sirve como registro."
                    />

                    <p v-if="error('ai_prompt')" class="form-error">
                        {{ error('ai_prompt') }}
                    </p>
                </div>

                <fieldset class="form-field">
                    <legend class="form-label">
                        Revisión <span class="form-required">*</span>
                    </legend>

                    <label
                        v-for="(check, index) in AI_CHECKS"
                        :key="check"
                        class="flex cursor-pointer items-start gap-2 text-sm text-[#244334]"
                    >
                        <input
                            v-model="aiChecks[index]"
                            type="checkbox"
                            class="mt-0.5 accent-[#187744]"
                        />
                        {{ check }}
                    </label>

                    <p v-if="error('ai_checked')" class="form-error">
                        {{ error('ai_checked') }}
                    </p>
                </fieldset>
            </div>

            <!-- Título, autor -->
            <div class="form-grid">
                <div class="form-field">
                    <label :for="id('title')" class="form-label">
                        Título
                        <span class="text-xs font-normal text-[#84958A]">
                            (opcional)
                        </span>
                    </label>

                    <input
                        :id="id('title')"
                        v-model="credit.title"
                        type="text"
                        placeholder="Ej. Jaguar descansando"
                        class="form-input"
                    />

                    <p v-if="error('title')" class="form-error">
                        {{ error('title') }}
                    </p>
                </div>

                <div v-if="showsAuthor" class="form-field">
                    <label :for="id('author_name')" class="form-label">
                        Autor
                        <span v-if="needsAttribution" class="form-required"
                            >*</span
                        >
                        <span v-else class="text-xs font-normal text-[#84958A]">
                            (opcional)
                        </span>
                    </label>

                    <input
                        :id="id('author_name')"
                        v-model="credit.author_name"
                        type="text"
                        placeholder="Nombre o usuario del autor"
                        class="form-input"
                    />

                    <p v-if="error('author_name')" class="form-error">
                        {{ error('author_name') }}
                    </p>
                </div>

                <div v-if="showsAuthor" class="form-field">
                    <label :for="id('author_url')" class="form-label">
                        URL del autor
                        <span class="text-xs font-normal text-[#84958A]">
                            (opcional)
                        </span>
                    </label>

                    <input
                        :id="id('author_url')"
                        v-model.trim="credit.author_url"
                        type="url"
                        placeholder="https://…"
                        class="form-input"
                    />

                    <p v-if="error('author_url')" class="form-error">
                        {{ error('author_url') }}
                    </p>
                </div>

                <!-- Fecha de obtención -->
                <div class="form-field">
                    <label :for="id('acquired_at')" class="form-label">
                        Fecha de obtención
                        <span class="text-xs font-normal text-[#84958A]">
                            (opcional)
                        </span>
                    </label>

                    <input
                        :id="id('acquired_at')"
                        v-model="credit.acquired_at"
                        type="date"
                        class="form-input"
                    />

                    <p v-if="error('acquired_at')" class="form-error">
                        {{ error('acquired_at') }}
                    </p>
                </div>
            </div>

            <!-- Modificada -->
            <div class="form-field">
                <label
                    class="flex cursor-pointer items-center gap-2 text-sm font-medium text-[#244334]"
                >
                    <input
                        v-model="credit.modified"
                        type="checkbox"
                        class="accent-[#187744]"
                    />
                    {{
                        role !== 'origin'
                            ? 'La obra se modificó al usarla'
                            : asset === 'model'
                              ? 'El modelo fue modificado'
                              : 'La imagen fue modificada'
                    }}
                </label>

                <input
                    v-if="credit.modified"
                    :id="id('modification_note')"
                    v-model="credit.modification_note"
                    type="text"
                    placeholder="Qué se cambió"
                    class="form-input mt-2"
                />

                <p class="form-help">
                    Las licencias Creative Commons piden indicar los cambios.
                </p>

                <p v-if="error('modification_note')" class="form-error">
                    {{ error('modification_note') }}
                </p>
            </div>

            <!-- Opciones avanzadas -->
            <details class="group rounded-lg border border-[#DCE7DF] bg-white">
                <summary
                    class="cursor-pointer px-3 py-2.5 text-sm font-medium text-[#435D4C]"
                >
                    Opciones avanzadas
                </summary>

                <div class="space-y-4 border-t border-[#DCE7DF] p-3">
                    <div class="form-field">
                        <label
                            :for="id('attribution_override')"
                            class="form-label"
                        >
                            Texto del crédito personalizado
                            <span class="text-xs font-normal text-[#84958A]">
                                (opcional)
                            </span>
                        </label>

                        <textarea
                            :id="id('attribution_override')"
                            v-model="credit.attribution_override"
                            rows="2"
                            class="form-input"
                            placeholder="Reemplaza la línea generada (p. ej. si el autor pide un texto exacto)"
                        />

                        <p
                            v-if="error('attribution_override')"
                            class="form-error"
                        >
                            {{ error('attribution_override') }}
                        </p>
                    </div>

                    <div class="form-field">
                        <label :for="id('notes')" class="form-label">
                            Notas internas
                            <span class="text-xs font-normal text-[#84958A]">
                                (solo el panel)
                            </span>
                        </label>

                        <textarea
                            :id="id('notes')"
                            v-model="credit.notes"
                            rows="2"
                            class="form-input"
                        />

                        <p v-if="error('notes')" class="form-error">
                            {{ error('notes') }}
                        </p>
                    </div>
                </div>
            </details>

            <!-- Vista previa del crédito -->
            <div
                v-if="preview"
                class="rounded-lg border border-dashed border-[#A9CBB5] bg-white p-3"
            >
                <p class="text-xs font-medium text-[#718477]">
                    Así se verá en los créditos de la app
                </p>
                <p class="mt-1 text-sm text-[#244334]">
                    {{ preview }}
                </p>
            </div>
        </div>
    </div>
</template>
