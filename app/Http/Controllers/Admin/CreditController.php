<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Credit;
use App\Models\License;
use App\Services\Licensing\CommonsMetadataFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Créditos y licencias: un renglón por crédito, más uno por cada recurso
     * subido que aún no tiene licencia completa.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'source' => $request->input('source'),
            'license' => $request->input('license'),
            'asset' => $request->input('asset'),
            'role' => $request->input('role'),
            'missing' => $request->boolean('missing') ? '1' : null,
        ];

        $rows = $this->rows()
            ->filter(fn (array $row) => $this->matches($row, $filters))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        $credits = (new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        ))->withQueryString();

        return Inertia::render(
            'admin/credits/Index',
            [
                'credits' => $credits,

                'filters' => $filters,

                'licenses' => License::query()
                    ->ordered()
                    ->get(['id', 'slug', 'short_name']),

                'assetSources' => array_map(fn (AssetSource $source) => [
                    'value' => $source->value,
                    'label' => $source->label(),
                ], AssetSource::cases()),

                'assets' => array_map(fn (CreditAsset $asset) => [
                    'value' => $asset->value,
                    'label' => $asset->label(),
                ], CreditAsset::cases()),

                'roles' => array_map(fn (CreditRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ], CreditRole::cases()),

                'missingCount' => $rows->where('missing', true)->count(),
            ]
        );
    }

    /**
     * Datos de un archivo de Wikimedia Commons para autocompletar el formulario.
     * No guarda nada: el administrador revisa antes de enviar.
     */
    public function commonsLookup(
        Request $request,
        CommonsMetadataFetcher $fetcher
    ): JsonResponse {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ], [], [
            'url' => 'URL de origen',
        ]);

        return response()->json([
            'success' => true,
            'data' => $fetcher->fetch($validated['url']),
        ]);
    }

    /**
     * Créditos de los recursos con archivo de todas las tarjetas, y los
     * recursos sin licencia completa.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(): Collection
    {
        return Card::query()
            ->select(['id', 'name', 'is_active', 'sort_order', 'thumbnail_image', 'artwork_image', 'model_file'])
            ->with('credits.license')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->flatMap(fn (Card $card) => collect(CreditAsset::cases())
                ->filter(fn (CreditAsset $asset) => $card->hasAsset($asset))
                ->flatMap(fn (CreditAsset $asset) => [
                    ...($card->hasCompleteCredits($asset) ? [] : [$this->row($card, $asset, null)]),
                    ...$card->creditsFor($asset)->map(fn (Credit $credit) => $this->row($card, $asset, $credit)),
                ]));
    }

    /**
     * Renglón de un crédito, o de un recurso al que le falta la licencia.
     *
     * @return array<string, mixed>
     */
    private function row(Card $card, CreditAsset $asset, ?Credit $credit): array
    {
        $preview = $asset === CreditAsset::Model
            ? ($card->thumbnail_image ?: $card->artwork_image)
            : $card->{$asset->column()};

        return [
            'key' => $card->id.'-'.$asset->value.'-'.($credit?->id ?? 'missing'),
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'is_active' => $card->is_active,
            ],
            'asset' => $asset->value,
            'asset_label' => $asset->label(),
            'preview_url' => $preview ? Storage::disk('public')->url($preview) : null,
            'missing' => $credit === null,
            'role' => $credit?->role->value,
            'role_label' => $credit?->role->label(),
            'source' => $credit?->source_name?->value,
            'source_label' => $credit?->source_name?->label(),
            'source_url' => $credit?->source_url,
            'license' => $credit?->license?->slug,
            'license_short_name' => $credit?->license?->short_name,
            'license_url' => $credit?->license?->url,
            'is_proprietary' => (bool) $credit?->license?->is_proprietary,
            'attribution' => $credit?->attribution,
            'edit_url' => $credit
                ? route('admin.cards.credits.edit', [$card, $credit])
                : route('admin.cards.credits.index', $card).'#credit-'.$asset->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $filters
     */
    private function matches(array $row, array $filters): bool
    {
        $search = mb_strtolower(trim((string) $filters['search']));

        return ($search === '' || str_contains(mb_strtolower($row['card']['name']), $search))
            && (! $filters['source'] || $row['source'] === $filters['source'])
            && (! $filters['license'] || $row['license'] === $filters['license'])
            && (! $filters['asset'] || $row['asset'] === $filters['asset'])
            && (! $filters['role'] || $row['role'] === $filters['role'])
            && (! $filters['missing'] || $row['missing']);
    }
}
