<?php

namespace App\Services;

use App\Models\Level;
use App\Models\PointMode;
use App\Models\PointMovement;
use App\Models\PointRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
