<?php

namespace App\Services\Duel;

use App\Models\Card;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Collection;

/**
 * Duelo iniciado (o reanudado) listo para mostrarse en la app.
 */
final readonly class DuelStart
{
    /**
     * @param  Collection<int, QuizQuestion>  $questions  Preguntas en el orden servido, con las respuestas mezcladas.
     */
    public function __construct(
        public QuizAttempt $attempt,
        public int $timeLimitSeconds,
        public int $timeLeftSeconds,
        public DuelAttempts $attempts,
        public Card $card,
        public Collection $questions,
    ) {}
}
