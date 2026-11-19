<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modo de puntos (temporada o evento). Cambia los puntos de algunas reglas;
 * las reglas que no cambia usan sus puntos base. Solo uno puede estar activo.
 */
class PointMode extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_public',
        'public_title',
        'public_message',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Reglas que este modo cambia, con sus puntos en `pivot->points`.
     *
     * @return BelongsToMany<PointRule, $this>
     */
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(PointRule::class, 'point_mode_rules')
            ->withPivot('points')
            ->withTimestamps();
    }

    /**
     * Puntos que este modo asigna, por id de regla.
     *
     * @return array<int, int>
     */
    public function overridePoints(): array
    {
        $points = [];

        foreach ($this->rules()->pluck('point_mode_rules.points', 'point_rules.id') as $ruleId => $value) {
            $points[(int) $ruleId] = (int) $value;
        }

        return $points;
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    /**
     * Puntos de la regla en este modo; los base si el modo no la cambia.
     */
    public function pointsFor(PointRule $rule): int
    {
        $points = $this->rules()
            ->where('point_rules.id', $rule->getKey())
            ->value('point_mode_rules.points');

        return $points === null ? (int) $rule->points : (int) $points;
    }
}
