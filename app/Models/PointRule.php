<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PointRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'points',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Modos de puntos que cambian los puntos de esta regla.
     *
     * @return BelongsToMany<PointMode, $this>
     */
    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(PointMode::class, 'point_mode_rules')
            ->withPivot('points')
            ->withTimestamps();
    }
}
