<?php

namespace App\Services\Points;

/**
 * Temporada visible para los visitantes: el aviso público del modo de
 * puntos activo y lo que cambia, separado en tarjetas y otras acciones.
 */
final readonly class PointSeason
{
    /**
     * @param  list<PointSeasonCardChange>  $cards  Una por rareza cuya captura cambia.
     * @param  list<PointSeasonRuleChange>  $other  Reglas cambiadas que ninguna rareza usa.
     */
    public function __construct(
        public string $title,
        public ?string $message,
        public array $cards,
        public array $other,
    ) {}
}
