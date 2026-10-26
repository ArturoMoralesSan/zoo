<?php

namespace App\Http\Resources\Duel;

use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pregunta del duelo con todas sus opciones (mezcladas).
 *
 * @mixin QuizQuestion
 */
class DuelQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Id de la pregunta; se envía como `question_id` al responder.
             */
            'id' => $this->id,

            /**
             * Texto de la pregunta.
             */
            'question' => $this->question,

            /**
             * Opciones de respuesta, en orden aleatorio. Solo una es correcta.
             *
             * @var array<int, DuelAnswerResource>
             */
            'answers' => DuelAnswerResource::collection($this->answers),
        ];
    }
}
