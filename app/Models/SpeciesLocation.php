<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeciesLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'species_id',
        'zone_id',
        'name',
        'latitude',
        'longitude',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Especie asociada a esta ubicación.
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * Zona del zoológico donde se encuentra la ubicación.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ZooZone::class, 'zone_id');
    }
}
