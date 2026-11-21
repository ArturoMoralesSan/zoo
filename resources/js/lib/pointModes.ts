// Modos de puntos (temporadas o eventos): lógica pura de la UI del panel.

export interface PointRuleRow {
    id: number;
    name: string;
    type: string;
    points: number;
    is_active: boolean;
    // false = ninguna acción la otorga ni la usa una rareza: cambiar sus puntos no tiene efecto.
    in_use: boolean;
}

export interface PointModeOverride {
    point_rule_id: number;
    points: number;
}

export interface PointMode {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    // Aviso para los visitantes en la app; el nombre y la descripción son internos.
    is_public: boolean;
    public_title: string | null;
    public_message: string | null;
    // Claves de resources/data/point-season-styles.json; null = predeterminado de la app.
    icon: string | null;
    color: string | null;
    overrides: PointModeOverride[];
    updated_at: string | null;
}

// Fila del formulario: null = la regla usa sus puntos base en este modo.
export interface PointModeFormRule {
    point_rule_id: number;
    points: number | null;
}

export interface PointModeChange {
    rule: PointRuleRow;
    base: number;
    points: number;
}

/**
 * Reglas cuyo puntaje en el modo es distinto del base, en el orden de `rules`.
 */
export function modeChanges(
    overrides: PointModeOverride[],
    rules: PointRuleRow[],
): PointModeChange[] {
    const byRule = new Map(overrides.map((o) => [o.point_rule_id, o.points]));

    return rules.flatMap((rule) => {
        const points = byRule.get(rule.id);

        return points === undefined || points === rule.points
            ? []
            : [{ rule, base: rule.points, points }];
    });
}

/**
 * Cambio porcentual respecto a los puntos base; null si no se puede calcular.
 */
export function percentChange(base: number, points: number): number | null {
    if (base <= 0) {
        return null;
    }

    return Math.round(((points - base) / base) * 100);
}

/**
 * Puntos base escalados a un porcentaje (p. ej. 50 = la mitad), sin negativos.
 */
export function scalePoints(base: number, percent: number): number {
    return Math.max(0, Math.round((base * percent) / 100));
}

/**
 * Filas del formulario para todas las reglas, a partir de lo guardado en el modo.
 */
export function formRules(
    rules: PointRuleRow[],
    overrides: PointModeOverride[] = [],
): PointModeFormRule[] {
    const byRule = new Map(overrides.map((o) => [o.point_rule_id, o.points]));

    return rules.map((rule) => ({
        point_rule_id: rule.id,
        points: byRule.get(rule.id) ?? null,
    }));
}
