<?php

namespace App\Http\Resources\Duel;

use App\Services\Duel\DuelReviewItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Respuesta correcta de una pregunta del duelo.
 *
 * Los textos de la pregunta y de sus opciones ya los tiene la app
 * desde `POST /duels`; aquí solo se indica cuál opción era la correcta.
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
            /**
             * Pregunta del último duelo.
             */
            'question_id' => $this->questionId,

            /**
             * Id de la opción correcta (uno de los `answers[].id` de esa pregunta).
             */
            'correct_answer_id' => $this->correctAnswerId,
        ];
    }
}
