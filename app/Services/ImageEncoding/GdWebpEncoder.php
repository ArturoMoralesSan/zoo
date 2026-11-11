<?php

namespace App\Services\ImageEncoding;

use GdImage;

/**
 * Respaldo con GD: sin sharp YUV, el texto queda un poco más suave que
 * con Imagick o con el navegador.
 */
class GdWebpEncoder implements WebpEncoder
{
    public function name(): ?string
    {
        return 'gd';
    }

    public function isAvailable(): bool
    {
        return function_exists('imagewebp')
            && (gd_info()['WebP Support'] ?? false);
    }

    public function encode(string $source, ImageProfile $profile): string
    {
        $contents = @file_get_contents($source);
        $image = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new ImageEncodingException('GD no pudo leer la imagen.');
        }

        $image = $this->orient($image, $source);

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        [$width, $height] = $profile->targetSize(imagesx($image), imagesy($image));

        if ($width !== imagesx($image) || $height !== imagesy($image)) {
            $scaled = imagescale($image, $width, $height, IMG_BICUBIC);

            if (! $scaled instanceof GdImage) {
                throw new ImageEncodingException('GD no pudo redimensionar la imagen.');
            }

            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $image = $scaled;
        }

        $temporary = TemporaryFile::create();

        if (! imagewebp($image, $temporary, $profile->quality)) {
            @unlink($temporary);

            throw new ImageEncodingException('GD no pudo guardar el WebP.');
        }

        return $temporary;
    }

    /**
     * GD ignora la orientación EXIF de las fotos de teléfono.
     */
    private function orient(GdImage $image, string $source): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
