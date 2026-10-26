<?php

namespace App\Http\Resources\Duel;

use App\Http\Resources\CardResource;
use App\Services\Duel\DuelStart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Duelo listo para responder.
 *
 * @mixin DuelStart
 */
class DuelStartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Id del duelo; se usa en `POST /duels/{attempt}/submit`.
             *
             * @example 42
             */
            'attempt_id' => $this->attempt->id,

            /**
             * Tiempo total para responder un duelo, en segundos.
             * Si se responde después, o no se responde, cuenta como intento fallido.
             *
             * @example 600
             */
            'time_limit_seconds' => $this->timeLimitSeconds,

            /**
             * Segundos que quedan para responder, calculados en el servidor.
             * Úsalo para el contador de la app (no depende del reloj del dispositivo).
             * En un duelo nuevo es igual a `time_limit_seconds`; al reanudar uno, es menor.
             *
             * @example 600
             */
            'time_left_seconds' => $this->timeLeftSeconds,

            /**
             * Fecha y hora (ISO 8601) en que vence el duelo.
             */
            'expires_at' => $this->attempt->expires_at->toJSON(),

            'attempts' => new DuelAttemptsResource($this->attempts),

            'card' => [
                'id' => $this->card->id,
                'name' => $this->card->name,
                'thumbnail_image' => CardResource::publicUrl($this->card->thumbnail_image),
                'card_image_url' => CardResource::publicUrl($this->card->generated_image),
                'rarity' => $this->card->rarity,
                'species_name' => $this->card->species?->common_name,
                'stats' => [
                    'astucia' => $this->card->astucia,
                    'rapidez' => $this->card->rapidez,
                    'fuerza' => $this->card->fuerza,
                ],
            ],

            /**
             * Preguntas a responder, cada una con sus opciones.
             *
             * @var array<int, DuelQuestionResource>
             */
            'questions' => DuelQuestionResource::collection($this->questions),
        ];
    }
}
