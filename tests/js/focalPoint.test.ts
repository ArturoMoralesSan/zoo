import { describe, expect, it } from 'vitest';

import { APP_FRAMES, clampFocal, objectPosition } from '@/lib/focalPoint';

describe('objectPosition', () => {
    it('falls back to the centre without a point', () => {
        expect(objectPosition(null, null)).toBe('50% 50%');
        expect(objectPosition(0.3, null)).toBe('50% 50%');
        expect(objectPosition(undefined, 0.3)).toBe('50% 50%');
    });

    it('uses percentages, like expo-image contentPosition', () => {
        expect(objectPosition(0.3, 0.42)).toBe('30% 42%');
        expect(objectPosition(0, 1)).toBe('0% 100%');
        expect(objectPosition(-1, 2)).toBe('0% 100%');
    });
});

describe('clampFocal', () => {
    it('limits to 0..1 with four decimals', () => {
        expect(clampFocal(0.123456, 1.4)).toEqual({ x: 0.1235, y: 1 });
        expect(clampFocal(-0.2, 0.5)).toEqual({ x: 0, y: 0.5 });
    });
});

describe('APP_FRAMES', () => {
    it('covers portrait and landscape frames', () => {
        const ratios = APP_FRAMES.map((frame) => frame.width / frame.height);

        expect(Math.min(...ratios)).toBeLessThan(1);
        expect(Math.max(...ratios)).toBeGreaterThan(1.5);
    });
});
