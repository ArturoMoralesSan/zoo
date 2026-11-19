<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'points',
        'type',
        'point_mode_id',
        'description',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'reference_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Modo de puntos activo cuando se otorgaron; null = puntos base.
     *
     * @return BelongsTo<PointMode, $this>
     */
    public function pointMode(): BelongsTo
    {
        return $this->belongsTo(PointMode::class);
    }
}
