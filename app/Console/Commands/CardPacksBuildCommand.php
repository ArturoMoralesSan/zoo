<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Services\CardPack\CardPackBuilder;
use App\Services\CardPack\CardPackCatalog;
use Illuminate\Console\Command;
use Throwable;

/**
 * Cifra los archivos de las tarjetas para el paquete sin conexión.
 * Ver docs/offline-packs-backend.md.
 *
 * Idempotente: sin --force solo crea lo que falta y borra lo que sobra.
 * Con --force cifra todo de nuevo (necesario si cambia CARD_PACK_SECRET).
 */
class CardPacksBuildCommand extends Command
{
    protected $signature = 'cards:packs:build
        {--card=* : Solo estas tarjetas (id)}
        {--force : Volver a cifrar aunque el archivo ya exista}';

    protected $description = 'Crea o actualiza las copias cifradas del paquete sin conexión de las tarjetas';

    public function handle(CardPackBuilder $builder): int
    {
        if (! $builder->enabled()) {
            $this->warn('Los paquetes están desactivados (CARD_PACKS_ENABLED=false).');

            return self::FAILURE;
        }

        $ids = array_map('intval', (array) $this->option('card'));
        $changed = 0;
        $failed = 0;

        $cards = Card::query()
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->orderBy('id')
            ->get();

        foreach ($cards as $card) {
            try {
                if ($builder->sync($card, (bool) $this->option('force'))) {
                    $changed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Tarjeta {$card->id}: {$exception->getMessage()}");
            }
        }

        CardPackCatalog::forget();

        $this->info("Tarjetas revisadas: {$cards->count()}. Actualizadas: {$changed}. Con error: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
