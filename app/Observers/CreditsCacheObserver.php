<?php

namespace App\Observers;

use App\Enums\CreditAsset;
use App\Models\Card;
use App\Models\Species;
use App\Models\SpeciesImage;
use App\Services\Licensing\CreditsCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalida la respuesta en caché de GET /api/v1/credits cuando cambia algo
 * que aparece en ella (créditos, licencias, tarjetas, especies y sus imágenes).
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
                ...array_map(fn (CreditAsset $asset) => $asset->column(), CreditAsset::forCards()),
            ]),
            $model instanceof Species => $model->wasChanged(['common_name', 'scientific_name', 'slug', 'is_active']),
            $model instanceof SpeciesImage => $model->wasChanged(['path', 'type', 'is_active', 'sort_order', 'alt_text']),
            default => true,
        };
    }
}
