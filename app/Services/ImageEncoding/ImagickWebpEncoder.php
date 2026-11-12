<?php

namespace App\Services\ImageEncoding;

use Imagick;
use Throwable;

/**
 * Codificador preferido: libwebp con sharp YUV (el texto de la tarjeta
 * no se mancha de color) y redimensionado Lanczos.
 */
class ImagickWebpEncoder implements WebpEncoder
{
    public function name(): ?string
    {
        return 'imagick';
    }

    public function isAvailable(): bool
    {
        return extension_loaded('imagick')
            && in_array('WEBP', Imagick::queryFormats('WEBP'), true);
    }

    public function encode(string $source, ImageProfile $profile): string
    {
        $temporary = TemporaryFile::create();

        try {
            $image = new Imagick($source);

            // Solo el primer cuadro (GIF o WebP animados).
            $image->setIteratorIndex(0);
            $image = $image->getImage();

            // Fotos de teléfono: aplicar la orientación EXIF antes de quitarla.
            $image->autoOrient();

            if ($image->getImageColorspace() === Imagick::COLORSPACE_CMYK) {
                $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            }

            [$width, $height] = $profile->targetSize(
                $image->getImageWidth(),
                $image->getImageHeight()
            );

            if ($width !== $image->getImageWidth() || $height !== $image->getImageHeight()) {
                $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
            }

            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($profile->quality);
            $image->setOption('webp:method', '6');
            $image->setOption('webp:use-sharp-yuv', 'true');
            $image->setOption('webp:alpha-quality', '100');
            $image->writeImage('webp:'.$temporary);
            $image->destroy();
        } catch (Throwable $exception) {
            @unlink($temporary);

            throw new ImageEncodingException(
                'Imagick no pudo convertir la imagen: '.$exception->getMessage(),
                previous: $exception
            );
        }

        return $temporary;
    }
}
