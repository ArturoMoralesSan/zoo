<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Http\Controllers\Controller;
use App\Models\AssetCredit;
use App\Models\Card;
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
     * Créditos y licencias: un renglón por cada recurso subido de cada tarjeta.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'source' => $request->input('source'),
            'license' => $request->input('license'),
            'asset' => $request->input('asset'),
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
     * Recursos con archivo (o con crédito) de todas las tarjetas.
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
                ->map(fn (CreditAsset $asset) => $this->row($card, $asset, $card->credit($asset)))
                ->filter());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(Card $card, CreditAsset $asset, ?AssetCredit $credit): ?array
    {
        $hasFile = filled($card->{$asset->column()});

        if (! $hasFile) {
            return null;
        }

        $preview = $asset === CreditAsset::Model
            ? ($card->thumbnail_image ?: $card->artwork_image)
            : $card->{$asset->column()};

        return [
            'key' => $card->id.'-'.$asset->value,
            'card' => [
                'id' => $card->id,
                'name' => $card->name,
                'is_active' => $card->is_active,
            ],
            'asset' => $asset->value,
            'asset_label' => $asset->label(),
            'preview_url' => $preview ? Storage::disk('public')->url($preview) : null,
            'missing' => $credit === null,
            'source' => $credit?->source_name?->value,
            'source_label' => $credit?->source_name?->label(),
            'source_url' => $credit?->source_url,
            'license' => $credit?->license?->slug,
            'license_short_name' => $credit?->license?->short_name,
            'license_url' => $credit?->license?->url,
            'is_proprietary' => (bool) $credit?->license?->is_proprietary,
            'attribution' => $credit?->attribution,
            'edit_url' => route('admin.cards.edit', $card).'#credit-'.$asset->value,
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
            && (! $filters['missing'] || $row['missing']);
    }
}
