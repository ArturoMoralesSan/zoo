<?php

namespace App\Enums;

/**
 * Acciones del código que otorgan una regla de puntos por su `type`.
 *
 * Es la única lista de esos tipos: el panel la usa para mostrar qué reglas
 * tienen efecto y GET /api/points/season para no anunciar reglas sin uso.
 * La captura de tarjetas no está aquí porque no se otorga por un tipo fijo:
 * usa la regla elegida para cada rareza en "Configuración por rareza" (o,
 * sin configuración, la `card_capture_<rareza>` de CardRarity::pointRuleType()).
 *
 * Una acción nueva que dé puntos se agrega aquí y se otorga con
 * PointService::addIfRuleActive() dentro de la transacción de la acción.
 */
enum PointAction: string
{
    case TicketPurchase = 'ticket_purchase';
    case Donation = 'donation';

    /**
     * Dónde se otorga, para el panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::TicketPurchase => 'Compra de boleto en app y taquilla',
            self::Donation => 'Donación desde la app y el panel',
        };
    }

    /**
     * Descripción del movimiento de puntos.
     */
    public function movementDescription(): string
    {
        return match ($this) {
            self::TicketPurchase => 'Compra de boleto',
            self::Donation => 'Donación',
        };
    }
}
