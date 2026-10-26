<?php

namespace App\Http\Resources;

use App\Http\Resources\Duel\DuelAttemptsResource;
use App\Models\Card;
use App\Services\Duel\DuelAttempts;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Tarjeta para el hub de la app móvil.
 *
 * El modelo 3D (archivo GLB) y la imagen final de la tarjeta solo se envían
 * si el usuario posee la tarjeta.
 *
 * @mixin Card
 */
class CardResource extends JsonResource
{
    /**
     * @param  Card  $resource
     * @param  CarbonInterface|string|null  $capturedAt  Fecha de captura del usuario; null si no la posee.
     * @param  DuelAttempts|null  $attempts  Intentos para obtenerla; null si ya la posee.
     */
    public function __construct(
        $resource,
        private CarbonInterface|string|null $capturedAt = null,
        private ?DuelAttempts $attempts = null
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwned = $this->capturedAt !== null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'thumbnail_image' => self::publicUrl($this->thumbnail_image),
            'rarity' => $this->rarity,
            'species_name' => $this->species?->common_name,
            'category_name' => $this->species?->category?->name,
            'sort_order' => $this->sort_order,
            'stats' => [
                'astucia' => $this->astucia,
                'rapidez' => $this->rapidez,
                'fuerza' => $this->fuerza,
            ],
            'encountered' => $isOwned,
            'captured_at' => Carbon::make($this->capturedAt)?->toJSON(),
            'model_url' => $isOwned ? self::publicUrl($this->model_file) : null,
            'card_image_url' => $isOwned ? self::publicUrl($this->generated_image) : null,

            /**
             * Intentos para obtener la tarjeta en la ronda actual. `null` si ya la posee.
             */
            'attempts' => $this->attempts
                ? new DuelAttemptsResource($this->attempts)
                : null,
        ];
    }

    /**
     * URL absoluta de un archivo del disco público.
     */
    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
