<?php

namespace App\Http\Requests\Admin\CardTransfer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardImportRequest extends FormRequest
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
            'package' => ['required', 'file', 'extensions:zip'],
        ];
    }

    public function attributes(): array
    {
        return [
            'package' => 'paquete',
        ];
    }

    public function messages(): array
    {
        return [
            'package.uploaded' => 'El paquete no se pudo subir. Puede que pese más de lo que permite el servidor.',
            'package.extensions' => 'El paquete debe ser un archivo .zip exportado desde ZooDeck.',
        ];
    }
}
