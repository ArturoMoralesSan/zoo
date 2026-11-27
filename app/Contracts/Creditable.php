<?php

namespace App\Contracts;

use App\Enums\CreditAsset;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dueño de créditos: una tarjeta (ilustración, miniatura, modelo 3D) o una
 * imagen de especie. Ver App\Models\Concerns\HasCredits.
 */
interface Creditable
{
    /**
     * @return MorphMany<Credit, covariant Model>
     */
    public function credits(): MorphMany;

    public function credit(CreditAsset|string $asset): ?Credit;

    /**
     * @return Collection<int, Credit>
     */
    public function creditsFor(CreditAsset|string $asset): Collection;

    /**
     * El dueño tiene el archivo de ese recurso.
     */
    public function hasAsset(CreditAsset $asset): bool;

    /**
     * @param  array<int, CreditAsset>  $visited
     */
    public function hasCompleteCredits(CreditAsset $asset, array $visited = []): bool;
}
