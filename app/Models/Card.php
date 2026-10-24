<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'card_image',
        'model_file',
        'is_active',
        'sort_order',
        'astucia',
        'rapidez',
        'fuerza',
        'duel_settings_source',
        'questions_per_duel',
        'retries',
        'cooldown_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'astucia' => 'integer',
        'rapidez' => 'integer',
        'fuerza' => 'integer',
        'questions_per_duel' => 'integer',
        'retries' => 'integer',
        'cooldown_minutes' => 'integer',
    ];

    /**
     * Orígenes posibles de la configuración de duelos.
     */
    public const DUEL_SETTINGS_RARITY = 'rarity';

    public const DUEL_SETTINGS_CARD = 'card';

    /**
     * Indica si la tarjeta usa la configuración de su rareza.
     */
    public function usesRarityDuelSettings(): bool
    {
        return $this->duel_settings_source !== self::DUEL_SETTINGS_CARD;
    }

    /**
     * Preguntas por duelo según la tarjeta o su rareza.
     */
    protected function effectiveQuestionsPerDuel(): Attribute
    {
        return Attribute::get(fn (): int => (int) (
            $this->usesRarityDuelSettings()
                ? $this->raritySetting?->questions_per_duel
                : $this->questions_per_duel
        ) ?: CardRaritySetting::DEFAULT_QUESTIONS_PER_DUEL);
    }

    /**
     * Reintentos según la tarjeta o su rareza.
     */
    protected function effectiveRetries(): Attribute
    {
        return Attribute::get(fn (): int => (int) (
            $this->usesRarityDuelSettings()
                ? $this->raritySetting?->retries
                : $this->retries
        ) ?: CardRaritySetting::DEFAULT_RETRIES);
    }

    /**
     * Minutos de espera tras agotar los reintentos, según la tarjeta o su rareza.
     */
    protected function effectiveCooldownMinutes(): Attribute
    {
        return Attribute::get(fn (): int => (int) (
            $this->usesRarityDuelSettings()
                ? $this->raritySetting?->cooldown_minutes
                : $this->cooldown_minutes
        ) ?: CardRaritySetting::DEFAULT_COOLDOWN_MINUTES);
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(
            Species::class
        );
    }

    public function raritySetting(): BelongsTo
    {
        return $this->belongsTo(
            CardRaritySetting::class,
            'rarity',
            'rarity'
        );
    }

    public function captures(): HasMany
    {
        return $this->hasMany(
            CardCapture::class
        );
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(CardQrCode::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function quizQuestions(): HasMany 
    { 
        return $this->hasMany(QuizQuestion::class) ->orderBy('sort_order') ->orderBy('id'); 
    }
}
