<?php

namespace App\Http\Requests\Admin\Card;

use App\Concerns\AssetCreditValidationRules;
use App\Concerns\CardDesignValidationRules;
use App\Concerns\CardMessagesValidationRules;
use App\Concerns\CardModelValidationRules;
use App\Enums\CardRarity;
use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCardRequest extends FormRequest
{
    use AssetCreditValidationRules;
    use CardDesignValidationRules;
    use CardMessagesValidationRules;
    use CardModelValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->cardDesignRules(),

            ...$this->cardMessagesRules(),

            'species_id' => [
                'required',
                'integer',
                'exists:species,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'rarity' => [
                'required',
                'string',
                Rule::enum(CardRarity::class),
            ],

            'thumbnail_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'artwork_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
             * Imagen final generada en el navegador a partir de la plantilla.
             * WebP a 2× (lib/imageOptimize.ts); PNG solo si el navegador no
             * pudo codificar WebP. Nunca mayor que el render a 3×.
             */
            'generated_image' => [
                'nullable',
                'image',
                'mimes:webp,png',
                'max:15360',
                'dimensions:max_width=1716,max_height=3048',
            ],

            ...$this->cardModelRules(),


            'is_active' => [
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'astucia' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'rapidez' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'fuerza' => [
                'required',
                'integer',
                'between:1,100',
            ],

            'duel_settings_source' => [
                'required',
                'string',
                Rule::in([
                    Card::DUEL_SETTINGS_RARITY,
                    Card::DUEL_SETTINGS_CARD,
                ]),
            ],

            'questions_per_duel' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:50',
            ],

            'retries' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:100',
            ],

            'cooldown_minutes' => [
                'nullable',
                'required_if:duel_settings_source,'.Card::DUEL_SETTINGS_CARD,
                'integer',
                'min:1',
                'max:43200',
            ],
        ];
    }

    /**
     * Activación de la tarjeta según sus créditos.
     */
    public function after(): array
    {
        return [
            $this->assetCreditsCheck(),
        ];
    }

    public function attributes(): array
    {
        return $this->cardModelAttributes();
    }
}
