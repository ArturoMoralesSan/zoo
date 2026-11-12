<?php

namespace App\Console\Commands;

use App\Exceptions\CardImageOptimizationException;
use App\Models\Card;
use App\Services\CardImageOptimizer;
use Illuminate\Console\Command;

/**
 * Convierte a WebP las imágenes guardadas usando el codificador del servidor
 * y borra cada original tras reemplazarla. Sin Imagick ni GD, usar el panel.
 */
class CardImagesOptimizeCommand extends Command
{
    protected $signature = 'cards:images:optimize
        {--card= : Solo esta tarjeta (id)}
        {--image= : Solo generated, thumbnail o artwork}
        {--limit= : Máximo de imágenes a procesar}
        {--dry-run : Mostrar lo que se haría sin cambiar nada}';

    protected $description = 'Convierte a WebP las imágenes de tarjetas con el codificador del servidor';

    public function handle(CardImageOptimizer $optimizer): int
    {
        $image = $this->option('image');

        if ($image !== null && ! array_key_exists($image, CardImageOptimizer::FIELDS)) {
            $this->error('--image debe ser: '.implode(', ', array_keys(CardImageOptimizer::FIELDS)));

            return self::INVALID;
        }

        $pending = collect($optimizer->pending(
            $this->option('card') ? (int) $this->option('card') : null,
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
                ['Tarjeta', 'Imagen', 'Ruta', 'Tamaño'],
                $pending->map(fn ($item) => [
                    $item['card_id'].' · '.$item['card_name'],
                    $item['image'],
                    $item['path'],
                    CardImagesReportCommand::megabytes($item['bytes']),
                ])->all()
            );

            return self::SUCCESS;
        }

        if (! $optimizer->serverEncoder()) {
            $this->error('El servidor no tiene Imagick ni GD con WebP. Usa "Optimizar imágenes" en el panel de tarjetas.');

            return self::FAILURE;
        }

        $before = 0;
        $after = 0;
        $failed = 0;

        foreach ($pending as $item) {
            $label = "#{$item['card_id']} {$item['image']}";

            try {
                $result = $optimizer->replace(
                    Card::findOrFail($item['card_id']),
                    $item['image'],
                    $item['path']
                );
            } catch (CardImageOptimizationException $exception) {
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
