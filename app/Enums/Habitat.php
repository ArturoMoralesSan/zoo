<?php

namespace App\Enums;

/*
 * Hábitats naturales. Una especie puede tener varios.
 * Incluye ecosistemas propios de México (selva seca, bosque de niebla) y
 * zonas rurales para especies domésticas sin hábitat silvestre (llama, dromedario).
 * Espejo en resources/js/lib/speciesCatalogs.ts.
 */
enum Habitat: string
{
    case TropicalRainforest = 'tropical_rainforest';
    case DryForest = 'dry_forest';
    case TemperateForest = 'temperate_forest';
    case ConiferousForest = 'coniferous_forest';
    case CloudForest = 'cloud_forest';
    case Savanna = 'savanna';
    case Grassland = 'grassland';
    case Scrubland = 'scrubland';
    case Desert = 'desert';
    case Mountain = 'mountain';
    case Polar = 'polar';
    case Wetland = 'wetland';
    case Mangrove = 'mangrove';
    case Freshwater = 'freshwater';
    case Coast = 'coast';
    case Ocean = 'ocean';
    case CoralReef = 'coral_reef';
    case Cave = 'cave';
    case Urban = 'urban';
    case Rural = 'rural';

    public function label(): string
    {
        return match ($this) {
            self::TropicalRainforest => 'Selva tropical',
            self::DryForest => 'Selva seca',
            self::TemperateForest => 'Bosque templado',
            self::ConiferousForest => 'Bosque de coníferas',
            self::CloudForest => 'Bosque de niebla',
            self::Savanna => 'Sabana',
            self::Grassland => 'Pastizal',
            self::Scrubland => 'Matorral',
            self::Desert => 'Desierto',
            self::Mountain => 'Montaña',
            self::Polar => 'Tundra y regiones polares',
            self::Wetland => 'Humedal',
            self::Mangrove => 'Manglar',
            self::Freshwater => 'Ríos y lagos',
            self::Coast => 'Costa',
            self::Ocean => 'Océano',
            self::CoralReef => 'Arrecife de coral',
            self::Cave => 'Cuevas',
            self::Urban => 'Zonas urbanas',
            self::Rural => 'Zonas rurales y de pastoreo',
        };
    }
}
