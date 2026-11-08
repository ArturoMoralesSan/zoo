/*
 * Línea de crédito (TASL: título, autor, origen, licencia) lista para mostrar.
 *
 * Copia de app/Services/Licensing/AttributionBuilder.php para la vista previa
 * del panel; ambos se prueban con tests/Fixtures/credits/attribution-cases.json.
 *
 *   [Basado en: | Incluye: ]“Título” de Autor, vía Origen, LICENCIA, con cambios: nota
 *
 * Un recurso hecho a partir de otro de la misma tarjeta lo dice en la misma
 * línea ("a partir del modelo 3D de la tarjeta"); el crédito del otro recurso
 * es su propia fila.
 */

import type { AssetSourceValue, AssetSourceOption } from './types';

export interface AttributionLicense {
    slug?: string | null;
    short_name?: string | null;
    is_proprietary?: boolean | null;
    attribution_template?: string | null;
}

export interface AttributionInput {
    role?: string | null;
    title?: string | null;
    author_name?: string | null;
    // Nombre del origen para el crédito (null si no aporta).
    source?: string | null;
    modified?: boolean | null;
    modification_note?: string | null;
    attribution_override?: string | null;
    based_on_asset?: string | null;
    license?: AttributionLicense | null;
}

const PROPRIETARY_LABELS: Record<string, string> = {
    proprietary: 'obra propia',
    'proprietary-commissioned': 'obra por encargo',
};

const PRIVATE_LICENSE_LABEL = 'licencia privada';

// "A partir de…" según el recurso de la tarjeta en que se basa.
export const BASIS: Record<string, string> = {
    artwork: 'a partir de la ilustración de la tarjeta',
    thumbnail: 'a partir de la miniatura de la tarjeta',
    model: 'a partir del modelo 3D de la tarjeta',
};

// Prefijo por papel del crédito (el origen no lleva).
const ROLE_PREFIXES: Record<string, string> = {
    based_on: 'Basado en: ',
    component: 'Incluye: ',
};

// Marcador de las plantillas donde va el texto de BASIS (con un espacio antes).
const BASIS_PLACEHOLDER = '{basis}';

const clean = (value: string | null | undefined): string =>
    (value ?? '').trim();

const join = (parts: string[]): string =>
    parts.filter((part) => part !== '').join(', ');

/*
 * Mayúscula inicial en las líneas que empiezan con texto fijo ("obra
 * propia", "a partir de…"); los nombres de autor no se tocan.
 */
const FIXED_STARTS = [
    ...Object.values(BASIS),
    ...Object.values(PROPRIETARY_LABELS),
    PRIVATE_LICENSE_LABEL,
];

const capitalize = (line: string): string =>
    FIXED_STARTS.some((text) => line.startsWith(text))
        ? line.charAt(0).toUpperCase() + line.slice(1)
        : line;

const fillTemplate = (template: string, basis: string): string => {
    if (template.includes(BASIS_PLACEHOLDER)) {
        return template.replace(
            BASIS_PLACEHOLDER,
            basis !== '' ? ` ${basis}` : '',
        );
    }

    return basis !== '' ? `${template} (${basis})` : template;
};

// Línea sin el prefijo del papel.
function line(credit: AttributionInput): string {
    const license = credit.license ?? {};
    const basis = BASIS[credit.based_on_asset ?? ''] ?? '';

    const template = clean(license.attribution_template);

    if (template !== '') {
        return fillTemplate(template, basis);
    }

    const title = clean(credit.title);
    const author = clean(credit.author_name);
    const source = clean(credit.source);

    let work = author;

    if (title !== '') {
        work = author !== '' ? `“${title}” de ${author}` : `“${title}”`;
    }

    const parts = [work, source !== '' ? `vía ${source}` : '', basis];

    if (license.is_proprietary) {
        const label =
            PROPRIETARY_LABELS[license.slug ?? ''] ?? PRIVATE_LICENSE_LABEL;

        const joined = join(parts);

        return joined === ''
            ? capitalize(label)
            : `${capitalize(joined)} – ${label}`;
    }

    const note = clean(credit.modification_note);

    parts.push(clean(license.short_name));

    if (credit.modified) {
        parts.push(note !== '' ? `con cambios: ${note}` : 'con cambios');
    }

    return capitalize(join(parts));
}

export function buildAttribution(credit: AttributionInput): string {
    const override = clean(credit.attribution_override);

    if (override !== '') {
        return override;
    }

    return (ROLE_PREFIXES[credit.role ?? ''] ?? '') + line(credit);
}

/**
 * Nombre del origen en el crédito ("vía …"): propio, "otro" e IA no aportan
 * nada (en la IA lo dice la licencia).
 */
export function creditSourceName(
    source: AssetSourceValue | null,
    options: AssetSourceOption[],
): string | null {
    if (!source || source === 'own' || source === 'other' || source === 'ai') {
        return null;
    }

    return options.find((option) => option.value === source)?.label ?? null;
}
