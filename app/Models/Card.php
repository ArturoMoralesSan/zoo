<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'species_id',
        'name',
        'rarity',
        'edition',
        'description',
        'card_image',
        'model_name',
        'model_file',
        'model_url',
        'model_format',
        'model_description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function species(): BelongsTo
    {
        return $this->belongsTo(
            Species::class
        );
    }

    public function captures(): HasMany
    {
        return $this->hasMany(
            CardCapture::class
        );
    }
}