/*
 * Respuestas de admin.cards.transfers.* (CardTransferController).
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
        match: 'slug' | 'scientific_name' | null;
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

export interface CardImportPreview {
    manifest: {
        app: string | null;
        source_url: string | null;
        exported_at: string | null;
        include_qr_tokens: boolean;
        total_bytes: number;
    };
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
}

export interface CardExportSummary {
    cards: number;
    questions: number;
    qr_codes: number;
    credits: number;
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
    species: { id: number; common_name: string; scientific_name: string }[];
    transfers: CardTransfer[];
}
