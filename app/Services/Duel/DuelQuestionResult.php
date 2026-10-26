<?php

namespace App\Services\Duel;

/**
 * Resultado de una pregunta respondida: solo indica si la respuesta
 * elegida fue correcta, nunca cuál era la correcta.
 */
final readonly class DuelQuestionResult
{
    public function __construct(
        public int $questionId,
        public int $answerId,
        public bool $isCorrect,
    ) {}
}
