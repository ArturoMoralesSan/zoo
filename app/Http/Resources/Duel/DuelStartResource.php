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
             * Intentos de la ronda actual. Para "Tienes {left} intentos" (A-20, A-21).
             * Aquí `locked_until` siempre es `null`: con la tarjeta bloqueada se responde 423.
             */
            'attempts' => new DuelAttemptsResource($this->attempts),

            /**
             * Animal que reta al usuario (A-19, A-20, A-21). La imagen final de la tarjeta
             * solo llega al ganar, en `card` de la respuesta del duelo.
             */
            'card' => [
                'name' => $this->card->name,
                'rarity' => $this->card->rarity,
                'category_name' => $this->card->species?->category?->name,
                'thumbnail_image' => CardResource::publicUrl($this->card->thumbnail_image),
                'stats' => [
                    'astucia' => $this->card->astucia,
                    'rapidez' => $this->card->rapidez,
                    'fuerza' => $this->card->fuerza,
                ],

                /**
                 * Modelo 3D (GLB) del animal para la batalla, ya escalado a su tamaño
                 * real (1 unidad = 1 m). `null` si la tarjeta no tiene modelo.
                 */
                'model_url' => CardResource::publicUrl($this->card->model_file),
            ],

            /**
             * Preguntas a responder, en orden, cada una con sus opciones.
             * Todas se envían juntas en `POST /duels/{attempt}/submit`.
             *
             * @var array<int, DuelQuestionResource>
             */
            'questions' => DuelQuestionResource::collection($this->questions),
        ];
    }
}
