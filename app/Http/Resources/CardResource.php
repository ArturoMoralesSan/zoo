<?php

namespace App\Http\Resources;

use App\Enums\CardPackKind;
use App\Http\Resources\Duel\DuelAttemptsResource;
use App\Models\Card;
use App\Services\CardPack\CardPackBuilder;
use App\Services\Duel\DuelAttempts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta para el hub de la app móvil.
 *
 * - Obtenida (`encountered: true`): todos sus datos, imagen final y modelo 3D.
 * - Sin obtener (`encountered: false`): solo `id` y `attempts`; la app la muestra
 *   como "???" / "Sin descubrir" sin revelar el animal.
 *
 * @mixin Card
 */
class CardResource extends JsonResource
{
    /**
     * @param  Card  $resource
     * @param  bool  $owned  Si el usuario posee la tarjeta.
     * @param  DuelAttempts|null  $attempts  Intentos para obtenerla; solo si no la posee.
     */
    public function __construct(
        $resource,
        private bool $owned = false,
        private ?DuelAttempts $attempts = null
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            /**
             * Si el usuario posee la tarjeta. Define cuáles de los demás campos vienen.
             */
            'encountered' => $this->owned,

            $this->mergeWhen($this->owned, fn () => [
                'name' => $this->name,

                /**
                 * @var 'comun'|'rara'|'epica'|'edicion_especial'
                 */
                'rarity' => $this->rarity,
                'category_name' => $this->species?->category?->name,

                /**
                 * Imagen del animal para la cuadrícula (A-24).
                 */
                'thumbnail_image' => self::publicUrl($this->thumbnail_image),

                /**
                 * Imagen final de la tarjeta, generada en el editor del panel (A-22, A-26).
                 */
                'card_image_url' => self::publicUrl($this->generated_image),
                'stats' => [
                    'astucia' => $this->astucia,
                    'rapidez' => $this->rapidez,
                    'fuerza' => $this->fuerza,
                ],

                /**
                 * Modelo 3D (GLB) ya escalado a su tamaño real (1 unidad = 1 m). `null` si no tiene.
                 */
                'model_url' => self::publicUrl($this->model_file),

                /**
                 * Llaves del paquete sin conexión: `id` (versión) y `key` (AES-256, base64)
                 * de cada archivo, para descifrar el `.bin` de `GET /cards/pack` sin
                 * descargarlo otra vez. `null` si la tarjeta no tiene ese archivo.
                 * Ver docs/offline-packs-backend.md.
                 *
                 * @var array{model: array{id: string, key: string}|null, card_image: array{id: string, key: string}|null, thumbnail: array{id: string, key: string}|null}
                 */
                'pack' => app(CardPackBuilder::class)->refs($this->resource, CardPackKind::cases()),
            ]),

            $this->mergeWhen(! $this->owned, fn () => [
                /**
                 * Intentos para obtenerla: `left > 0` y sin `locked_until` = disponible
                 * para retar; con `locked_until` = bloqueada hasta esa fecha.
                 */
                'attempts' => new DuelAttemptsResource($this->attempts),
            ]),
        ];
    }

    /**
     * URL absoluta de un archivo del disco público.
     *
     * Se arma con el host de la petición (url()), como en especies, y no con
     * APP_URL: así la app móvil recibe una dirección que sí puede alcanzar
     * (p. ej. la IP de la red local en desarrollo, no localhost).
     */
    public static function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        return url('storage/'.ltrim($path, '/'));
    }
}
