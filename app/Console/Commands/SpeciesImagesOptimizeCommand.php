<?php

namespace App\Console\Commands;

use App\Exceptions\ImageOptimizationException;
use App\Models\SpeciesImage;
use App\Services\SpeciesImageOptimizer;
use Illuminate\Console\Command;

/**
 * Convierte a WebP las imágenes de especies guardadas usando el codificador
 * del servidor y borra cada original tras reemplazarla. Sin Imagick ni GD,
 * usar el panel.
 */
class SpeciesImagesOptimizeCommand extends Command
{
    protected $signature = 'species:images:optimize
        {--species= : Solo esta especie (id)}
        {--image= : Solo main, thumbnail o gallery}
        {--limit= : Máximo de imágenes a procesar}
        {--dry-run : Mostrar lo que se haría sin cambiar nada}';

    protected $description = 'Convierte a WebP las imágenes de especies con el codificador del servidor';

    public function handle(SpeciesImageOptimizer $optimizer): int
    {
        $image = $this->option('image');

        if ($image !== null && ! in_array($image, SpeciesImageOptimizer::KINDS, true)) {
            $this->error('--image debe ser: '.implode(', ', SpeciesImageOptimizer::KINDS));

            return self::INVALID;
        }

        $pending = collect($optimizer->pending(
            $this->option('species') ? (int) $this->option('species') : null,
            $image
        ));

        if ($this->option('limit')) {
            $pending = $pending->take((int) $this->option('limit'));
        }

        if ($pending->isEmpty()) {
            $this->info('No hay imágenes pendientes.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Especie', 'Imagen', 'Ruta', 'Tamaño'],
                $pending->map(fn ($item) => [
                    $item['species_id'].' · '.$item['species_name'],
                    $item['image'],
                    $item['path'],
                    CardImagesReportCommand::megabytes($item['bytes']),
                ])->all()
            );

            return self::SUCCESS;
        }

        if (! $optimizer->serverEncoder()) {
            $this->error('El servidor no tiene Imagick ni GD con WebP. Usa "Optimizar imágenes" en el listado de especies.');

            return self::FAILURE;
        }

        $before = 0;
        $after = 0;
        $failed = 0;

        foreach ($pending as $item) {
            $label = "#{$item['species_id']} {$item['image']} (imagen {$item['image_id']})";

            try {
                $result = $optimizer->replace(
                    SpeciesImage::findOrFail($item['image_id']),
                    $item['path']
                );
            } catch (ImageOptimizationException $exception) {
                $failed++;
                $this->warn("{$label}: {$exception->getMessage()}");

                continue;
            }

            $before += $result['original_bytes'];
            $after += $result['optimized_bytes'];

            $this->line(sprintf(
                '%s: %s → %s',
                $label,
                CardImagesReportCommand::megabytes($result['original_bytes']),
                CardImagesReportCommand::megabytes($result['optimized_bytes'])
            ));
        }

        $this->info(sprintf(
            'Listo: %s → %s. Sin cambios: %d.',
            CardImagesReportCommand::megabytes($before),
            CardImagesReportCommand::megabytes($after),
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
