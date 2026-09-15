<?php

namespace App\Services;

use App\Models\Level;
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
     */
    public function add(
        User $user,
        string $type,
        ?string $description = null,
        ?Model $reference = null
    ): PointMovement {
        $rule = PointRule::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        if (! $rule) {
            throw new RuntimeException(
                "No existe una regla de puntos activa para: {$type}"
            );
        }

        if ($rule->points <= 0) {
            throw new RuntimeException(
                'La regla de puntos debe otorgar una cantidad mayor a cero.'
            );
        }

        $points = $rule->points;

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
