<?php

namespace App\Models;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Observers\CreditsCacheObserver;
use App\Services\Licensing\AttributionBuilder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Crédito de un recurso (ilustración, miniatura o modelo 3D) de una tarjeta.
 *
 * Un recurso tiene un crédito de origen y, si hace falta, créditos de las
 * obras en que se basa y del material de terceros que incluye.
 *
 * @property-read string $attribution
 */
#[ObservedBy(CreditsCacheObserver::class)]
class Credit extends Model
{
    protected $fillable = [
        'asset',
        'role',
        'sort_order',
        'license_id',
        'title',
        'author_name',
        'author_url',
        'source_name',
        'source_url',
        'acquired_at',
        'modified',
        'modification_note',
        'attribution_override',
        'based_on_asset',
        'ai_prompt',
        'ai_checked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'asset' => CreditAsset::class,
            'role' => CreditRole::class,
            'source_name' => AssetSource::class,
            'based_on_asset' => CreditAsset::class,
            'sort_order' => 'integer',
            'acquired_at' => 'date:Y-m-d',
            'ai_checked_at' => 'datetime',
            'modified' => 'boolean',
        ];
    }

    public function creditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    /**
     * Orden de los listados: recurso, papel y posición.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Clave para ordenar una colección: ilustración → miniatura → modelo,
     * y dentro de cada recurso origen → basado en → incluye.
     */
    public function sortKey(): string
    {
        return sprintf('%d-%d-%05d-%010d', $this->asset->order(), $this->role->order(), $this->sort_order, $this->id);
    }

    public function isAiGenerated(): bool
    {
        return $this->source_name === AssetSource::Ai;
    }

    /**
     * Línea de crédito lista para mostrar (ver AttributionBuilder).
     */
    protected function attribution(): Attribute
    {
        return Attribute::get(fn (): string => AttributionBuilder::build([
            'role' => $this->role?->value,
            'title' => $this->title,
            'author_name' => $this->author_name,
            'source' => $this->source_name?->creditName(),
            'modified' => $this->modified,
            'modification_note' => $this->modification_note,
            'attribution_override' => $this->attribution_override,
            'based_on_asset' => $this->based_on_asset?->value,
            'license' => $this->license?->only([
                'slug',
                'short_name',
                'is_proprietary',
                'attribution_template',
            ]),
        ]));
    }
}
