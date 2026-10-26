<?php

namespace App\Services\Duel;

use App\Models\Card;
use Carbon\CarbonInterface;

/**
 * Resultado de responder un duelo.
 */
final readonly class DuelResult
{
    /**
     * Ganó: todas las respuestas fueron correctas y obtuvo la tarjeta.
     */
    public const OUTCOME_WON = 'won';

    /**
     * Falló, pero todavía le quedan intentos en esta ronda.
     */
    public const OUTCOME_RETRY = 'retry';

    /**
     * Falló y ya no le quedan intentos: la tarjeta queda bloqueada.
     */
    public const OUTCOME_LOCKED = 'locked';

    /**
     * @param  'won'|'retry'|'locked'  $outcome
     * @param  list<DuelQuestionResult>  $results
     * @param  list<DuelReviewItem>|null  $review
     */
    public function __construct(
        public string $outcome,
        public string $message,
        public int $correctCount,
        public int $totalQuestions,
        public array $results,
        public ?DuelAttempts $attempts,
        public ?array $review,
        public ?Card $card,
        public ?CarbonInterface $capturedAt,
    ) {}

    public function passed(): bool
    {
        return $this->outcome === self::OUTCOME_WON;
    }
}
