<?php

namespace App\Http\Requests\Admin\MapMarker;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMapMarkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('map-markers.edit') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'icon' => [
                'nullable',
                'string',
                'max:100',
            ],

            'color' => [
                'nullable',
                'string',
                'max:50',
            ],

            'zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zoo_zones', 'id')
                    ->where('is_active', true),
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'type' => 'tipo',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'icon' => 'icono',
            'color' => 'color',
            'zone_id' => 'zona',
            'is_active' => 'estado',
        ];
    }
}