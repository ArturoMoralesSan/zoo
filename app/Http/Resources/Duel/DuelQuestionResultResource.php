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
             * Si la respuesta que eligió el usuario es correcta. No indica cuál era
             * la correcta: esa solo llega en `review`.
             */
            'is_correct' => $this->isCorrect,
        ];
    }
}
