/*
 * Línea de crédito (TASL: título, autor, origen, licencia) lista para mostrar.
 *
 * Copia de app/Services/Licensing/AttributionBuilder.php para la vista previa
 * del panel; ambos se prueban con tests/Fixtures/credits/attribution-cases.json.
 *
 *   “Título” de Autor, vía Origen, LICENCIA, con cambios: nota
 *
 * Un modelo generado a partir de la ilustración de la tarjeta lo dice en la
 * misma línea; el crédito de la ilustración es su propia fila.
 */

import type { AssetSourceValue, AssetSourceOption } from './types';

export interface AttributionLicense {
    slug?: string | null;
    short_name?: string | null;
    is_proprietary?: boolean | null;
    attribution_template?: string | null;
}

export interface AttributionInput {
    title?: string | null;
    author_name?: string | null;
    // Nombre del origen para el crédito (null si no aporta).
    source?: string | null;
    modified?: boolean | null;
    modification_note?: string | null;
    attribution_override?: string | null;
    based_on_artwork?: boolean | null;
    license?: AttributionLicense | null;
}

const PROPRIETARY_LABELS: Record<string, string> = {
    proprietary: 'obra propia',
    'proprietary-commissioned': 'obra por encargo',
};

const PRIVATE_LICENSE_LABEL = 'licencia privada';

export const BASIS = 'a partir de la ilustración de la tarjeta';

// Marcador de las plantillas donde va BASIS (con un espacio antes).
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
    BASIS,
    ...Object.values(PROPRIETARY_LABELS),
    PRIVATE_LICENSE_LABEL,
];

const capitalize = (line: string): string =>
    FIXED_STARTS.some((text) => line.startsWith(text))
        ? line.charAt(0).toUpperCase() + line.slice(1)
        : line;

const fillTemplate = (template: string, basedOnArtwork: boolean): string => {
    if (template.includes(BASIS_PLACEHOLDER)) {
        return template.replace(
            BASIS_PLACEHOLDER,
            basedOnArtwork ? ` ${BASIS}` : '',
        );
    }

    return basedOnArtwork ? `${template} (${BASIS})` : template;
};

export function buildAttribution(credit: AttributionInput): string {
    const override = clean(credit.attribution_override);

    if (override !== '') {
        return override;
    }

    const license = credit.license ?? {};
    const basedOnArtwork = Boolean(credit.based_on_artwork);

    const template = clean(license.attribution_template);

    if (template !== '') {
        return fillTemplate(template, basedOnArtwork);
    }

    const title = clean(credit.title);
    const author = clean(credit.author_name);
    const source = clean(credit.source);

    let work = author;

    if (title !== '') {
        work = author !== '' ? `“${title}” de ${author}` : `“${title}”`;
    }

    const parts = [
        work,
        source !== '' ? `vía ${source}` : '',
        basedOnArtwork ? BASIS : '',
    ];

    if (license.is_proprietary) {
        const label =
            PROPRIETARY_LABELS[license.slug ?? ''] ?? PRIVATE_LICENSE_LABEL;

        const line = join(parts);

        return line === ''
            ? capitalize(label)
            : `${capitalize(line)} – ${label}`;
    }

    const note = clean(credit.modification_note);

    parts.push(clean(license.short_name));

    if (credit.modified) {
        parts.push(note !== '' ? `con cambios: ${note}` : 'con cambios');
    }

    return capitalize(join(parts));
}

/**
 * Nombre del origen en el crédito ("vía …"): propio y "otro" no aportan nada.
 */
export function creditSourceName(
    source: AssetSourceValue | null,
    options: AssetSourceOption[],
): string | null {
    if (!source || source === 'own' || source === 'other') {
        return null;
    }

    return options.find((option) => option.value === source)?.label ?? null;
}
