<?php

namespace App\Enums;

/*
 * Tipo de alimentación principal de una especie.
 * Espejo en resources/js/lib/speciesCatalogs.ts.
 */
enum Diet: string
{
    case Carnivore = 'carnivore';
    case Herbivore = 'herbivore';
    case Omnivore = 'omnivore';
    case Insectivore = 'insectivore';
    case Piscivore = 'piscivore';
    case Frugivore = 'frugivore';
    case Nectarivore = 'nectarivore';
    case Granivore = 'granivore';
    case Folivore = 'folivore';
    case Scavenger = 'scavenger';
    case Planktivore = 'planktivore';

    public function label(): string
    {
        return match ($this) {
            self::Carnivore => 'Carnívoro',
            self::Herbivore => 'Herbívoro',
            self::Omnivore => 'Omnívoro',
            self::Insectivore => 'Insectívoro',
            self::Piscivore => 'Piscívoro',
            self::Frugivore => 'Frugívoro',
            self::Nectarivore => 'Nectarívoro',
            self::Granivore => 'Granívoro',
            self::Folivore => 'Folívoro',
            self::Scavenger => 'Carroñero',
            self::Planktivore => 'Planctívoro',
        };
    }
}
