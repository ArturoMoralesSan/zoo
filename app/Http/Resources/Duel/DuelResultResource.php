<?php

namespace App\Http\Resources\Duel;

use App\Http\Resources\CardResource;
use App\Services\Duel\DuelResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resultado de responder un duelo.
 *
 * @mixin DuelResult
 */
class DuelResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Resultado del duelo:
             * - `won`: todas las respuestas fueron correctas; la tarjeta se agregó a la colección.
             * - `retry`: falló, pero le quedan intentos (`attempts.left > 0`).
             * - `locked`: falló y ya no le quedan intentos; la tarjeta queda bloqueada hasta `attempts.locked_until`.
             *
             * @var 'won'|'retry'|'locked'
             */
            'outcome' => $this->outcome,

            /**
             * Si cada respuesta elegida fue correcta, en el orden de las preguntas.
             * Sirve para marcar la opción equivocada (A-23).
             *
             * @var array<int, DuelQuestionResultResource>
             */
            'results' => DuelQuestionResultResource::collection($this->results),

            /**
             * Intentos después de este duelo. `null` si ganó.
             */
            'attempts' => $this->attempts
                ? new DuelAttemptsResource($this->attempts)
                : null,

            /**
             * Repaso final: la respuesta correcta de cada pregunta de este último duelo
             * (no de los intentos anteriores). Solo cuando `outcome` es `locked` y la rareza
             * de la tarjeta permite mostrar las respuestas; si no, `null`.
             *
             * @var array<int, DuelReviewItemResource>|null
             */
            'review' => $this->review !== null
                ? DuelReviewItemResource::collection($this->review)
                : null,

            /**
             * Tarjeta obtenida (mismo formato que una tarjeta obtenida en `GET /cards`).
             * Solo si ganó; si no, `null`.
             */
            'card' => $this->card
                ? new CardResource($this->card, owned: true)
                : null,
        ];
    }
}
