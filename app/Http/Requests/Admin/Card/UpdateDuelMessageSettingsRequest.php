<?php

namespace App\Http\Requests\Admin\Card;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDuelMessageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $message = [
            'nullable',
            'string',
            'max:255',
        ];

        return [
            'win_message' => $message,

            'retry_message' => $message,

            'locked_message' => $message,
        ];
    }

    public function attributes(): array
    {
        return [
            'win_message' => 'mensaje de victoria',
            'retry_message' => 'mensaje de reintento',
            'locked_message' => 'mensaje sin intentos',
        ];
    }
}
