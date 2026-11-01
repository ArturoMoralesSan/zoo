/*
 * Créditos de los recursos de las tarjetas (ver app/Models/AssetCredit.php).
 */

export type CreditAsset = 'artwork' | 'thumbnail' | 'model';

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
    | 'other';

/**
 * Origen con los recursos y las licencias que admite (AssetSource::formOptions).
 */
export interface AssetSourceOption {
    value: AssetSourceValue;
    label: string;
    assets: CreditAsset[];
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
 * Campos del crédito en el formulario de la tarjeta: credits[asset][campo].
 */
export interface CreditForm {
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
    // Confirmación de CompartirIgual (no se guarda).
    share_alike_ack: boolean;
    // Solo el modelo: generado a partir de la ilustración de la tarjeta.
    based_on_artwork: boolean;
}

/**
 * Crédito guardado, tal como lo envía el controlador.
 */
export interface StoredCredit extends Omit<CreditForm, 'share_alike_ack'> {
    id: number;
    license_short_name: string;
    attribution: string;
}

export type CardCredits = Partial<Record<CreditAsset, StoredCredit>>;

export type CreditForms = Record<CreditAsset, CreditForm>;

export const CREDIT_ASSETS: { value: CreditAsset; label: string }[] = [
    { value: 'artwork', label: 'Ilustración' },
    { value: 'thumbnail', label: 'Miniatura' },
    { value: 'model', label: 'Modelo 3D' },
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

export function emptyCredit(asset: CreditAsset, modified = true): CreditForm {
    return {
        source_name: null,
        source_url: null,
        license_id: null,
        title: null,
        author_name: null,
        author_url: null,
        acquired_at: null,
        modified,
        modification_note: modified ? DEFAULT_MODIFICATION_NOTES[asset] : null,
        attribution_override: null,
        notes: null,
        share_alike_ack: false,
        based_on_artwork: false,
    };
}

/**
 * Formulario a partir del crédito guardado (o vacío con los valores por defecto).
 */
export function creditForm(
    asset: CreditAsset,
    stored: StoredCredit | undefined,
    modified = true,
): CreditForm {
    if (!stored) {
        return emptyCredit(asset, modified);
    }

    return {
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
        share_alike_ack: false,
        based_on_artwork: stored.based_on_artwork,
    };
}

/**
 * El formulario tiene un crédito (se enviará y se validará).
 */
export function hasCredit(credit: CreditForm): boolean {
    return credit.license_id !== null || credit.source_name !== null;
}
