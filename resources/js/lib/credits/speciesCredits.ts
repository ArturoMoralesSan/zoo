import type { CreditRole } from '@/lib/credits/types';

/*
 * Créditos de las imágenes de una especie (SpeciesCreditController).
 */

export interface SpeciesCreditSummary {
    id: number;
    common_name: string;
    scientific_name: string | null;
}

export type SpeciesImageType = 'main' | 'thumbnail' | 'gallery';

/**
 * Imagen de la especie para los formularios.
 */
export interface SpeciesCreditImage {
    id: number;
    type: SpeciesImageType;
    // "Imagen principal", "Miniatura", "Galería 2".
    label: string;
    alt_text: string | null;
    is_active: boolean;
    preview_url: string | null;
    has_file: boolean;
    has_origin: boolean;
}

export interface SpeciesImageCreditRow {
    id: number;
    role: CreditRole;
    role_label: string;
    source_label: string | null;
    source_url: string | null;
    license_short_name: string;
    license_url: string | null;
    attribution: string;
    is_ai: boolean;
    ai_checked_at: string | null;
}

/**
 * Imagen con su estado, créditos y avisos (lista de créditos de la especie).
 */
export interface SpeciesImageSummary extends SpeciesCreditImage {
    status: 'complete' | 'missing' | 'no_file';
    credits: SpeciesImageCreditRow[];
    alerts: { type: 'info' | 'warning'; message: string }[];
}
