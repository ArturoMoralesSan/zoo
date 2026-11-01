<?php

namespace App\Models;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Observers\CreditsCacheObserver;
use App\Services\Licensing\AttributionBuilder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Crédito de un recurso (ilustración, miniatura o modelo 3D) de una tarjeta.
 *
 * @property-read string $attribution
 */
#[ObservedBy(CreditsCacheObserver::class)]
class AssetCredit extends Model
{
    protected $fillable = [
        'asset',
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
        'derived_from_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'asset' => CreditAsset::class,
            'source_name' => AssetSource::class,
            'acquired_at' => 'date:Y-m-d',
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

    public function derivedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'derived_from_id');
    }

    /**
     * Modelo 3D generado a partir de la ilustración de la misma tarjeta.
     */
    public function isBasedOnArtwork(): bool
    {
        return $this->asset === CreditAsset::Model && $this->derived_from_id !== null;
    }

    /**
     * Línea de crédito lista para mostrar (ver AttributionBuilder).
     */
    protected function attribution(): Attribute
    {
        return Attribute::get(fn (): string => AttributionBuilder::build([
            'title' => $this->title,
            'author_name' => $this->author_name,
            'source' => $this->source_name?->creditName(),
            'modified' => $this->modified,
            'modification_note' => $this->modification_note,
            'attribution_override' => $this->attribution_override,
            'based_on_artwork' => $this->isBasedOnArtwork(),
            'license' => $this->license?->only([
                'slug',
                'short_name',
                'is_proprietary',
                'attribution_template',
            ]),
        ]));
    }
}
