<?php

namespace App\Http\Requests\Admin\Card;

use App\Enums\CardRarity;
use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'model_file' => [
                'nullable',
                'file',
                'extensions:glb',
                'mimes:glb',
                'max:51200',
            ],

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
}
