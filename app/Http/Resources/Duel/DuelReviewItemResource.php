<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelReviewItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pregunta del repaso con la respuesta elegida y la correcta.
 *
 * @mixin DuelReviewItem
 */
class DuelReviewItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->question->id,

            'question' => $this->question->question,

            /**
             * Id de la respuesta que eligió el usuario.
             */
            'selected_answer_id' => $this->selectedAnswerId,

            /**
             * Todas las opciones; la correcta tiene `is_correct: true`.
             *
             * @var array<int, DuelReviewAnswerResource>
             */
            'answers' => DuelReviewAnswerResource::collection($this->question->answers),
        ];
    }
}
