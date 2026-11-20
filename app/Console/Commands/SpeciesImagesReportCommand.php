<?php

namespace App\Console\Commands;

use App\Services\SpeciesImageOptimizer;
use Illuminate\Console\Command;

/**
 * Solo lectura: imágenes de especies que aún no están en WebP optimizado.
 */
class SpeciesImagesReportCommand extends Command
{
    protected $signature = 'species:images:report';

    protected $description = 'Muestra las imágenes de especies pendientes de optimizar (solo lectura)';

    public function handle(SpeciesImageOptimizer $optimizer): int
    {
        $pending = collect($optimizer->pending());

        $this->line('Codificador del servidor: '.($optimizer->serverEncoder() ?? 'ninguno (usa el panel)'));

        if ($pending->isEmpty()) {
            $this->info('No hay imágenes pendientes.');

            return self::SUCCESS;
        }

        $this->table(
            ['Imagen', 'Pendientes', 'Tamaño total'],
            $pending->groupBy('image')
                ->map(fn ($items, $image) => [
                    $image,
                    $items->count(),
                    CardImagesReportCommand::megabytes($items->sum('bytes')),
                ])
                ->values()
                ->all()
        );

        $this->line('Total: '.$pending->count().' imágenes, '.CardImagesReportCommand::megabytes($pending->sum('bytes')));

        return self::SUCCESS;
    }
}
