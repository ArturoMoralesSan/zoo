<?php

namespace App\Enums;

/**
 * Recurso que lleva crédito propio: los de una tarjeta (ilustración,
 * miniatura, modelo 3D) o una imagen de especie (Image).
 *
 * La imagen generada (generated_image) es una obra derivada nuestra y no
 * tiene crédito.
 */
enum CreditAsset: string
{
    case Artwork = 'artwork';
    case Thumbnail = 'thumbnail';
    case Model = 'model';

    // Imagen de especie (principal, miniatura o galería): cada una es su propio dueño.
    case Image = 'image';

    public function label(): string
    {
        return match ($this) {
            self::Artwork => 'Ilustración',
            self::Thumbnail => 'Miniatura',
            self::Model => 'Modelo 3D',
            self::Image => 'Imagen',
        };
    }

    /**
     * Con artículo, para frases: "la ilustración", "el modelo 3D".
     */
    public function withArticle(): string
    {
        return match ($this) {
            self::Artwork => 'la ilustración',
            self::Thumbnail => 'la miniatura',
            self::Model => 'el modelo 3D',
            self::Image => 'la imagen',
        };
    }

    /**
     * "de la ilustración", "del modelo 3D".
     */
    public function fromPhrase(): string
    {
        return match ($this) {
            self::Model => 'del modelo 3D',
            default => 'de '.$this->withArticle(),
        };
    }

    /**
     * Columna de la tarjeta con el archivo del recurso. Solo para los
     * recursos de tarjeta (ver forCards()).
     */
    public function column(): string
    {
        return match ($this) {
            self::Artwork => 'artwork_image',
            self::Thumbnail => 'thumbnail_image',
            self::Model => 'model_file',
            self::Image => throw new \LogicException('Las imágenes de especie no son una columna de la tarjeta.'),
        };
    }

    public function isCardAsset(): bool
    {
        return $this !== self::Image;
    }

    /**
     * Recursos de una tarjeta, en orden.
     *
     * @return array<int, self>
     */
    public static function forCards(): array
    {
        return [self::Artwork, self::Thumbnail, self::Model];
    }

    /**
     * Orden en los listados: ilustración, miniatura, modelo.
     */
    public function order(): int
    {
        return match ($this) {
            self::Artwork => 0,
            self::Thumbnail => 1,
            self::Model => 2,
            self::Image => 3,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
