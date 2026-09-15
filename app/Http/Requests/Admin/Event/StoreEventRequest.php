<?php

namespace App\Http\Requests\Admin\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('events.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
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

            'start_at' => [
                'required',
                'date',
            ],

            'end_at' => [
                'nullable',
                'date',
                'after:start_at',
            ],

            'zoo_zone_id' => [
                'nullable',
                'integer',
                Rule::exists('zoo_zones', 'id')
                    ->where('is_active', true),
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'capacity' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'is_featured' => [
                'boolean',
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
            'start_at' => 'fecha de inicio',
            'end_at' => 'fecha de finalización',
            'zoo_zone_id' => 'zona',
            'image' => 'imagen',
            'capacity' => 'capacidad',
            'is_featured' => 'evento destacado',
            'is_active' => 'estado',
        ];
    }
}
