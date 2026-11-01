<?php

namespace App\Services\Licensing;

use App\Enums\CreditAsset;
use App\Http\Resources\Credits\CreditSectionResource;
use App\Models\Card;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Créditos públicos para la pantalla de créditos de la app
 * (GET /api/v1/credits). Ver docs/api-credits.md.
 *
 * La respuesta se arma una vez y se guarda en caché; CreditsCacheObserver
 * la invalida cuando cambia un crédito, una licencia, una tarjeta o una especie.
 */
class CreditsCatalog
{
    public const CACHE_KEY = 'licensing.credits.v1';

    /**
     * Respaldo por si un cambio no pasa por Eloquent (p. ej. SQL manual).
     */
    private const CACHE_TTL = 86400;

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>, etag: string}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>, etag: string}
     */
    private function build(): array
    {
        $credits = $this->activeCardCredits()->groupBy('creditable_id');

        $cards = Card::query()
            ->select(['id', 'species_id', 'name', 'sort_order'])
            ->with('species:id,common_name')
            ->whereIn('id', $credits->keys())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $data = $cards
            ->map(fn (Card $card) => (new CreditSectionResource(
                $card,
                $credits->get($card->id)
                    ->sortBy(fn (Credit $credit) => $credit->sortKey())
                    ->values()
            ))->resolve())
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'generated_at' => Carbon::now()->toIso8601String(),
                'cards' => count($data),
                'credits' => array_sum(array_map(fn ($section) => count($section['credits']), $data)),
            ],
            'etag' => sha1(json_encode($data)),
        ];
    }

    /**
     * Todos los créditos de las tarjetas activas cuyo recurso sigue
     * existiendo, incluido el contenido propio. Las tarjetas inactivas
     * nunca se incluyen (su nombre puede no ser público).
     *
     * @return Collection<int, Credit>
     */
    private function activeCardCredits(): Collection
    {
        return Credit::query()
            ->select('credits.*')
            ->join('cards', function (JoinClause $join) {
                $join->on('cards.id', '=', 'credits.creditable_id')
                    ->where('credits.creditable_type', (new Card)->getMorphClass());
            })
            ->where('cards.is_active', true)
            ->where(function (Builder $query) {
                foreach (CreditAsset::cases() as $asset) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('credits.asset', $asset->value)
                        ->whereNotNull('cards.'.$asset->column())
                        ->where('cards.'.$asset->column(), '!=', ''));
                }
            })
            ->with('license')
            ->get();
    }
}
