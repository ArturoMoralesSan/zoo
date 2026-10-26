<?php

namespace App\Services\Duel;

use App\Models\QuizQuestion;

/**
 * Pregunta del repaso: todas sus respuestas (indicando la correcta)
 * y la que eligió el usuario.
 */
final readonly class DuelReviewItem
{
    public function __construct(
        public QuizQuestion $question,
        public int $selectedAnswerId,
    ) {}
}
