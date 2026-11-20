<?php

namespace App\Jobs;

use App\Enums\CardTransferStatus;
use App\Models\CardTransfer;
use App\Services\CardTransfer\CardPackageReader;
use App\Services\CardTransfer\InvalidCardPackageException;
use App\Services\CardTransfer\SpeciesResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Valida un paquete subido y prepara la vista previa; no escribe nada más.
 */
class AnalyzeCardImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public CardTransfer $transfer) {}

    public function handle(): void
    {
        $this->transfer->update(['status' => CardTransferStatus::Analyzing]);

        try {
            $reader = CardPackageReader::open((string) $this->transfer->absolutePath());
        } catch (InvalidCardPackageException $exception) {
            $this->transfer->markFailed($exception->getMessage());

            return;
        }

        try {
            $reader->verifyFiles();
            $preview = $reader->analyze(new SpeciesResolver);
        } catch (InvalidCardPackageException $exception) {
            $this->transfer->markFailed($exception->getMessage());

            return;
        } finally {
            $reader->close();
        }

        $this->transfer->update([
            'status' => CardTransferStatus::AwaitingConfirmation,
            'summary' => ['preview' => $preview],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->transfer->markFailed('No se pudo revisar el paquete. Revisa el registro del servidor.');
    }
}
