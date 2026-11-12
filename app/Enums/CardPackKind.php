<?php

namespace App\Enums;

/**
 * Archivos de una tarjeta que van en el paquete sin conexión.
 */
enum CardPackKind: string
{
    case Model = 'model';
    case CardImage = 'card_image';
    case Thumbnail = 'thumbnail';

    public function column(): string
    {
        return match ($this) {
            self::Model => 'model_file',
            self::CardImage => 'generated_image',
            self::Thumbnail => 'thumbnail_image',
        };
    }

    /**
     * Disco donde vive el archivo original.
     */
    public function disk(): string
    {
        return match ($this) {
            self::Model => (string) config('glb.disks.resized'),
            default => 'public',
        };
    }
}
