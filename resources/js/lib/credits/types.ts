/*
 * Créditos de los recursos de las tarjetas (ver app/Models/Credit.php).
 */

export type CreditAsset = 'artwork' | 'thumbnail' | 'model';

/*
 * Papel del crédito: de dónde salió el recurso, obra en que se basa o
 * material de terceros que incluye.
 */
export type CreditRole = 'origin' | 'based_on' | 'component';

export type AssetSourceValue =
    | 'own'
    | 'pexels'
    | 'unsplash'
    | 'pixabay'
    | 'wikimedia_commons'
    | 'meshy'
    | 'sketchfab'
    | 'poly_pizza'
    | 'poly_haven'
    | 'ai'
    | 'other';

/**
 * Origen con los recursos (por papel) y las licencias que admite
 * (AssetSource::formOptions).
 */
export interface AssetSourceOption {
    value: AssetSourceValue;
    label: string;
    assets: Record<CreditRole, CreditAsset[]>;
    // Slugs de las licencias que usa esta plataforma.
    licenses: string[];
}

/**
 * Licencia del catálogo (LicenseSeeder).
 */
export interface License {
    id: number;
    slug: string;
    name: string;
    short_name: string;
    url: string | null;
    requires_attribution: boolean;
    allows_commercial: boolean;
    allows_modification: boolean;
    share_alike: boolean;
    attribution_template: string | null;
    is_selectable: boolean;
    is_proprietary: boolean;
    summary: string;
}

/**
 * Campos del formulario de un crédito.
 */
export interface CreditForm {
    asset: CreditAsset | null;
    role: CreditRole | null;
    source_name: AssetSourceValue | null;
    source_url: string | null;
    license_id: number | null;
    title: string | null;
    author_name: string | null;
    author_url: string | null;
    acquired_at: string | null;
    modified: boolean;
    modification_note: string | null;
    attribution_override: string | null;
    notes: string | null;
    // Solo el origen: otro recurso de la tarjeta del que sale este.
    based_on_asset: CreditAsset | null;
    // Imágenes generadas con IA.
    ai_prompt: string | null;
    ai_checked: boolean;
    // Confirmación de CompartirIgual (no se guarda).
    share_alike_ack: boolean;
}

/**
 * Crédito guardado, tal como lo envía el controlador al formulario de editar.
 */
export interface StoredCredit extends Omit<
    CreditForm,
    'share_alike_ack' | 'ai_checked'
> {
    id: number;
    sort_order: number;
    ai_checked_at: string | null;
}

/**
 * Recurso de la tarjeta para los formularios: si tiene archivo y sus créditos.
 */
export interface CreditAssetOption {
    value: CreditAsset;
    label: string;
    has_file: boolean;
    has_origin: boolean;
    credits: {
        id: number;
        role: CreditRole;
        license_id: number;
        license_short_name: string;
        attribution: string;
    }[];
}

export const CREDIT_ASSETS: { value: CreditAsset; label: string }[] = [
    { value: 'artwork', label: 'Ilustración' },
    { value: 'thumbnail', label: 'Miniatura' },
    { value: 'model', label: 'Modelo 3D' },
];

export const CREDIT_ROLES: {
    value: CreditRole;
    label: string;
    description: string;
}[] = [
    {
        value: 'origin',
        label: 'Origen',
        description:
            'Quién hizo este archivo o de dónde salió. Cada recurso tiene uno.',
    },
    {
        value: 'based_on',
        label: 'Basado en',
        description:
            'Una obra externa usada para crearlo: la foto que se dio a Meshy, un modelo de otro sitio que se renderizó…',
    },
    {
        value: 'component',
        label: 'Incluye',
        description:
            'Material de terceros dentro del recurso: HDRI, texturas, objetos de la escena…',
    },
];

/*
 * Valores por defecto: las imágenes se recortan para la tarjeta y el modelo
 * se escala para la realidad aumentada.
 */
export const DEFAULT_MODIFICATION_NOTES: Record<CreditAsset, string> = {
    artwork: 'Recortada/adaptada para la tarjeta',
    thumbnail: 'Recortada/adaptada para la tarjeta',
    model: 'Escalado para RA',
};

export function emptyCredit(
    asset: CreditAsset | null = null,
    role: CreditRole | null = null,
): CreditForm {
    // Solo el archivo final (origen) se modifica para la tarjeta.
    const modified = asset !== null && role === 'origin';

    return {
        asset,
        role,
        source_name: null,
        source_url: null,
        license_id: null,
        title: null,
        author_name: null,
        author_url: null,
        acquired_at: null,
        modified,
        modification_note:
            modified && asset ? DEFAULT_MODIFICATION_NOTES[asset] : null,
        attribution_override: null,
        notes: null,
        based_on_asset: null,
        ai_prompt: null,
        ai_checked: false,
        share_alike_ack: false,
    };
}

/**
 * Formulario a partir del crédito guardado.
 */
export function creditForm(stored: StoredCredit): CreditForm {
    return {
        asset: stored.asset,
        role: stored.role,
        source_name: stored.source_name,
        source_url: stored.source_url,
        license_id: stored.license_id,
        title: stored.title,
        author_name: stored.author_name,
        author_url: stored.author_url,
        acquired_at: stored.acquired_at,
        modified: stored.modified,
        modification_note: stored.modification_note,
        attribution_override: stored.attribution_override,
        notes: stored.notes,
        based_on_asset: stored.based_on_asset,
        ai_prompt: stored.ai_prompt,
        ai_checked: stored.ai_checked_at !== null,
        share_alike_ack: false,
    };
}

/**
 * Estado de los créditos de un recurso con archivo (formulario y detalle de
 * la tarjeta).
 */
export interface CreditSummaryItem {
    asset: CreditAsset;
    label: string;
    complete: boolean;
    // Línea del crédito de origen, si lo tiene.
    origin: string | null;
    // Créditos "Basado en" e "Incluye".
    extra_count: number;
}
