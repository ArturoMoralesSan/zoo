<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Http\Controllers\Admin\Concerns\CardSectionHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\StoreCardCreditRequest;
use App\Http\Resources\Credits\CreditResource;
use App\Models\Card;
use App\Models\Credit;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Créditos de los recursos de una tarjeta (ilustración, miniatura, modelo 3D).
 *
 * Cada recurso tiene un crédito de origen y, si hace falta, créditos de las
 * obras en que se basa y del material de terceros que incluye.
 */
class CardCreditController extends Controller
{
    use CardSectionHelpers;

    /**
     * Créditos de la tarjeta, por recurso.
     */
    public function index(Card $card): Response
    {
        $card->load('credits.license');

        return Inertia::render(
            'admin/card-credits/Index',
            [
                'card' => [
                    ...$this->cardSummary($card),
                    'is_active' => $card->is_active,
                ],

                'assets' => array_map(
                    fn (CreditAsset $asset) => $this->assetSummary($card, $asset),
                    CreditAsset::cases()
                ),

                /*
                 * Lo que mostrará la app (GET /api/v1/credits) para esta tarjeta.
                 */
                'appPreview' => $card->credits
                    ->filter(fn (Credit $credit) => $card->hasAsset($credit->asset))
                    ->sortBy(fn (Credit $credit) => $credit->sortKey())
                    ->map(fn (Credit $credit) => (new CreditResource($credit))->resolve())
                    ->values(),
            ]
        );
    }

    /**
     * Formulario para crear.
     */
    public function create(Request $request, Card $card): Response
    {
        return Inertia::render(
            'admin/card-credits/Create',
            [
                ...$this->formProps($card),

                // Recurso y tipo elegidos desde la lista (?asset=…&role=…).
                'preset' => [
                    'asset' => CreditAsset::tryFrom((string) $request->query('asset'))?->value,
                    'role' => CreditRole::tryFrom((string) $request->query('role'))?->value,
                    'source' => AssetSource::tryFrom((string) $request->query('source'))?->value,
                ],
            ]
        );
    }

    /**
     * Guardar crédito.
     */
    public function store(
        StoreCardCreditRequest $request,
        Card $card
    ): RedirectResponse {
        $credit = $card->credits()->create($request->creditData());

        $this->flashSuccess('Crédito agregado correctamente.');

        return $this->redirectAfterSaving($request, $card, $credit);
    }

    /**
     * Formulario para editar.
     */
    public function edit(Card $card, Credit $credit): Response
    {
        return Inertia::render(
            'admin/card-credits/Edit',
            [
                ...$this->formProps($card),

                'credit' => [
                    ...$credit->only([
                        'id',
                        'license_id',
                        'sort_order',
                        'title',
                        'author_name',
                        'author_url',
                        'source_url',
                        'modified',
                        'modification_note',
                        'attribution_override',
                        'ai_prompt',
                        'notes',
                    ]),
                    'asset' => $credit->asset->value,
                    'role' => $credit->role->value,
                    'source_name' => $credit->source_name?->value,
                    'based_on_asset' => $credit->based_on_asset?->value,
                    'acquired_at' => $credit->acquired_at?->format('Y-m-d'),
                    'ai_checked_at' => $credit->ai_checked_at?->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Actualizar crédito.
     */
    public function update(
        StoreCardCreditRequest $request,
        Card $card,
        Credit $credit
    ): RedirectResponse {
        $credit->update($request->creditData());

        $this->flashSuccess('Crédito actualizado correctamente.');

        return $this->redirectAfterSaving($request, $card, $credit);
    }

    /**
     * Eliminar crédito.
     */
    public function destroy(Card $card, Credit $credit): RedirectResponse
    {
        $credit->delete();

        $this->flashSuccess('Crédito eliminado correctamente.');

        return redirect()->to($this->assetUrl($card, $credit->asset));
    }

    /**
     * Render de un modelo de otro sitio: después de guardar el origen, se
     * registra el modelo como "Basado en".
     */
    private function redirectAfterSaving(Request $request, Card $card, Credit $credit): RedirectResponse
    {
        if ($request->input('then') === 'add_based_on_model') {
            return redirect()->route('admin.cards.credits.create', [
                'card' => $card,
                'asset' => $credit->asset->value,
                'role' => CreditRole::BasedOn->value,
            ]);
        }

        return redirect()->to($this->assetUrl($card, $credit->asset));
    }

    private function assetUrl(Card $card, CreditAsset $asset): string
    {
        return route('admin.cards.credits.index', $card).'#credit-'.$asset->value;
    }

    /**
     * Datos comunes de los formularios de crear y editar.
     *
     * @return array<string, mixed>
     */
    private function formProps(Card $card): array
    {
        $card->load('credits.license');

        $licenses = License::query()->ordered()->get();

        return [
            'card' => $this->cardSummary($card),

            'licenses' => $licenses->map->toFormOption(),

            // Recursos y licencias que admite cada origen.
            'assetSources' => AssetSource::formOptions($licenses),

            'allowRestrictedLicenses' => (bool) config('licensing.allow_restricted'),

            'assets' => array_map(fn (CreditAsset $asset) => [
                'value' => $asset->value,
                'label' => $asset->label(),
                'has_file' => $card->hasAsset($asset),
                'has_origin' => $card->credit($asset) !== null,

                // Créditos del recurso, para mostrar de qué sale un render o un modelo.
                'credits' => $card->creditsFor($asset)->map(fn (Credit $credit) => [
                    'id' => $credit->id,
                    'role' => $credit->role->value,
                    'license_id' => $credit->license_id,
                    'license_short_name' => $credit->license->short_name,
                    'attribution' => $credit->attribution,
                ]),
            ], CreditAsset::cases()),
        ];
    }

    /**
     * Resumen de un recurso para la lista: estado, créditos y avisos.
     *
     * @return array<string, mixed>
     */
    private function assetSummary(Card $card, CreditAsset $asset): array
    {
        $hasFile = $card->hasAsset($asset);
        $credits = $card->creditsFor($asset);

        return [
            'value' => $asset->value,
            'label' => $asset->label(),
            'has_file' => $hasFile,
            'preview_url' => $hasFile && $asset !== CreditAsset::Model
                ? Storage::disk('public')->url($card->{$asset->column()})
                : null,
            'status' => match (true) {
                ! $hasFile => 'no_file',
                $card->hasCompleteCredits($asset) => 'complete',
                default => 'missing',
            },
            'has_origin' => $card->credit($asset) !== null,
            'credits' => $credits->map(fn (Credit $credit) => [
                'id' => $credit->id,
                'role' => $credit->role->value,
                'role_label' => $credit->role->label(),
                'source_label' => $credit->source_name?->label(),
                'source_url' => $credit->source_url,
                'license_short_name' => $credit->license->short_name,
                'license_url' => $credit->license->url,
                'attribution' => $credit->attribution,
                'based_on_asset' => $credit->based_on_asset?->value,
                'is_ai' => $credit->isAiGenerated(),
                'ai_checked_at' => $credit->ai_checked_at?->toIso8601String(),
            ]),
            'alerts' => $hasFile ? $this->alerts($card, $asset) : [],
        ];
    }

    /**
     * Avisos de la cadena de licencias de un recurso.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function alerts(Card $card, CreditAsset $asset): array
    {
        $alerts = [];
        $origin = $card->credit($asset);
        $name = ucfirst($asset->withArticle());

        if ($origin?->isAiGenerated()) {
            $alerts[] = $origin->ai_checked_at
                ? [
                    'type' => 'info',
                    'message' => 'Imagen generada con IA: probablemente no tiene derechos de autor propios, así que otros podrían copiarla. Los retoques hechos a mano sí se protegen.',
                ]
                : [
                    'type' => 'warning',
                    'message' => 'Imagen generada con IA sin revisar. Edita su crédito y confirma la revisión.',
                ];
        }

        $basis = $origin?->based_on_asset;

        if ($basis) {
            $from = $basis->fromPhrase();
            $basisLicense = $card->credit($basis)?->license;

            if (! $card->hasAsset($basis)) {
                $alerts[] = ['type' => 'warning', 'message' => "{$name} se hizo a partir {$from}, pero la tarjeta ya no tiene ese recurso."];
            } elseif (! $card->hasCompleteCredits($basis)) {
                $alerts[] = ['type' => 'warning', 'message' => "{$name} se hizo a partir {$from}, que aún no tiene licencia."];
            } elseif ($basisLicense?->share_alike) {
                $alerts[] = ['type' => 'warning', 'message' => "Se hizo a partir {$from} ({$basisLicense->short_name}): también debe compartirse con esa licencia."];
            } elseif ($basisLicense?->requires_attribution) {
                $alerts[] = ['type' => 'info', 'message' => "Se hizo a partir {$from} ({$basisLicense->short_name}): su crédito también aparece en la app."];
            }
        }

        foreach ($card->creditsFor($asset) as $credit) {
            if ($credit->role !== CreditRole::Origin && $credit->license->share_alike) {
                $alerts[] = ['type' => 'warning', 'message' => "Usa material {$credit->license->short_name}: el recurso resultante debe compartirse con la misma licencia."];
            }
        }

        return $alerts;
    }
}
