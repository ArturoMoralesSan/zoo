<?php

namespace App\Services\ImageEncoding;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Compresión al guardar, común a tarjetas y especies. La clase que lo usa
 * recibe el codificador en $encoder y define el perfil de cada imagen.
 */
trait OptimizesWebpUploads
{
    abstract public function profile(string $image): ImageProfile;

    /**
     * Codificador del servidor (imagick, gd) o null si no hay.
     */
    public function serverEncoder(): ?string
    {
        return $this->encoder->isAvailable() ? $this->encoder->name() : null;
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
     * Al guardar: comprimir en el servidor si hace falta y se puede.
     * Nunca falla: ante un error se guarda el archivo tal como llegó.
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
            Log::warning('No se pudo optimizar la imagen al guardarla.', [
                'optimizer' => static::class,
                'image' => $image,
                'error' => $exception->getMessage(),
            ]);

            return $file;
        }

        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'imagen';

        // test: true porque el archivo no llegó por HTTP (permite moverlo).
        return new UploadedFile($encoded, $name.'.webp', 'image/webp', null, true);
    }
}
