<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZooZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'geometry',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Ubicaciones de especies dentro de esta zona.
     */
    public function speciesLocations(): HasMany
    {
        return $this->hasMany(SpeciesLocation::class, 'zone_id');
    }

    /**
     * Marcadores del mapa asociados a esta zona.
     */
    public function mapMarkers(): HasMany
    {
        return $this->hasMany(MapMarker::class, 'zone_id');
    }

    /**
     * Eventos asociados a esta zona.
     *
     * La relación se utilizará cuando implementemos
     * el módulo de Eventos.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'zone_id');
    }
}