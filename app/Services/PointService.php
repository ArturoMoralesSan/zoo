<?php

namespace App\Services;

use App\Enums\CardRarity;
use App\Enums\PointAction;
use App\Models\CardRaritySetting;
use App\Models\Level;
use App\Models\PointMode;
use App\Models\PointMovement;
use App\Models\PointRule;
use App\Models\User;
use App\Services\Points\PointsAward;
use App\Services\Points\PointsAwardSeason;
use App\Services\Points\PointSeason;
use App\Services\Points\PointSeasonCardChange;
use App\Services\Points\PointSeasonRuleChange;
use App\Services\Points\PointSeasonStyles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PointService
{
    /**
     * Aplica una regla de puntos a un usuario.
     *
     * Los puntos son acumulativos.
     * Al alcanzar un nuevo nivel, se actualiza level_id.
     * El nivel nunca disminuye.
     *
     * Falla si no hay una regla activa de ese tipo. Si el modo de puntos
     * activo deja la regla en 0, no otorga nada y devuelve null: un modo
     * nunca debe impedir la acción que da los puntos (por ejemplo, una compra).
     */
    public function add(
        User $user,
        string $type,
        ?string $description = null,
        ?Model $reference = null
    ): ?PointMovement {
        $rule = PointRule::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            throw new RuntimeException(
                "No existe una regla de puntos activa para: {$type}"
            );
        }

        return $this->apply($user, $rule, $description, $reference);
    }

    /**
     * Como add(), pero no falla si la regla no existe, está inactiva
     * u otorga cero puntos: devuelve null y no otorga nada.
     * Recibe el tipo de la regla o la regla misma.
     *
     * Para flujos donde los puntos son un extra y no deben impedir la
     * acción principal (por ejemplo, capturar una tarjeta).
     */
    public function addIfRuleActive(
        User $user,
        PointRule|string $rule,
        ?string $description = null,
        ?Model $reference = null
    ): ?PointMovement {
        if (is_string($rule)) {
            $rule = PointRule::query()
                ->where('type', $rule)
                ->first();
        }

        if (! $rule || ! $rule->is_active) {
            return null;
        }

        return $this->apply($user, $rule, $description, $reference);
    }

    /**
     * Otorga la regla una sola vez por referencia (una orden, una donación…)
     * y devuelve el resumen para la respuesta de la acción.
     *
     * Si la referencia ya tiene un movimiento de puntos no otorga nada otra
     * vez (por ejemplo, un webhook de pago repetido) y `awarded` trae los
     * puntos que ya dio. Con $rule null (la acción no da puntos) solo arma
     * el resumen. Igual que addIfRuleActive(), nunca falla por la regla.
     */
    public function awardOnce(
        User $user,
        ?PointRule $rule,
        string $description,
        Model $reference
    ): PointsAward {
        return DB::transaction(function () use ($user, $rule, $description, $reference) {
            // Bloquear al usuario serializa los otorgamientos simultáneos
            // de la misma referencia.
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $previousLevelId = $user->level_id;

            $existing = PointMovement::query()
                ->where('reference_type', $reference::class)
                ->where('reference_id', $reference->getKey())
                ->first();

            $awarded = $existing
                ? (int) $existing->points
                : ($rule ? $this->addIfRuleActive($user, $rule, $description, $reference)->points ?? 0 : 0);

            return $this->summary($user, $previousLevelId, $awarded, $rule);
        });
    }

    /**
     * Resumen de los puntos de una acción, con el formato de
     * PointsAwardResource. $baseRule es la regla de la acción; si no da
     * puntos (null o inactiva), la temporada lleva base_points null.
     */
    public function summary(User $user, ?int $previousLevelId, int $awarded, ?PointRule $baseRule): PointsAward
    {
        $user->refresh();

        $season = $this->visibleSeason();

        return new PointsAward(
            awarded: $awarded,
            total: (int) $user->points,
            level: $user->level_id ? Level::find($user->level_id) : null,
            levelUp: $user->level_id !== null && $user->level_id !== $previousLevelId,
            season: $season
                ? new PointsAwardSeason(
                    title: $season->title,
                    message: $season->message,
                    icon: $season->icon,
                    color: $season->color,
                    basePoints: $baseRule?->is_active ? (int) $baseRule->points : null,
                )
                : null,
        );
    }

    /**
     * Dónde tiene efecto cada regla: las acciones del código que la otorgan
     * por su tipo (PointAction) y las rarezas que la usan al capturar.
     * Una regla sin ninguna de las dos no da puntos aunque esté activa.
     *
     * @param  iterable<PointRule>  $rules
     * @return array<int, array{actions: list<string>, rarities: list<string>, in_use: bool}>
     */
    public function ruleUsage(iterable $rules): array
    {
        $captureRuleIds = $this->captureRuleIds();
        $usage = [];

        foreach ($rules as $rule) {
            $action = PointAction::tryFrom((string) $rule->type);
            $actions = $action ? [$action->label()] : [];
            $rarities = [];

            foreach ($captureRuleIds as $rarityValue => $ruleId) {
                if ($ruleId === $rule->id) {
                    $rarities[] = CardRarity::from($rarityValue)->label();
                }
            }

            $usage[$rule->id] = [
                'actions' => $actions,
                'rarities' => $rarities,
                'in_use' => $actions !== [] || $rarities !== [],
            ];
        }

        return $usage;
    }

    /**
     * Temporada que ven los visitantes: el modo activo, solo si está marcado
     * como visible. Separa lo que cambia en dos listas:
     * - cards: una entrada por rareza cuya regla de captura ("Configuración
     *   por rareza") cambia; así la app no tiene que cruzar reglas y rarezas.
     * - other: las demás reglas activas que cambian y que alguna acción del
     *   código otorga (PointAction: boletos, donaciones…). Las reglas sin uso
     *   no aparecen: la app no debe anunciar acciones que no dan puntos.
     * Una regla usada por alguna rareza solo aparece en cards.
     */
    public function visibleSeason(): ?PointSeason
    {
        $mode = PointMode::active();

        if (! $mode || ! $mode->is_public) {
            return null;
        }

        $overrides = $mode->overridePoints();

        /** @var array<int, PointRule> $changed */
        $changed = [];

        $rules = PointRule::query()
            ->whereKey(array_keys($overrides))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($rules as $rule) {
            if ($overrides[$rule->id] !== (int) $rule->points) {
                $changed[$rule->id] = $rule;
            }
        }

        $cards = [];
        $usedByRarity = [];

        foreach ($this->captureRuleIds() as $rarityValue => $ruleId) {
            if ($ruleId === null) {
                continue;
            }

            $usedByRarity[$ruleId] = true;

            if (! isset($changed[$ruleId])) {
                continue;
            }

            $cards[] = new PointSeasonCardChange(
                rarity: CardRarity::from($rarityValue),
                basePoints: (int) $changed[$ruleId]->points,
                points: $overrides[$ruleId],
            );
        }

        $other = [];

        foreach ($changed as $ruleId => $rule) {
            if (isset($usedByRarity[$ruleId]) || PointAction::tryFrom((string) $rule->type) === null) {
                continue;
            }

            $other[] = new PointSeasonRuleChange(
                type: (string) $rule->type,
                name: (string) $rule->name,
                basePoints: (int) $rule->points,
                points: $overrides[$ruleId],
            );
        }

        return new PointSeason(
            title: $mode->public_title ?: $mode->name,
            message: $mode->public_message,
            // Una clave que ya no está en el catálogo cuenta como predeterminado.
            icon: PointSeasonStyles::icon($mode->icon),
            color: PointSeasonStyles::color($mode->color),
            cards: $cards,
            other: $other,
        );
    }

    /**
     * Regla de captura de cada rareza, en el orden del enum. Igual que el
     * duelo: la elegida en "Configuración por rareza" (null = sin puntos) o,
     * si la rareza no tiene configuración, su regla card_capture_<rareza>.
     *
     * @return array<string, int|null>
     */
    private function captureRuleIds(): array
    {
        // Valores crudos: rarity es el valor del enum CardRarity.
        $settings = CardRaritySetting::query()
            ->toBase()
            ->get(['rarity', 'point_rule_id'])
            ->keyBy('rarity');

        $ruleIds = [];

        foreach (CardRarity::cases() as $rarity) {
            $setting = $settings->get($rarity->value);

            $ruleIds[$rarity->value] = $setting !== null
                ? ($setting->point_rule_id === null ? null : (int) $setting->point_rule_id)
                : PointRule::query()->where('type', $rarity->pointRuleType())->value('id');
        }

        return $ruleIds;
    }

    /**
     * Puntos que otorga la regla: los del modo activo, o los base si no hay
     * modo activo o el modo no cambia esta regla.
     */
    public function pointsFor(PointRule $rule, ?PointMode $mode): int
    {
        return $mode ? $mode->pointsFor($rule) : (int) $rule->points;
    }

    private function apply(
        User $user,
        PointRule $rule,
        ?string $description,
        ?Model $reference
    ): ?PointMovement {
        $mode = PointMode::active();
        $points = $this->pointsFor($rule, $mode);

        if ($points <= 0) {
            return null;
        }

        // Acumular puntos
        $user->increment('points', $points);

        // Refrescar para obtener el nuevo saldo
        $user->refresh();

        // Revisar si alcanzó un nuevo nivel
        $this->updateLevel($user);

        // Registrar movimiento
        return $user->pointMovements()->create([
            'points' => $points,
            'type' => $rule->type,
            'point_mode_id' => $mode?->id,
            'description' => $description ?? $rule->name,
            'reference_type' => $reference
                ? $reference::class
                : null,
            'reference_id' => $reference?->getKey(),
        ]);
    }

    /**
     * Actualiza el nivel del usuario si alcanzó uno superior.
     *
     * El nivel nunca baja.
     */
    private function updateLevel(User $user): void
    {
        $newLevel = Level::query()
            ->where('min_points', '<=', $user->points)
            ->orderByDesc('min_points')
            ->first();

        if (! $newLevel) {
            return;
        }

        // Si ya tiene ese nivel o uno superior, no hacemos nada.
        if (
            $user->level_id !== null &&
            $newLevel->min_points <= $this->currentLevelMinPoints($user)
        ) {
            return;
        }

        $user->update([
            'level_id' => $newLevel->id,
        ]);
    }

    /**
     * Obtiene los puntos mínimos del nivel actual.
     */
    private function currentLevelMinPoints(User $user): int
    {
        if (! $user->level_id) {
            return -1;
        }

        return (int) Level::query()
            ->whereKey($user->level_id)
            ->value('min_points');
    }
}
