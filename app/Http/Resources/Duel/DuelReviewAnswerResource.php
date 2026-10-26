<?php

namespace App\Http\Resources\Duel;

use App\Models\QuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Opción de respuesta en el repaso, indicando si es la correcta.
 *
 * @mixin QuizAnswer
 */
class DuelReviewAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'answer' => $this->answer,

            /**
             * Si esta opción es la respuesta correcta.
             */
            'is_correct' => (bool) $this->is_correct,
        ];
    }
}
