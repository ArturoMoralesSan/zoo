<?php

namespace App\Console\Commands;

use App\Services\CardTransfer\CardPackageImporter;
use App\Services\CardTransfer\CardPackageReader;
use App\Services\CardTransfer\CardTransferSupport;
use App\Services\CardTransfer\InvalidCardPackageException;
use App\Services\CardTransfer\SpeciesResolver;
use Illuminate\Console\Command;

/**
 * Importa un paquete .zip sin pasar por el panel (útil si pesa más de lo que
 * el servidor acepta en una subida). Ver docs/card-transfer.md.
 */
class CardsImportCommand extends Command
{
    protected $signature = 'cards:import
        {path : Ruta del .zip}
        {--overwrite : Sobrescribir las tarjetas que ya existen (por defecto se omiten)}
        {--settings : Reemplazar la configuración por rareza y los mensajes de duelo}
        {--dry-run : Solo revisar el paquete y mostrar qué se haría}';

    protected $description = 'Importa tarjetas desde un paquete .zip exportado de ZooDeck';

    public function handle(CardTransferSupport $support, CardPackageImporter $importer): int
    {
        if (! $support->available()) {
            $this->error(CardTransferSupport::UNAVAILABLE_MESSAGE);

            return self::FAILURE;
        }

        try {
            $reader = CardPackageReader::open((string) $this->argument('path'));
        } catch (InvalidCardPackageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $reader->verifyFiles();
            $preview = $reader->analyze(new SpeciesResolver);

            $this->info(sprintf(
                'Tarjetas: %d nuevas, %d existentes, %d inválidas.',
                $preview['counts']['new'],
                $preview['counts']['existing'],
                $preview['counts']['invalid']
            ));

            foreach ($preview['missing_species'] as $species) {
                $this->warn("Especie no encontrada: {$species['scientific_name']} ({$species['slug']}); se omitirán {$species['cards']} tarjetas.");
            }

            if ($this->option('dry-run')) {
                return self::SUCCESS;
            }

            $result = $importer->import(
                $reader,
                $this->option('overwrite') ? CardPackageImporter::MODE_OVERWRITE : CardPackageImporter::MODE_SKIP,
                (bool) $this->option('settings')
            );
        } catch (InvalidCardPackageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $reader->close();
        }

        foreach ($result['failed'] as $failed) {
            $this->error("«{$failed['name']}»: {$failed['message']}");
        }

        $this->info(sprintf(
            'Creadas: %d. Actualizadas: %d. Omitidas: %d. Con error: %d.',
            $result['created'],
            $result['updated'],
            count($result['skipped']),
            count($result['failed'])
        ));

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
