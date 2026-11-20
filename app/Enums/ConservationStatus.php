<?php

namespace App\Enums;

/*
 * Categorías de la Lista Roja de la UICN. El valor es el código oficial,
 * así no depende de cómo se traduzca la etiqueta.
 * Espejo en resources/js/lib/speciesCatalogs.ts.
 */
enum ConservationStatus: string
{
    case Extinct = 'EX';
    case ExtinctInTheWild = 'EW';
    case CriticallyEndangered = 'CR';
    case Endangered = 'EN';
    case Vulnerable = 'VU';
    case NearThreatened = 'NT';
    case LeastConcern = 'LC';
    case DataDeficient = 'DD';
    case NotEvaluated = 'NE';

    public function label(): string
    {
        return match ($this) {
            self::Extinct => 'Extinto',
            self::ExtinctInTheWild => 'Extinto en estado silvestre',
            self::CriticallyEndangered => 'En peligro crítico',
            self::Endangered => 'En peligro',
            self::Vulnerable => 'Vulnerable',
            self::NearThreatened => 'Casi amenazado',
            self::LeastConcern => 'Preocupación menor',
            self::DataDeficient => 'Datos insuficientes',
            self::NotEvaluated => 'No evaluado',
        };
    }
}
