<?php

namespace App\Console\Commands;

use App\Services\CardTransfer\CardPackageExporter;
use App\Services\CardTransfer\CardTransferSupport;
use Illuminate\Console\Command;

/**
 * Exporta todas las tarjetas a un .zip sin pasar por el panel.
 * Ver docs/card-transfer.md.
 */
class CardsExportCommand extends Command
{
    protected $signature = 'cards:export
        {path? : Ruta del .zip (por defecto en storage/app/private/card-transfers)}
        {--qr-tokens : Incluir los tokens de los QR (los QR impresos seguirán sirviendo)}';

    protected $description = 'Exporta tarjetas, configuración y archivos a un paquete .zip';

    public function handle(CardTransferSupport $support, CardPackageExporter $exporter): int
    {
        if (! $support->available()) {
            $this->error(CardTransferSupport::UNAVAILABLE_MESSAGE);

            return self::FAILURE;
        }

        $path = (string) ($this->argument('path')
            ?: storage_path('app/private/card-transfers/zoodeck-tarjetas-'.now()->format('Y-m-d-His').'.zip'));

        $summary = $exporter->export($path, (bool) $this->option('qr-tokens'));

        foreach ($summary['missing_files'] as $missing) {
            $this->warn("Falta el archivo {$missing['field']} de «{$missing['card']}».");
        }

        $this->info(sprintf(
            'Paquete: %s (%d tarjetas, %d archivos, %.2f MB).',
            $path,
            $summary['cards'],
            $summary['files'],
            $summary['bytes'] / 1048576
        ));

        return self::SUCCESS;
    }
}
