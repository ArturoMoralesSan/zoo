<?php

namespace App\Services\ImageEncoding;

/**
 * Sin Imagick ni GD con WebP: el navegador comprime antes de subir.
 */
class NullWebpEncoder implements WebpEncoder
{
    public function name(): ?string
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function encode(string $source, ImageProfile $profile): string
    {
        throw new ImageEncodingException('El servidor no puede convertir imágenes a WebP.');
    }
}
