<?php

namespace App\Models\Concerns;

use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Créditos de los recursos de un modelo (ver App\Contracts\Creditable).
 * El modelo define hasAsset().
 */
trait HasCredits
{
    /**
     * @return MorphMany<Credit, $this>
     */
    public function credits(): MorphMany
    {
        return $this->morphMany(Credit::class, 'creditable');
    }

    /**
     * Crédito de origen de un recurso, si lo tiene.
     */
    public function credit(CreditAsset|string $asset): ?Credit
    {
        return $this->creditsFor($asset)->first(
            fn (Credit $credit) => $credit->role === CreditRole::Origin
        );
    }

    /**
     * Todos los créditos de un recurso, en orden.
     *
     * @return Collection<int, Credit>
     */
    public function creditsFor(CreditAsset|string $asset): Collection
    {
        $asset = CreditAsset::from($asset instanceof CreditAsset ? $asset->value : $asset);

        return $this->credits
            ->filter(fn (Credit $credit) => $credit->asset === $asset)
            ->sortBy(fn (Credit $credit) => $credit->sortKey())
            ->values();
    }

    /**
     * El recurso tiene su crédito de origen y, si se hizo a partir de otro
     * recurso del mismo dueño (render del modelo…), ese también lo tiene.
     *
     * @param  array<int, CreditAsset>  $visited
     */
    public function hasCompleteCredits(CreditAsset $asset, array $visited = []): bool
    {
        $origin = $this->credit($asset);

        if (! $origin) {
            return false;
        }

        $basis = $origin->based_on_asset;

        if (! $basis || in_array($basis, $visited, true) || $basis === $asset) {
            return true;
        }

        return $this->hasAsset($basis) && $this->hasCompleteCredits($basis, [...$visited, $asset]);
    }
}
