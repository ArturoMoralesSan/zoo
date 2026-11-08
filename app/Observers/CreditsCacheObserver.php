<?php

namespace App\Observers;

use App\Enums\CreditAsset;
use App\Models\Card;
use App\Models\Species;
use App\Services\Licensing\CreditsCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalida la respuesta en caché de GET /api/v1/credits cuando cambia algo
 * que aparece en ella (créditos, licencias, tarjetas y nombre de la especie).
 */
class CreditsCacheObserver
{
    /**
     * Columnas de la tarjeta que afectan a los créditos publicados.
     */
    private const CARD_COLUMNS = [
        'is_active',
        'name',
        'sort_order',
        'species_id',
    ];

    public function created(Model $model): void
    {
        CreditsCatalog::forget();
    }

    public function updated(Model $model): void
    {
        if ($this->affectsCredits($model)) {
            CreditsCatalog::forget();
        }
    }

    public function deleted(Model $model): void
    {
        CreditsCatalog::forget();
    }

    private function affectsCredits(Model $model): bool
    {
        return match (true) {
            $model instanceof Card => $model->wasChanged([
                ...self::CARD_COLUMNS,
                ...array_map(fn (CreditAsset $asset) => $asset->column(), CreditAsset::cases()),
            ]),
            $model instanceof Species => $model->wasChanged('common_name'),
            default => true,
        };
    }
}
