<?php

namespace App\Enums;

/**
 * Recurso de una tarjeta que lleva crédito propio.
 *
 * La imagen generada (generated_image) es una obra derivada nuestra y no
 * tiene crédito.
 */
enum CreditAsset: string
{
    case Artwork = 'artwork';
    case Thumbnail = 'thumbnail';
    case Model = 'model';

    public function label(): string
    {
        return match ($this) {
            self::Artwork => 'Ilustración',
            self::Thumbnail => 'Miniatura',
            self::Model => 'Modelo 3D',
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
     * Columna de la tarjeta con el archivo del recurso.
     */
    public function column(): string
    {
        return match ($this) {
            self::Artwork => 'artwork_image',
            self::Thumbnail => 'thumbnail_image',
            self::Model => 'model_file',
        };
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
