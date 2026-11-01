<?php

namespace App\Concerns;

use App\Enums\AssetSource;
use App\Enums\CreditAsset;
use App\Models\Card;
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
 * Forma: credits[artwork|thumbnail|model][campo]. Un crédito se valida si
 * se envió (licencia u origen) o si su recurso se sube en esta petición;
 * los demás se ignoran, para que una tarjeta sin créditos se pueda seguir
 * editando. Volver a escalar el modelo (solo llega model_file) conserva su
 * crédito y no lo exige.
 */
trait AssetCreditValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function assetCreditRules(?Card $card = null): array
    {
        $rules = [];

        foreach (CreditAsset::cases() as $asset) {
            if (! $this->creditIsValidated($asset, $card)) {
                continue;
            }

            $prefix = 'credits.'.$asset->value.'.';

            $license = License::find($this->input($prefix.'license_id'));
            $source = AssetSource::tryFrom((string) $this->input($prefix.'source_name'));

            /*
             * Las licencias con plantilla (Meshy gratuito) ya dicen a quién
             * se atribuye; no piden autor ni URL.
             */
            $needsAttribution = $license?->requires_attribution && ! $license->attribution_template;

            $rules += [
                $prefix.'license_id' => [
                    'required',
                    'integer',
                    'exists:licenses,id',
                    new SelectableLicense(array_filter([$card?->credit($asset)?->license_id])),
                    new LicenseMatchesSource($source),
                ],

                $prefix.'source_name' => [
                    'required',
                    Rule::enum(AssetSource::class),
                    function (string $attribute, mixed $value, Closure $fail) use ($asset, $source) {
                        if ($source && ! in_array($asset, $source->assets(), true)) {
                            $fail($asset === CreditAsset::Model
                                ? "{$source->label()} no ofrece modelos 3D: elige el origen del modelo."
                                : "{$source->label()} solo ofrece modelos 3D: elige el origen de la imagen.");
                        }
                    },
                ],

                $prefix.'source_url' => [
                    $needsAttribution ? 'required' : 'nullable',
                    'url:http,https',
                    'max:2048',
                    ...($source === AssetSource::WikimediaCommons ? [new CommonsFileUrl] : []),
                ],

                $prefix.'title' => ['nullable', 'string', 'max:255'],

                $prefix.'author_name' => [
                    $needsAttribution ? 'required' : 'nullable',
                    'string',
                    'max:255',
                ],

                $prefix.'author_url' => ['nullable', 'url:http,https', 'max:2048'],

                $prefix.'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],

                $prefix.'modified' => ['boolean'],

                $prefix.'modification_note' => ['nullable', 'string', 'max:255'],

                $prefix.'attribution_override' => ['nullable', 'string', 'max:1000'],

                $prefix.'notes' => ['nullable', 'string', 'max:5000'],
            ];

            /*
             * CompartirIgual: confirmación en el formulario (no se guarda).
             */
            if ($license?->share_alike) {
                $rules[$prefix.'share_alike_ack'] = ['accepted'];
            }

            if ($asset === CreditAsset::Model) {
                $rules[$prefix.'based_on_artwork'] = ['boolean'];
            }
        }

        return $rules;
    }

    /**
     * Comprobaciones que dependen de varios recursos:
     * - Una tarjeta no se activa (ni se crea activa) si algún recurso
     *   subido no tiene crédito. Una tarjeta ya activa sigue activa.
     * - "Generado a partir de la ilustración" necesita el crédito de la ilustración.
     */
    protected function assetCreditsCheck(?Card $card = null): Closure
    {
        return function (Validator $validator) use ($card) {
            $hasCredit = fn (CreditAsset $asset) => $this->creditIsSent($asset) ||
                ($card?->credit($asset) !== null);

            if (
                $this->boolean('credits.model.based_on_artwork') &&
                ! $hasCredit(CreditAsset::Artwork)
            ) {
                $validator->errors()->add(
                    'credits.model.based_on_artwork',
                    'Primero registra la licencia de la ilustración de esta tarjeta.'
                );
            }

            if (! $this->boolean('is_active') || $card?->is_active) {
                return;
            }

            $missing = array_filter(
                CreditAsset::cases(),
                fn (CreditAsset $asset) => $this->creditAssetWillExist($asset, $card) && ! $hasCredit($asset)
            );

            if ($missing) {
                $labels = array_map(fn (CreditAsset $asset) => $asset->label(), $missing);

                $validator->errors()->add(
                    'is_active',
                    'No se puede activar la tarjeta: falta la licencia de '.self::joinLabels($labels).'.'
                );
            }
        };
    }

    /**
     * @return array<string, string>
     */
    protected function assetCreditMessages(): array
    {
        return [
            'credits.*.license_id.required' => 'Elige la licencia del recurso.',
            'credits.*.source_name.required' => 'Elige el origen del recurso.',
            'credits.*.author_name.required' => 'La licencia elegida exige indicar el autor.',
            'credits.*.source_url.required' => 'La licencia elegida exige indicar la URL de origen.',
            'credits.*.share_alike_ack.accepted' => 'Confirma que la tarjeta resultante se compartirá con la misma licencia.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function assetCreditAttributes(): array
    {
        return [
            'credits.*.license_id' => 'licencia',
            'credits.*.source_name' => 'origen',
            'credits.*.source_url' => 'URL de origen',
            'credits.*.title' => 'título',
            'credits.*.author_name' => 'autor',
            'credits.*.author_url' => 'URL del autor',
            'credits.*.acquired_at' => 'fecha de obtención',
            'credits.*.modified' => 'modificada',
            'credits.*.modification_note' => 'nota de modificación',
            'credits.*.attribution_override' => 'texto del crédito',
            'credits.*.notes' => 'notas',
        ];
    }

    /**
     * Se valida el crédito si se envió o si su recurso se sube ahora, y solo
     * si el recurso existirá después de guardar.
     */
    private function creditIsValidated(CreditAsset $asset, ?Card $card): bool
    {
        return $this->creditUploadsAsset($asset) ||
            ($this->creditIsSent($asset) && $this->creditAssetWillExist($asset, $card));
    }

    private function creditIsSent(CreditAsset $asset): bool
    {
        return $this->filled('credits.'.$asset->value.'.license_id') ||
            $this->filled('credits.'.$asset->value.'.source_name');
    }

    /**
     * Archivo nuevo para el recurso. En el modelo, solo cuenta el original:
     * volver a escalar envía solo el archivo escalado.
     */
    private function creditUploadsAsset(CreditAsset $asset): bool
    {
        return $this->hasFile(match ($asset) {
            CreditAsset::Model => 'model_original_file',
            default => $asset->column(),
        });
    }

    private function creditAssetWillExist(CreditAsset $asset, ?Card $card): bool
    {
        return $this->creditUploadsAsset($asset) ||
            ($asset === CreditAsset::Model && $this->hasFile('model_file') && ! $card?->model_file) ||
            filled($card?->{$asset->column()});
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
