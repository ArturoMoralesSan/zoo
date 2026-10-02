<?php

namespace App\Http\Requests\Admin\Card;

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
                Rule::in([
                    'comun',
                    'rara',
                    'epica',
                    'edicion_especial',
                ]),
            ],

            'edition' => [
                'nullable',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'model_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_file' => [
                'nullable',
                'file',
                'mimes:glb,gltf',
                'max:51200',
            ],

            'model_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'model_format' => [
                'nullable',
                'string',
                'max:50',
            ],

            'model_description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}