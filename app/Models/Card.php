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
        'astucia',
        'rapidez',
        'fuerza',
        'questions_per_duel',
        'retry_type',
        'retries',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'astucia' => 'integer',
        'rapidez' => 'integer',
        'fuerza' => 'integer',
        'questions_per_duel' => 'integer',
        'retries' => 'integer',
    ];

    /**
     * Obtener el número particular de reintentos según la tarjeta o su categoría.
     */
    public function getParticularRetriesAttribute(): int
    {
        if ($this->retry_type === 'category') {
            return (int) ($this->species?->category?->retry_limit ?? 3);
        }

        return (int) ($this->retries ?? 3);
    }

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

    public function quizQuestions(): HasMany 
    { 
        return $this->hasMany(QuizQuestion::class) ->orderBy('sort_order') ->orderBy('id'); 
    }
}
