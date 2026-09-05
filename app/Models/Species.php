<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Species extends Model
{
    protected $fillable = [
        'species_category_id',
        'common_name',
        'scientific_name',
        'slug',
        'description',
        'habitat',
        'origin',
        'diet',
        'conservation_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            SpeciesCategory::class,
            'species_category_id'
        );
    }

    public function images(): HasMany
    {
        return $this->hasMany(
            SpeciesImage::class
        )->orderBy('sort_order');
    }

    public function models(): HasMany
    {
        return $this->hasMany(
            SpeciesModel::class
        );
    }

    public function locations(): HasMany
    {
        return $this->hasMany(
            SpeciesLocation::class
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            SpeciesTag::class,
            'species_tag'
        );
    }
}