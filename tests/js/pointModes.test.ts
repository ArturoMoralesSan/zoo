import { describe, expect, it } from 'vitest';

import {
    formRules,
    modeChanges,
    percentChange,
    scalePoints,
} from '@/lib/pointModes';
import type { PointRuleRow } from '@/lib/pointModes';

const rules: PointRuleRow[] = [
    {
        id: 1,
        name: 'Compra de boleto',
        type: 'ticket_purchase',
        points: 10,
        is_active: true,
        in_use: true,
    },
    {
        id: 2,
        name: 'Captura rara',
        type: 'card_capture_rara',
        points: 50,
        is_active: true,
        in_use: true,
    },
    {
        id: 3,
        name: 'Donación',
        type: 'donation',
        points: 5,
        is_active: false,
        in_use: true,
    },
];

describe('modeChanges', () => {
    it('lists only rules whose points differ from base, in rule order', () => {
        const changes = modeChanges(
            [
                { point_rule_id: 3, points: 0 },
                { point_rule_id: 2, points: 30 },
                { point_rule_id: 1, points: 10 },
            ],
            rules,
        );

        expect(changes.map((c) => [c.rule.id, c.base, c.points])).toEqual([
            [2, 50, 30],
            [3, 5, 0],
        ]);
    });

    it('ignores overrides for rules that no longer exist', () => {
        expect(modeChanges([{ point_rule_id: 99, points: 1 }], rules)).toEqual(
            [],
        );
    });
});

describe('percentChange', () => {
    it('computes the rounded change against base', () => {
        expect(percentChange(50, 30)).toBe(-40);
        expect(percentChange(10, 12)).toBe(20);
        expect(percentChange(5, 0)).toBe(-100);
    });

    it('returns null when base is zero', () => {
        expect(percentChange(0, 10)).toBeNull();
    });
});

describe('scalePoints', () => {
    it('scales and rounds, never below zero', () => {
        expect(scalePoints(25, 50)).toBe(13);
        expect(scalePoints(100, 150)).toBe(150);
        expect(scalePoints(10, -20)).toBe(0);
    });
});

describe('formRules', () => {
    it('builds one row per rule, null where the mode keeps base', () => {
        expect(formRules(rules, [{ point_rule_id: 2, points: 30 }])).toEqual([
            { point_rule_id: 1, points: null },
            { point_rule_id: 2, points: 30 },
            { point_rule_id: 3, points: null },
        ]);
    });
});
