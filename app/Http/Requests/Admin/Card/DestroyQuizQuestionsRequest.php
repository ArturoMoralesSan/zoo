<?php

namespace App\Http\Requests\Admin\Card;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Borrado en lote: o una lista de ids, o todas las preguntas que coinciden
 * con los filtros del listado (all), para no tener que mandar miles de ids.
 */
class DestroyQuizQuestionsRequest extends FormRequest
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
            'all' => [
                'sometimes',
                'boolean',
            ],

            'ids' => [
                'required_unless:all,true,1',
                'array',
                'max:1000',
            ],

            'ids.*' => [
                'integer',
                'distinct',
            ],

            'search' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'nullable',
                'in:0,1,true,false',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'all' => 'todas las preguntas',
            'ids' => 'preguntas seleccionadas',
            'ids.*' => 'pregunta',
            'search' => 'búsqueda',
            'is_active' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required_unless' => 'Selecciona al menos una pregunta.',
        ];
    }
}
