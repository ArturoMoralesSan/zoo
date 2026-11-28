<?php

namespace App\Http\Requests\Admin\Species;

use App\Services\SpeciesInfoGeneratorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSpeciesInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'common_name' => [
                'required',
                'string',
                'max:255',
            ],

            'scientific_name' => [
                'required',
                'string',
                'max:255',
            ],

            'fields' => [
                'required',
                'array',
                'min:1',
            ],

            'fields.*' => [
                'distinct',
                Rule::in(SpeciesInfoGeneratorService::FIELDS),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'common_name' => 'nombre común',
            'scientific_name' => 'nombre científico',
            'fields' => 'campos a completar',
            'fields.*' => 'campo a completar',
        ];
    }
}
