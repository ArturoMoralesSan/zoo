<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ZooDeckMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * Latitud actual del usuario, para saber si está dentro del zoológico.
             *
             * @example 24.0751
             */
            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
                'required_with:longitude',
            ],

            /**
             * Longitud actual del usuario.
             *
             * @example -104.5836
             */
            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
                'required_with:latitude',
            ],

            /**
             * Precisión del GPS en metros (`coords.accuracy`). Da margen junto al borde del
             * zoológico, hasta el tope configurado.
             *
             * @example 12
             */
            'accuracy' => [
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function coordinate(string $key): ?float
    {
        $value = $this->validated($key);

        return is_numeric($value) ? (float) $value : null;
    }
}
