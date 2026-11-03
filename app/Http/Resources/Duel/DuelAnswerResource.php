<?php

namespace App\Http\Resources\Duel;

use App\Models\QuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Opción de respuesta de una pregunta (sin indicar si es la correcta).
 *
 * @mixin QuizAnswer
 */
class DuelAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Id de la respuesta; se envía como `answer_id` al responder.
             */
            'id' => $this->id,

            /**
             * Texto de la opción.
             */
            'answer' => $this->answer,
        ];
    }
}
