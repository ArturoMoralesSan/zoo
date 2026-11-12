<?php

namespace App\Console\Commands;

use App\Services\CardImageOptimizer;
use Illuminate\Console\Command;

/**
 * Solo lectura: imágenes de tarjetas que aún no están en WebP optimizado.
 */
class CardImagesReportCommand extends Command
{
    protected $signature = 'cards:images:report';

    protected $description = 'Muestra las imágenes de tarjetas pendientes de optimizar (solo lectura)';

    public function handle(CardImageOptimizer $optimizer): int
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
                    self::megabytes($items->sum('bytes')),
                ])
                ->values()
                ->all()
        );

        $this->line('Total: '.$pending->count().' imágenes, '.self::megabytes($pending->sum('bytes')));

        return self::SUCCESS;
    }

    public static function megabytes(int|float $bytes): string
    {
        return number_format($bytes / 1048576, 2).' MB';
    }
}
