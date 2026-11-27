import type { CreditAsset } from '@/lib/credits/types';

/*
 * Texto de los avisos de licencias que faltan, igual que
 * Card::missingCreditsText() y Species::missingCreditsText().
 */

const WITH_ARTICLE: Record<CreditAsset, string> = {
    artwork: 'la ilustración',
    thumbnail: 'la miniatura',
    model: 'el modelo 3D',
    image: 'la imagen',
};

/**
 * "la ilustración", "la miniatura y el modelo 3D"… o null si no falta nada.
 */
export function missingAssetsText(assets: CreditAsset[]): string | null {
    const names = assets.map((asset) => WITH_ARTICLE[asset]);
    const last = names.pop();

    if (!last) {
        return null;
    }

    return names.length ? `${names.join(', ')} y ${last}` : last;
}

/**
 * "1 imagen", "3 imágenes"… o null si no falta ninguna.
 */
export function missingImagesText(count: number): string | null {
    if (count <= 0) {
        return null;
    }

    return count === 1 ? '1 imagen' : `${count} imágenes`;
}
