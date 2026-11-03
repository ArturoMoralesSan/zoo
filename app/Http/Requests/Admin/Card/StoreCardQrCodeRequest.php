<?php

namespace App\Http\Requests\Admin\Card;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para crear o editar un código QR de una tarjeta.
 */
class StoreCardQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'boolean',
            ],

            'expires' => [
                'required',
                Rule::in(['never', 'date']),
            ],

            'expires_at' => [
                'nullable',
                'required_if:expires,date',
                'date',
                'after:now',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'label' => 'etiqueta',
            'expires_at' => 'fecha de vencimiento',
        ];
    }

    /**
     * Atributos listos para guardar en el modelo.
     */
    public function qrCodeAttributes(): array
    {
        return [
            'label' => $this->validated('label'),
            'is_active' => $this->boolean('is_active'),
            'expires_at' => $this->validated('expires') === 'date'
                ? $this->validated('expires_at')
                : null,
        ];
    }
}
