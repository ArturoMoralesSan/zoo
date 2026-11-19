import { describe, expect, it } from 'vitest';

import { assetChain, creditsProgress } from '@/lib/credits/assetChain';
import type { ChainAsset } from '@/lib/credits/assetChain';

const assets: ChainAsset[] = [
    {
        value: 'artwork',
        label: 'Ilustración',
        status: 'complete',
        credits: [
            { id: 1, role: 'component', based_on_asset: null },
            { id: 2, role: 'origin', based_on_asset: null },
            { id: 3, role: 'based_on', based_on_asset: null },
        ],
    },
    {
        value: 'thumbnail',
        label: 'Miniatura',
        status: 'missing',
        credits: [{ id: 4, role: 'origin', based_on_asset: 'model' }],
    },
    {
        value: 'model',
        label: 'Modelo 3D',
        status: 'missing',
        credits: [],
    },
];

describe('creditsProgress', () => {
    it('counts only assets with a file', () => {
        expect(
            creditsProgress([assets[0], { ...assets[2], status: 'no_file' }]),
        ).toEqual({ done: 1, total: 1, missing: [] });
    });

    it('lists the assets still missing a licence in order', () => {
        expect(creditsProgress(assets)).toEqual({
            done: 1,
            total: 3,
            missing: ['thumbnail', 'model'],
        });
    });
});

describe('assetChain', () => {
    it('separates the origin and orders the extra credits like the app', () => {
        const chain = assetChain(assets[0], assets);

        expect(chain.origin?.id).toBe(2);
        expect(chain.basis).toBeNull();
        expect(chain.extras.map((item) => item.id)).toEqual([3, 1]);
    });

    it('links the asset the origin was made from, with its status', () => {
        expect(assetChain(assets[1], assets).basis).toEqual({
            value: 'model',
            label: 'Modelo 3D',
            status: 'missing',
        });
    });

    it('has no origin when none was registered', () => {
        const chain = assetChain(assets[2], assets);

        expect(chain.origin).toBeNull();
        expect(chain.extras).toEqual([]);
    });
});
