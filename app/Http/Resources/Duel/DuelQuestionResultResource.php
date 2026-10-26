<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelQuestionResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Si la respuesta elegida para una pregunta fue correcta.
 *
 * @mixin DuelQuestionResult
 */
class DuelQuestionResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'question_id' => $this->questionId,

            /**
             * Respuesta que eligió el usuario.
             */
            'answer_id' => $this->answerId,

            /**
             * Si la respuesta elegida es correcta (no se indica cuál era la correcta).
             */
            'is_correct' => $this->isCorrect,
        ];
    }
}
