<?php

namespace App\Http\Resources;

use App\Models\Card;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Tarjeta para el hub de la app móvil.
 *
 * El modelo 3D solo se envía si el usuario posee la tarjeta.
 *
 * @mixin Card
 */
class CardResource extends JsonResource
{
    /**
     * @param  Card  $resource
     * @param  CarbonInterface|string|null  $capturedAt  Fecha de captura del usuario; null si no la posee.
     */
    public function __construct(
        $resource,
        private CarbonInterface|string|null $capturedAt = null
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
            'card_image' => self::publicUrl($this->card_image),
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
            'model_url' => $isOwned ? $this->modelUrl() : null,
        ];
    }

    /**
     * URL del modelo: archivo subido o, si no existe, la URL externa.
     */
    private function modelUrl(): ?string
    {
        return self::publicUrl($this->model_file) ?? $this->model_url;
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
