<?php

namespace App\Http\Requests\Admin\Card;

use App\Enums\CardRarity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCardRaritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
            ],

            'settings.*.rarity' => [
                'required',
                'distinct',
                Rule::enum(CardRarity::class),
            ],

            'settings.*.questions_per_duel' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],

            'settings.*.retries' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'settings.*.questions_per_duel' => 'preguntas por duelo',
            'settings.*.retries' => 'reintentos',
        ];
    }
}
