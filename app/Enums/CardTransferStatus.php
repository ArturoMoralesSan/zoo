<?php

namespace App\Enums;

/**
 * Estado de una exportación o importación de tarjetas.
 *
 * Exportar: pending → processing → completed | failed.
 * Importar: analyzing → awaiting_confirmation → processing → completed | failed.
 */
enum CardTransferStatus: string
{
    case Pending = 'pending';
    case Analyzing = 'analyzing';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Hay un trabajo en cola o en curso.
     */
    public function isRunning(): bool
    {
        return in_array($this, [self::Pending, self::Analyzing, self::Processing], true);
    }
}
