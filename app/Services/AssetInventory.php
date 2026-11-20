<?php

namespace App\Services;

use App\Models\Card;
use App\Models\SpeciesImage;
use App\Services\CardTransfer\CardTransferSupport;
use Illuminate\Support\Facades\Storage;

/**
 * Resumen de los archivos que descarga la app (imágenes y modelos 3D) para la
 * página "Recursos": peso actual, peso antes de optimizar y pendientes.
 *
 * Sin peso original registrado se cuenta el actual (sin ahorro): pasa con
 * archivos subidos antes de la compresión o cuyo original ya se borró.
 */
class AssetInventory
{
    private const HEAVIEST_LIMIT = 20;

    private const CARD_IMAGE_LABELS = [
        'generated' => 'Tarjeta generada',
        'thumbnail' => 'Miniatura de tarjeta',
        'artwork' => 'Ilustración',
    ];

    private const SPECIES_IMAGE_LABELS = [
        'main' => 'Imagen principal',
        'thumbnail' => 'Miniatura de especie',
        'gallery' => 'Galería',
    ];

    public function __construct(
        private CardImageOptimizer $cardImages,
        private SpeciesImageOptimizer $speciesImages,
        private CardTransferSupport $transferSupport,
    ) {}

    /**
     * @return array{
     *     server_encoder: string|null,
     *     zip_available: bool,
     *     groups: list<array{key: string, label: string, files: int, bytes: int, original_bytes: int, pending: int|null}>,
     *     models: list<array{card_id: int, card_name: string, bytes: int|null, original_bytes: int|null, height: float|null, width: float|null, depth: float|null}>,
     *     heaviest: list<array{kind: string, owner: string, edit_url: string, path: string, bytes: int}>
     * }
     */
    public function summary(): array
    {
        $files = [];
        $models = [];

        $cards = Card::query()
            ->orderBy('name')
            ->get([
                'id', 'name', 'image_original_bytes',
                ...array_values(CardImageOptimizer::FIELDS),
                'model_file', 'model_original_file',
                'model_final_height', 'model_final_width', 'model_final_depth',
            ]);

        foreach ($cards as $card) {
            $editUrl = route('admin.cards.edit', $card);

            foreach (CardImageOptimizer::FIELDS as $image => $column) {
                $path = $card->{$column};
                $bytes = $this->size('public', $path);

                if ($path !== null && $bytes !== null) {
                    $files[] = [
                        'group' => 'card_images',
                        'kind' => self::CARD_IMAGE_LABELS[$image],
                        'owner' => $card->name,
                        'edit_url' => $editUrl,
                        'path' => $path,
                        'bytes' => $bytes,
                        'original_bytes' => $card->image_original_bytes[$image] ?? $bytes,
                    ];
                }
            }

            $modelPath = $card->model_file;
            $modelBytes = $this->size((string) config('glb.disks.resized'), $modelPath);
            $originalBytes = $this->size((string) config('glb.disks.original'), $card->model_original_file);

            if ($modelPath === null || $modelBytes === null) {
                continue;
            }

            $files[] = [
                'group' => 'card_models',
                'kind' => 'Modelo 3D',
                'owner' => $card->name,
                'edit_url' => $editUrl,
                'path' => $modelPath,
                'bytes' => $modelBytes,
                'original_bytes' => $originalBytes ?? $modelBytes,
            ];

            $models[] = [
                'card_id' => $card->id,
                'card_name' => $card->name,
                'bytes' => $modelBytes,
                'original_bytes' => $originalBytes,
                'height' => $card->model_final_height,
                'width' => $card->model_final_width,
                'depth' => $card->model_final_depth,
            ];
        }

        $speciesImages = SpeciesImage::query()
            ->with('species:id,common_name')
            ->whereIn('type', SpeciesImageOptimizer::KINDS)
            ->get(['id', 'species_id', 'type', 'path', 'original_bytes']);

        foreach ($speciesImages as $image) {
            $bytes = $this->size('public', $image->path);

            if ($bytes === null) {
                continue;
            }

            $files[] = [
                'group' => 'species_images',
                'kind' => self::SPECIES_IMAGE_LABELS[$image->type],
                'owner' => $image->species->common_name ?? '',
                'edit_url' => route('admin.species.edit', $image->species_id),
                'path' => $image->path,
                'bytes' => $bytes,
                'original_bytes' => $image->original_bytes ?? $bytes,
            ];
        }

        return [
            'server_encoder' => $this->cardImages->serverEncoder(),
            'zip_available' => $this->transferSupport->available(),
            'groups' => [
                $this->group($files, 'card_images', 'Imágenes de tarjetas', count($this->cardImages->pending())),
                $this->group($files, 'species_images', 'Imágenes de especies', count($this->speciesImages->pending())),
                // Los modelos se escalan al subirlos; no hay conversión pendiente.
                $this->group($files, 'card_models', 'Modelos 3D de tarjetas', null),
            ],
            'models' => $models,
            'heaviest' => array_values(collect($files)
                ->sortByDesc('bytes')
                ->take(self::HEAVIEST_LIMIT)
                ->map(fn (array $file) => [
                    'kind' => $file['kind'],
                    'owner' => $file['owner'],
                    'edit_url' => $file['edit_url'],
                    'path' => $file['path'],
                    'bytes' => $file['bytes'],
                ])
                ->all()),
        ];
    }

    /**
     * @param  list<array{group: string, bytes: int, original_bytes: int}>  $files
     * @return array{key: string, label: string, files: int, bytes: int, original_bytes: int, pending: int|null}
     */
    private function group(array $files, string $key, string $label, ?int $pending): array
    {
        $items = collect($files)->where('group', $key);

        return [
            'key' => $key,
            'label' => $label,
            'files' => $items->count(),
            'bytes' => (int) $items->sum('bytes'),
            'original_bytes' => (int) $items->sum('original_bytes'),
            'pending' => $pending,
        ];
    }

    private function size(string $disk, ?string $path): ?int
    {
        if (! $path || preg_match('#^https?://#', $path)) {
            return null;
        }

        $storage = Storage::disk($disk);

        return $storage->exists($path) ? $storage->size($path) : null;
    }
}
