<?php

namespace App\Services\ImageEncoding;

/**
 * Límites de una imagen optimizada (resources/data/card-image-profiles.json).
 */
final readonly class ImageProfile
{
    public function __construct(
        public int $maxWidth,
        public int $maxHeight,
        public int $quality,
    ) {}

    /**
     * @param  array{maxWidth: int, maxHeight: int, quality: int}  $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            (int) $config['maxWidth'],
            (int) $config['maxHeight'],
            (int) $config['quality'],
        );
    }

    public function fits(int $width, int $height): bool
    {
        return $width <= $this->maxWidth && $height <= $this->maxHeight;
    }

    /**
     * Tamaño final conservando la proporción. Nunca agranda.
     *
     * @return array{0: int, 1: int}
     */
    public function targetSize(int $width, int $height): array
    {
        $scale = min(1, $this->maxWidth / $width, $this->maxHeight / $height);

        return [
            max(1, min($this->maxWidth, (int) round($width * $scale))),
            max(1, min($this->maxHeight, (int) round($height * $scale))),
        ];
    }
}
