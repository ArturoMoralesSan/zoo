<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'question',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Texto comparable para detectar preguntas repetidas: minúsculas, sin
     * acentos, sin signos al inicio y al final y con espacios simples.
     * Igual que normalizeQuestionText() en resources/js/lib/quizImport.ts.
     */
    public static function normalizeText(string $text): string
    {
        // Str::ascii quita acentos y convierte ¿ ¡ en ? !.
        $text = Str::ascii(mb_strtolower($text));
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text, ' ?!.');
    }

    /**
     * Card a la que pertenece la pregunta.
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Respuestas de la pregunta.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
