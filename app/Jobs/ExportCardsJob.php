<?php

namespace App\Jobs;

use App\Enums\CardTransferStatus;
use App\Models\CardTransfer;
use App\Services\CardTransfer\CardPackageExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Arma el paquete .zip de una exportación pedida desde el panel.
 */
class ExportCardsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public CardTransfer $transfer) {}

    public function handle(CardPackageExporter $exporter): void
    {
        $this->transfer->update(['status' => CardTransferStatus::Processing]);

        $summary = $exporter->export(
            (string) $this->transfer->absolutePath(),
            (bool) ($this->transfer->options['include_qr_tokens'] ?? false),
            (bool) ($this->transfer->options['include_locations'] ?? false)
        );

        $this->transfer->update([
            'status' => CardTransferStatus::Completed,
            'file_size' => filesize((string) $this->transfer->absolutePath()) ?: null,
            'summary' => $summary,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->transfer->markFailed('No se pudo generar el paquete. Revisa el registro del servidor.');
    }
}
