/*
 * Respuestas de admin.assets.transfers.* (CardTransferController):
 * paquetes con tarjetas y especies (Recursos → Descargas).
 */

export type CardTransferType = 'export' | 'import';

export type CardTransferStatus =
    | 'pending'
    | 'analyzing'
    | 'awaiting_confirmation'
    | 'processing'
    | 'completed'
    | 'failed';

export type CardImportMode = 'skip' | 'overwrite';

export interface CardImportPreviewCard {
    uuid: string;
    name: string;
    rarity: string | null;
    status: 'new' | 'existing' | 'invalid';
    existing_id: number | null;
    species: {
        key: string;
        common_name: string | null;
        scientific_name: string | null;
        // package: la especie viene en el paquete y se importa antes.
        match: 'slug' | 'scientific_name' | 'package' | null;
    };
    questions: number;
    qr_codes: number;
    files: number;
    errors: string[];
}

export interface CardImportMissingSpecies {
    key: string;
    slug: string | null;
    scientific_name: string | null;
    common_name: string | null;
    cards: number;
}

export interface SpeciesImportPreviewRow {
    slug: string;
    common_name: string;
    scientific_name: string | null;
    status: 'new' | 'existing' | 'invalid';
    existing_id: number | null;
    match: 'slug' | 'scientific_name' | null;
    images: number;
    errors: string[];
}

// Lo que falta en este servidor; `key` es el nombre normalizado.
export interface MissingReference {
    key: string;
    name: string;
    species?: number;
    locations?: number;
}

export interface SpeciesImportPreview {
    counts: { total: number; new: number; existing: number; invalid: number };
    rows: SpeciesImportPreviewRow[];
    missing_categories: MissingReference[];
    missing_tags: MissingReference[];
    missing_zones: MissingReference[];
}

export interface CardImportPreview {
    manifest: {
        app: string | null;
        source_url: string | null;
        exported_at: string | null;
        version: number;
        include_qr_tokens: boolean;
        include_locations: boolean;
        total_bytes: number;
    };
    species: SpeciesImportPreview;
    counts: { total: number; new: number; existing: number; invalid: number };
    cards: CardImportPreviewCard[];
    missing_species: CardImportMissingSpecies[];
    licenses: {
        missing: string[];
        unknown: string[];
        not_selectable: string[];
    };
    missing_point_rules: string[];
    qr_token_conflicts: number;
}

export interface CardImportResult {
    created: number;
    updated: number;
    skipped: { name: string; reason: string }[];
    failed: { name: string; message: string }[];
    settings_replaced: boolean;
    qr_tokens_regenerated: number;
    licenses_created: string[];
    species: {
        created: number;
        updated: number;
        skipped: { name: string; reason: string }[];
        failed: { name: string; message: string }[];
    };
    categories_created: string[];
    tags_created: string[];
    locations_skipped: number;
}

export interface CardExportSummary {
    cards: number;
    questions: number;
    qr_codes: number;
    credits: number;
    species: number;
    species_images: number;
    files: number;
    bytes: number;
    missing_files: { card: string; field: string }[];
}

export interface CardTransfer {
    id: number;
    type: CardTransferType;
    status: CardTransferStatus;
    options: {
        include_qr_tokens?: boolean;
        include_locations?: boolean;
        mode?: CardImportMode;
        replace_settings?: boolean;
    } | null;
    original_name: string | null;
    file_size: number | null;
    summary:
        | (Partial<CardExportSummary> & {
              preview?: CardImportPreview;
              result?: CardImportResult;
              progress?: { done: number; total: number };
          })
        | null;
    error: string | null;
    user: string | null;
    created_at: string | null;
    updated_at: string | null;
    can_download: boolean;
}

export interface CardTransferState {
    available: boolean;
    unavailable_message: string | null;
    max_upload_bytes: number;
    can_export: boolean;
    can_import: boolean;
    card_count: number;
    species_count: number;
    species: { id: number; common_name: string; scientific_name: string }[];
    categories: { id: number; name: string }[];
    tags: { id: number; name: string }[];
    zones: { id: number; name: string }[];
    transfers: CardTransfer[];
}
