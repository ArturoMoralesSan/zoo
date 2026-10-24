<?php

namespace App\Enums;

enum CardRarity: string
{
    case Comun = 'comun';
    case Rara = 'rara';
    case Epica = 'epica';
    case EdicionEspecial = 'edicion_especial';

    /**
     * Etiqueta legible de la rareza.
     */
    public function label(): string
    {
        return match ($this) {
            self::Comun => 'Común',
            self::Rara => 'Rara',
            self::Epica => 'Épica',
            self::EdicionEspecial => 'Edición especial',
        };
    }
}
