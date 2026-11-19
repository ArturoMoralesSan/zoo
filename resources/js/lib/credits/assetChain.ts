import type { CreditAsset, CreditRole } from '@/lib/credits/types';

/*
 * Vista de la lista de créditos de una tarjeta: progreso de las licencias y,
 * por recurso, su origen, de qué otro recurso de la tarjeta se hizo y los
 * créditos adicionales. Usa el estado que ya calcula el servidor
 * (CardCreditController::assetSummary) para no duplicar sus reglas.
 */

export type AssetStatus = 'complete' | 'missing' | 'no_file';

export interface ChainCredit {
    id: number;
    role: CreditRole;
    based_on_asset: CreditAsset | null;
}

export interface ChainAsset<C extends ChainCredit = ChainCredit> {
    value: CreditAsset;
    label: string;
    status: AssetStatus;
    credits: C[];
}

export interface CreditsProgress {
    done: number;
    total: number;
    // Recursos con archivo a los que aún les falta licencia, en orden.
    missing: CreditAsset[];
}

export function creditsProgress(assets: ChainAsset[]): CreditsProgress {
    const withFile = assets.filter((asset) => asset.status !== 'no_file');

    return {
        done: withFile.filter((asset) => asset.status === 'complete').length,
        total: withFile.length,
        missing: withFile
            .filter((asset) => asset.status === 'missing')
            .map((asset) => asset.value),
    };
}

export interface AssetChain<C extends ChainCredit = ChainCredit> {
    origin: C | null;
    // Otro recurso de la tarjeta del que sale el origen (render, recorte…).
    basis: { value: CreditAsset; label: string; status: AssetStatus } | null;
    // "Basado en" primero y luego "Incluye", como en la app.
    extras: C[];
}

export function assetChain<C extends ChainCredit>(
    asset: ChainAsset<C>,
    assets: ChainAsset[],
): AssetChain<C> {
    const origin = asset.credits.find((item) => item.role === 'origin') ?? null;
    const basisAsset = origin?.based_on_asset
        ? (assets.find((item) => item.value === origin.based_on_asset) ?? null)
        : null;

    const order: CreditRole[] = ['based_on', 'component'];

    return {
        origin,
        basis: basisAsset
            ? {
                  value: basisAsset.value,
                  label: basisAsset.label,
                  status: basisAsset.status,
              }
            : null,
        extras: asset.credits
            .filter((item) => item.role !== 'origin')
            .sort((a, b) => order.indexOf(a.role) - order.indexOf(b.role)),
    };
}
