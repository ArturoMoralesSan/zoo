<?php

namespace App\Models;

use App\Observers\CreditsCacheObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Licencia del catálogo (solo la administra LicenseSeeder).
 */
#[ObservedBy(CreditsCacheObserver::class)]
class License extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'short_name',
        'url',
        'requires_attribution',
        'allows_commercial',
        'allows_modification',
        'share_alike',
        'attribution_template',
        'is_selectable',
        'is_proprietary',
        'summary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_attribution' => 'boolean',
            'allows_commercial' => 'boolean',
            'allows_modification' => 'boolean',
            'share_alike' => 'boolean',
            'is_selectable' => 'boolean',
            'is_proprietary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Licencia abierta (Creative Commons, dominio público o GFDL): no es
     * propia ni la licencia de una plataforma concreta.
     */
    public function isOpen(): bool
    {
        return str_starts_with($this->slug, 'cc') ||
            in_array($this->slug, ['public-domain', 'gfdl'], true);
    }

    /**
     * Datos para los formularios del panel.
     *
     * @return array<string, mixed>
     */
    public function toFormOption(): array
    {
        return $this->only([
            'id',
            'slug',
            'name',
            'short_name',
            'url',
            'requires_attribution',
            'allows_commercial',
            'allows_modification',
            'share_alike',
            'attribution_template',
            'is_selectable',
            'is_proprietary',
            'summary',
        ]);
    }
}
