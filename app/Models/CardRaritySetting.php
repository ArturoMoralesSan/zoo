<?php

namespace App\Models;

use App\Enums\CardRarity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardRaritySetting extends Model
{
    /**
     * Valores por defecto cuando no existe configuración para una rareza.
     */
    public const DEFAULT_QUESTIONS_PER_DUEL = 3;

    public const DEFAULT_RETRIES = 3;

    public const DEFAULT_COOLDOWN_MINUTES = 1440;

    protected $fillable = [
        'rarity',
        'questions_per_duel',
        'retries',
        'cooldown_minutes',
    ];

    protected function casts(): array
    {
        return [
            'rarity' => CardRarity::class,
            'questions_per_duel' => 'integer',
            'retries' => 'integer',
            'cooldown_minutes' => 'integer',
        ];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'rarity', 'rarity');
    }
}
