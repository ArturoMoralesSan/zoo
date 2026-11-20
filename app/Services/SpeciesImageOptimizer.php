<?php

namespace App\Services;

use App\Exceptions\ImageOptimizationException;
use App\Models\SpeciesImage;
use App\Services\ImageEncoding\ImageEncodingException;
use App\Services\ImageEncoding\ImageProfile;
use App\Services\ImageEncoding\OptimizesWebpUploads;
use App\Services\ImageEncoding\WebpEncoder;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Compresión WebP de las imágenes de las especies (principal, miniatura y
 * galería), igual que CardImageOptimizer pero por fila de species_images.
 * Ver docs/image-optimization.md.
 */
class SpeciesImageOptimizer
{
    use OptimizesWebpUploads;

    /**
     * Tipos de species_images que se optimizan (con perfil en config/images.php).
     */
    public const KINDS = ['main', 'thumbnail', 'gallery'];

    public function __construct(private WebpEncoder $encoder) {}

    public function profile(string $image): ImageProfile
    {
        return ImageProfile::fromConfig(config('images.species.'.$image));
    }

    /**
     * Peso antes de comprimir: el navegador lo envía porque sube la imagen
     * ya comprimida; sin él, el del archivo recibido.
     */
    public function originalBytes(Request $request, string $key, UploadedFile $file): int
    {
        $sent = (int) $request->input($key);

        return $sent > 0 ? $sent : (int) $file->getSize();
    }

    /**
     * Peso actual del archivo guardado, o null si no está en el disco.
     */
    public function storedBytes(SpeciesImage $image): ?int
    {
        $disk = Storage::disk('public');

        return $image->path && $disk->exists($image->path)
            ? $disk->size($image->path)
            : null;
    }

    /**
     * Imágenes guardadas que aún no están optimizadas.
     *
     * @return list<array{image_id: int, species_id: int, species_name: string, image: string, path: string, url: string, bytes: int, width: int|null, height: int|null}>
     */
    public function pending(?int $speciesId = null, ?string $onlyImage = null): array
    {
        $disk = Storage::disk('public');
        $items = [];

        $images = SpeciesImage::query()
            ->with('species:id,common_name')
            ->whereIn('type', $onlyImage ? [$onlyImage] : self::KINDS)
            ->when($speciesId, fn ($query) => $query->where('species_id', $speciesId))
            ->orderBy('species_id')
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($images as $image) {
            $path = $image->path;

            if (
                ! $path ||
                preg_match('#^https?://#', $path) ||
                ! $disk->exists($path) ||
                $this->isOptimized($image->type, $disk->path($path))
            ) {
                continue;
            }

            $size = @getimagesize($disk->path($path)) ?: null;

            $items[] = [
                'image_id' => $image->id,
                'species_id' => $image->species_id,
                'species_name' => $image->species->common_name ?? '',
                'image' => $image->type,
                'path' => $path,
                'url' => $disk->url($path),
                'bytes' => $disk->size($path),
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Reemplazar una imagen guardada por su versión WebP y borrar la original.
     *
     * $webpFile viene del navegador; sin él, codifica el servidor.
     *
     * @return array{path: string, url: string, original_bytes: int, optimized_bytes: int}
     *
     * @throws ImageOptimizationException
     */
    public function replace(
        SpeciesImage $image,
        string $expectedPath,
        ?string $webpFile = null
    ): array {
        $disk = Storage::disk('public');

        if (! in_array($image->type, self::KINDS, true)) {
            throw ImageOptimizationException::invalid('Este tipo de imagen no se optimiza.');
        }

        if ($image->path !== $expectedPath) {
            throw ImageOptimizationException::changed();
        }

        if (! $disk->exists($expectedPath)) {
            throw ImageOptimizationException::missing();
        }

        $encodedHere = $webpFile === null;

        if ($encodedHere) {
            if (! $this->encoder->isAvailable()) {
                throw ImageOptimizationException::invalid(
                    'El servidor no puede convertir imágenes. Envía el archivo WebP.'
                );
            }

            try {
                $webpFile = $this->encoder->encode(
                    $disk->path($expectedPath),
                    $this->profile($image->type)
                );
            } catch (ImageEncodingException $exception) {
                throw ImageOptimizationException::invalid($exception->getMessage());
            }
        }

        try {
            $originalBytes = $disk->size($expectedPath);
            $optimizedBytes = (int) filesize($webpFile);

            $this->ensureValid($image->type, $webpFile, $optimizedBytes, $originalBytes);

            $newPath = $disk->putFileAs(
                'species/'.$image->species_id,
                new File($webpFile),
                uniqid('', true).'.webp'
            );

            if ($newPath === false) {
                throw ImageOptimizationException::invalid('No se pudo guardar el archivo optimizado.');
            }
        } finally {
            if ($encodedHere) {
                @unlink($webpFile);
            }
        }

        try {
            DB::transaction(function () use ($image, $expectedPath, $newPath, $originalBytes) {
                $locked = SpeciesImage::query()->lockForUpdate()->findOrFail($image->id);

                // Otro guardado pudo reemplazarla mientras se codificaba.
                if ($locked->path !== $expectedPath) {
                    throw ImageOptimizationException::changed();
                }

                $locked->update([
                    'path' => $newPath,
                    // Se conserva el peso registrado al subirla, si lo hay.
                    'original_bytes' => $locked->original_bytes ?? $originalBytes,
                ]);
            });
        } catch (Throwable $exception) {
            $disk->delete($newPath);

            throw $exception;
        }

        // Solo tras confirmar el cambio: si algo falla antes, la original sigue en uso.
        $disk->delete($expectedPath);

        $image->refresh();

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
            throw ImageOptimizationException::invalid('El archivo optimizado no es un WebP válido.');
        }

        if (! $this->profile($image)->fits($info[0], $info[1])) {
            throw ImageOptimizationException::invalid('El archivo optimizado excede el tamaño permitido.');
        }

        if ($bytes >= $originalBytes) {
            throw ImageOptimizationException::invalid('El archivo optimizado no es más ligero que el original.');
        }
    }
}
