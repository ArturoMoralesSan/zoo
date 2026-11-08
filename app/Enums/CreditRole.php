<?php

namespace App\Enums;

/**
 * Papel de un crédito dentro de un recurso de la tarjeta.
 *
 * - Origen: quién hizo el archivo o de dónde salió (exactamente uno).
 * - Basado en: obra externa usada para crearlo (la foto que se dio a Meshy,
 *   un modelo de otro sitio que se renderizó…).
 * - Incluye: material de terceros dentro del recurso (HDRI, texturas…).
 */
enum CreditRole: string
{
    case Origin = 'origin';
    case BasedOn = 'based_on';
    case Component = 'component';

    public function label(): string
    {
        return match ($this) {
            self::Origin => 'Origen',
            self::BasedOn => 'Basado en',
            self::Component => 'Incluye',
        };
    }

    /**
     * Prefijo de la línea de crédito ("Basado en: …"). El origen no lleva.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Origin => '',
            default => $this->label().': ',
        };
    }

    /**
     * Orden en los listados: origen, basado en, incluye.
     */
    public function order(): int
    {
        return match ($this) {
            self::Origin => 0,
            self::BasedOn => 1,
            self::Component => 2,
        };
    }
}
