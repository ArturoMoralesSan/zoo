<?php

namespace App\Concerns;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Enums\CreditRole;
use App\Models\Card;
use App\Models\Credit;
use App\Models\License;
use App\Rules\CommonsFileUrl;
use App\Rules\LicenseMatchesSource;
use App\Rules\SelectableLicense;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas de los créditos de los recursos de la tarjeta.
 *
 * - Un crédito (sección "Créditos" de la tarjeta): creditRules() y creditChecks().
 * - Formulario de la tarjeta: assetCreditsCheck(), que no deja activar una
 *   tarjeta con recursos sin licencia.
 */
trait AssetCreditValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function creditRules(Card $card, ?Credit $current = null): array
    {
        $asset = CreditAsset::tryFrom((string) $this->input('asset'));
        $role = CreditRole::tryFrom((string) $this->input('role'));
        $source = AssetSource::tryFrom((string) $this->input('source_name'));
        $license = License::find($this->input('license_id'));

        /*
         * Las licencias con plantilla (Meshy, herramientas de IA) ya dicen a
         * quién se atribuye; no piden autor ni URL.
         */
        $needsAttribution = $license?->requires_attribution && ! $license->attribution_template;

        $rules = [
            'asset' => [
                'required',
                Rule::enum(CreditAsset::class),
                function (string $attribute, mixed $value, Closure $fail) use ($card, $asset) {
                    if ($asset && ! $card->hasAsset($asset)) {
                        $fail("La tarjeta no tiene {$asset->withArticle()}: súbela primero en el formulario de la tarjeta.");
                    }
                },
            ],

            'role' => [
                'required',
                Rule::enum(CreditRole::class),
            ],

            'source_name' => [
                'required',
                Rule::enum(AssetSource::class),
                function (string $attribute, mixed $value, Closure $fail) use ($asset, $role, $source) {
                    if ($asset && $role && $source && ! $source->allowsAsset($asset, $role)) {
                        $fail($asset === CreditAsset::Model
                            ? "{$source->label()} no ofrece modelos 3D: elige el origen del modelo."
                            : "{$source->label()} solo ofrece modelos 3D: para una imagen hecha con un modelo, usa «Render de un modelo 3D».");
                    }
                },
            ],

            'license_id' => [
                'required',
                'integer',
                'exists:licenses,id',
                new SelectableLicense(array_filter([$current?->license_id])),
                new LicenseMatchesSource($source),
            ],

            'source_url' => [
                $needsAttribution ? 'required' : 'nullable',
                'url:http,https',
                'max:2048',
                ...($source === AssetSource::WikimediaCommons ? [new CommonsFileUrl] : []),
            ],

            'title' => ['nullable', 'string', 'max:255'],

            'author_name' => [
                $needsAttribution ? 'required' : 'nullable',
                'string',
                'max:255',
            ],

            'author_url' => ['nullable', 'url:http,https', 'max:2048'],

            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],

            'modified' => ['boolean'],

            'modification_note' => ['nullable', 'string', 'max:255'],

            'attribution_override' => ['nullable', 'string', 'max:1000'],

            'sort_order' => ['nullable', 'integer', 'min:0'],

            'notes' => ['nullable', 'string', 'max:5000'],

            /*
             * Solo el origen se hace a partir de otro recurso de la tarjeta
             * (render del modelo, modelo de la ilustración).
             */
            'based_on_asset' => $role === CreditRole::Origin
                ? [
                    'nullable',
                    Rule::enum(CreditAsset::class),
                    'different:asset',
                    function (string $attribute, mixed $value, Closure $fail) use ($card) {
                        $basis = CreditAsset::tryFrom((string) $value);

                        if ($basis && ! $card->hasAsset($basis)) {
                            $fail("La tarjeta no tiene {$basis->withArticle()}.");
                        }
                    },
                ]
                : ['exclude'],
        ];

        /*
         * Imagen generada con IA: la indicación se guarda como registro y la
         * revisión es obligatoria.
         */
        if ($source === AssetSource::Ai) {
            $rules['ai_prompt'] = ['nullable', 'string', 'max:5000'];
            $rules['ai_checked'] = ['accepted'];
        }

        /*
         * CompartirIgual: confirmación en el formulario (no se guarda).
         */
        if ($license?->share_alike) {
            $rules['share_alike_ack'] = ['accepted'];
        }

        return $rules;
    }

    /**
     * Comprobaciones con los demás créditos de la tarjeta.
     */
    protected function creditChecks(Card $card, ?Credit $current = null): Closure
    {
        return function (Validator $validator) use ($card, $current) {
            $asset = CreditAsset::tryFrom((string) $this->input('asset'));
            $role = CreditRole::tryFrom((string) $this->input('role'));

            if (! $asset || $role !== CreditRole::Origin) {
                return;
            }

            $origin = $card->credit($asset);

            if ($origin && $origin->id !== $current?->id) {
                $validator->errors()->add(
                    'role',
                    'Este recurso ya tiene crédito de origen. Edítalo, o registra este como «Basado en» o «Incluye».'
                );
            }

            /*
             * Sin ciclos: un recurso no puede salir de otro que sale de él.
             */
            $basis = CreditAsset::tryFrom((string) $this->input('based_on_asset'));

            if ($basis && $card->credit($basis)?->based_on_asset === $asset) {
                $validator->errors()->add(
                    'based_on_asset',
                    ucfirst($basis->withArticle())." ya se hizo a partir {$asset->fromPhrase()}."
                );
            }
        };
    }

    /**
     * Una tarjeta no se activa (ni se crea activa) si algún recurso subido no
     * tiene licencia completa. Una tarjeta ya activa sigue activa.
     */
    protected function assetCreditsCheck(?Card $card = null): Closure
    {
        return function (Validator $validator) use ($card) {
            if (! $this->boolean('is_active') || $card?->is_active) {
                return;
            }

            $missing = array_filter(
                CreditAsset::cases(),
                fn (CreditAsset $asset) => $this->creditAssetWillExist($asset, $card)
                    && ! $card?->hasCompleteCredits($asset)
            );

            if (! $missing) {
                return;
            }

            $labels = array_map(fn (CreditAsset $asset) => $asset->label(), $missing);

            $validator->errors()->add(
                'is_active',
                'No se puede activar la tarjeta: falta la licencia de '.self::joinLabels($labels).'. '.
                ($card
                    ? 'Regístrala en la sección Créditos de la tarjeta.'
                    : 'Crea la tarjeta inactiva, registra los créditos y después actívala.')
            );
        };
    }

    /**
     * @return array<string, string>
     */
    protected function creditMessages(): array
    {
        return [
            'license_id.required' => 'Elige la licencia.',
            'source_name.required' => 'Elige el origen.',
            'author_name.required' => 'La licencia elegida exige indicar el autor.',
            'source_url.required' => 'La licencia elegida exige indicar la URL de origen.',
            'share_alike_ack.accepted' => 'Confirma que el recurso resultante se compartirá con la misma licencia.',
            'ai_checked.accepted' => 'Confirma la revisión de la imagen generada con IA.',
            'based_on_asset.different' => 'Un recurso no puede hacerse a partir de sí mismo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function creditAttributes(): array
    {
        return [
            'asset' => 'recurso',
            'role' => 'tipo de crédito',
            'license_id' => 'licencia',
            'source_name' => 'origen',
            'source_url' => 'URL de origen',
            'title' => 'título',
            'author_name' => 'autor',
            'author_url' => 'URL del autor',
            'acquired_at' => 'fecha de obtención',
            'modification_note' => 'nota de modificación',
            'attribution_override' => 'texto del crédito',
            'based_on_asset' => 'recurso de origen',
            'ai_prompt' => 'indicación (prompt)',
            'notes' => 'notas',
        ];
    }

    /**
     * El recurso existirá después de guardar la tarjeta.
     */
    private function creditAssetWillExist(CreditAsset $asset, ?Card $card): bool
    {
        $uploaded = $asset === CreditAsset::Model
            ? $this->hasFile('model_file') || $this->hasFile('model_original_file')
            : $this->hasFile($asset->column());

        return $uploaded || (bool) $card?->hasAsset($asset);
    }

    /**
     * "a", "a y b", "a, b y c".
     *
     * @param  array<int, string>  $labels
     */
    private static function joinLabels(array $labels): string
    {
        $labels = array_values($labels);
        $last = array_pop($labels);

        return $labels ? implode(', ', $labels).' y '.$last : $last;
    }
}
