<?php

namespace App\Jobs;

use App\Enums\CardTransferStatus;
use App\Models\CardTransfer;
use App\Services\CardTransfer\CardPackageImporter;
use App\Services\CardTransfer\CardPackageReader;
use App\Services\CardTransfer\InvalidCardPackageException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Importa un paquete ya revisado con las opciones que eligió la persona.
 */
class ImportCardsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public CardTransfer $transfer) {}

    /**
     * Dos importaciones a la vez podrían crear la misma tarjeta dos veces.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('card-import'))->releaseAfter(30)->expireAfter(3600)];
    }

    public function handle(CardPackageImporter $importer): void
    {
        $this->transfer->update(['status' => CardTransferStatus::Processing]);

        $options = $this->transfer->options ?? [];
        $preview = $this->transfer->summary['preview'] ?? null;

        try {
            $reader = CardPackageReader::open((string) $this->transfer->absolutePath());
        } catch (InvalidCardPackageException $exception) {
            $this->transfer->markFailed($exception->getMessage());

            return;
        }

        try {
            $result = $importer->import(
                $reader,
                (string) ($options['mode'] ?? CardPackageImporter::MODE_SKIP),
                (bool) ($options['replace_settings'] ?? false),
                (array) ($options['species_map'] ?? []),
                (array) ($options['references'] ?? []),
                function (int $done, int $total) use ($preview) {
                    // Progreso para el panel (especies y tarjetas), como mucho cada 10.
                    if ($done === $total || $done % 10 === 0) {
                        $this->transfer->update([
                            'summary' => ['preview' => $preview, 'progress' => ['done' => $done, 'total' => $total]],
                        ]);
                    }
                }
            );
        } catch (InvalidCardPackageException $exception) {
            $this->transfer->markFailed($exception->getMessage());

            return;
        } finally {
            $reader->close();
        }

        $this->transfer->update([
            'status' => CardTransferStatus::Completed,
            'summary' => ['preview' => $preview, 'result' => $result],
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->transfer->markFailed('La importación se detuvo por un error. Revisa el registro del servidor.');
    }
}
