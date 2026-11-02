<?php

namespace App\Services\Duel;

/**
 * Pregunta del repaso: cuál era su respuesta correcta.
 */
final readonly class DuelReviewItem
{
    public function __construct(
        public int $questionId,
        public int $correctAnswerId,
    ) {}
}
