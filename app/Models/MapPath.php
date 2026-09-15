<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapPath extends Model
{
    protected $fillable = [
        'zone_id',
        'name',
        'description',
        'coordinates',
        'distance',
        'estimated_time',
        'is_active',
        'order',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'coordinates' => 'array',
        'distance' => 'integer',
        'estimated_time' => 'integer',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(
            ZooZone::class,
            'zone_id',
        );
    }
}
