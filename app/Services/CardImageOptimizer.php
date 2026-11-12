<?php

namespace App\Services;

use App\Exceptions\CardImageOptimizationException;
use App\Models\Card;
use App\Services\ImageEncoding\ImageEncodingException;
use App\Services\ImageEncoding\ImageProfile;
use App\Services\ImageEncoding\WebpEncoder;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compresión WebP de las imágenes de las tarjetas.
 *
 * El navegador comprime antes de subir (lib/imageOptimize.ts); el servidor
 * lo hace solo si tiene Imagick o GD con WebP. Ver docs/image-optimization.md.
 */
class CardImageOptimizer
{
    /**
     * Tipo de imagen => columna de la tarjeta.
     */
    public const FIELDS = [
        'generated' => 'generated_image',
        'thumbnail' => 'thumbnail_image',
        'artwork' => 'artwork_image',
    ];

    public function __construct(private WebpEncoder $encoder) {}

    /**
     * Codificador del servidor (imagick, gd) o null si no hay.
     */
    public function serverEncoder(): ?string
    {
        return $this->encoder->isAvailable() ? $this->encoder->name() : null;
    }

    public function profile(string $image): ImageProfile
    {
        return ImageProfile::fromConfig(config('images.cards.'.$image));
    }

    /**
     * Ya es WebP y cabe en su perfil.
     */
    public function isOptimized(string $image, string $file): bool
    {
        $info = @getimagesize($file);

        return $info !== false
            && $info['mime'] === 'image/webp'
            && $this->profile($image)->fits($info[0], $info[1]);
    }

    /**
     * Imágenes guardadas que aún no están optimizadas.
     *
     * @return list<array{card_id: int, card_name: string, image: string, path: string, url: string, bytes: int, width: int|null, height: int|null}>
     */
    public function pending(?int $cardId = null, ?string $onlyImage = null): array
    {
        $disk = Storage::disk('public');
        $items = [];

        $cards = Card::query()
            ->when($cardId, fn ($query) => $query->whereKey($cardId))
            ->orderBy('id')
            ->get(['id', 'name', ...array_values(self::FIELDS)]);

        foreach ($cards as $card) {
            foreach (self::FIELDS as $image => $column) {
                $path = $card->{$column};

                if (
                    ($onlyImage && $onlyImage !== $image) ||
                    ! $path ||
                    preg_match('#^https?://#', $path) ||
                    ! $disk->exists($path) ||
                    $this->isOptimized($image, $disk->path($path))
                ) {
                    continue;
                }

                $size = @getimagesize($disk->path($path)) ?: null;

                $items[] = [
                    'card_id' => $card->id,
                    'card_name' => $card->name,
                    'image' => $image,
                    'path' => $path,
                    'url' => $disk->url($path),
                    'bytes' => $disk->size($path),
                    'width' => $size[0] ?? null,
                    'height' => $size[1] ?? null,
                ];
            }
        }

        return $items;
    }

    /**
     * Al guardar la tarjeta: comprimir en el servidor si hace falta y se
     * puede. Nunca falla: ante un error se guarda el archivo tal como llegó.
     */
    public function prepareUpload(UploadedFile $file, string $image): UploadedFile
    {
        if (
            ! $this->encoder->isAvailable() ||
            $this->isOptimized($image, $file->getRealPath())
        ) {
            return $file;
        }

        try {
            $encoded = $this->encoder->encode($file->getRealPath(), $this->profile($image));
        } catch (ImageEncodingException $exception) {
            Log::warning('No se pudo optimizar la imagen de la tarjeta al guardarla.', [
                'image' => $image,
                'error' => $exception->getMessage(),
            ]);

            return $file;
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'imagen';

        // test: true porque el archivo no llegó por HTTP (permite moverlo).
        return new UploadedFile($encoded, $name.'.webp', 'image/webp', null, true);
    }

    /**
     * Reemplazar una imagen guardada por su versión WebP y borrar la original.
     *
     * $webpFile viene del navegador; sin él, codifica el servidor.
     *
     * @return array{path: string, url: string, original_bytes: int, optimized_bytes: int}
     *
     * @throws CardImageOptimizationException
     */
    public function replace(
        Card $card,
        string $image,
        string $expectedPath,
        ?string $webpFile = null
    ): array {
        $column = self::FIELDS[$image];
        $disk = Storage::disk('public');

        if ($card->{$column} !== $expectedPath) {
            throw CardImageOptimizationException::changed();
        }

        if (! $disk->exists($expectedPath)) {
            throw CardImageOptimizationException::missing();
        }

        $encodedHere = $webpFile === null;

        if ($encodedHere) {
            if (! $this->encoder->isAvailable()) {
                throw CardImageOptimizationException::invalid(
                    'El servidor no puede convertir imágenes. Envía el archivo WebP.'
                );
            }

            try {
                $webpFile = $this->encoder->encode(
                    $disk->path($expectedPath),
                    $this->profile($image)
                );
            } catch (ImageEncodingException $exception) {
                throw CardImageOptimizationException::invalid($exception->getMessage());
            }
        }

        try {
            $originalBytes = $disk->size($expectedPath);
            $optimizedBytes = (int) filesize($webpFile);

            $this->ensureValid($image, $webpFile, $optimizedBytes, $originalBytes);

            $newPath = $disk->putFileAs(
                'cards/'.$card->id,
                new File($webpFile),
                uniqid('', true).'.webp'
            );

            if ($newPath === false) {
                throw CardImageOptimizationException::invalid('No se pudo guardar el archivo optimizado.');
            }
        } finally {
            if ($encodedHere) {
                @unlink($webpFile);
            }
        }

        try {
            DB::transaction(function () use ($card, $column, $image, $expectedPath, $newPath, $originalBytes) {
                $locked = Card::query()->lockForUpdate()->findOrFail($card->id);

                // Otro guardado pudo reemplazarla mientras se codificaba.
                if ($locked->{$column} !== $expectedPath) {
                    throw CardImageOptimizationException::changed();
                }

                // Se conserva el peso registrado al subirla, si lo hay.
                $originals = $locked->image_original_bytes ?? [];
                $originals[$image] ??= $originalBytes;

                $locked->update([
                    $column => $newPath,
                    'image_original_bytes' => $originals,
                ]);
            });
        } catch (Throwable $exception) {
            $disk->delete($newPath);

            throw $exception;
        }

        // Solo tras confirmar el cambio: si algo falla antes, la original sigue en uso.
        $disk->delete($expectedPath);

        $card->refresh();

        return [
            'path' => $newPath,
            'url' => $disk->url($newPath),
            'original_bytes' => $originalBytes,
            'optimized_bytes' => $optimizedBytes,
        ];
    }

    private function ensureValid(string $image, string $file, int $bytes, int $originalBytes): void
    {
        $info = @getimagesize($file);

        if ($info === false || $info['mime'] !== 'image/webp') {
            throw CardImageOptimizationException::invalid('El archivo optimizado no es un WebP válido.');
        }

        if (! $this->profile($image)->fits($info[0], $info[1])) {
            throw CardImageOptimizationException::invalid('El archivo optimizado excede el tamaño permitido.');
        }

        if ($bytes >= $originalBytes) {
            throw CardImageOptimizationException::invalid('El archivo optimizado no es más ligero que el original.');
        }
    }
}
