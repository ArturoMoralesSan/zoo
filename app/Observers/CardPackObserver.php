<?php

namespace App\Observers;

use App\Models\Card;
use App\Services\CardPack\CardPackBuilder;
use App\Services\CardPack\CardPackCatalog;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Mantiene al día las copias cifradas del paquete sin conexión y su catálogo
 * (GET /api/cards/pack). Ver docs/offline-packs-backend.md.
 *
 * Corre después del commit: el editor guarda archivos y columnas dentro de una
 * transacción, y una reversión no debe dejar .bin de archivos que no quedaron.
 * Es síncrono (sin cola): cifrar unos MB tarda milisegundos y así no hay un
 * momento en que la tarjeta ya cambió pero su copia todavía no existe.
 */
class CardPackObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private CardPackBuilder $builder) {}

    /**
     * No se mira wasChanged(): varias actualizaciones en una misma transacción
     * llegan aquí con los cambios de la última. sync() es idempotente y barato
     * cuando no hay nada nuevo.
     */
    public function saved(Card $card): void
    {
        try {
            $this->builder->sync($card);
        } catch (Throwable $exception) {
            // La tarjeta ya se guardó; `cards:packs:build` la repara después.
            report($exception);
        }

        CardPackCatalog::forget();
    }

    public function deleted(Card $card): void
    {
        $this->builder->remove($card->id);

        CardPackCatalog::forget();
    }
}
