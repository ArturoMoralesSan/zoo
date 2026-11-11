<?php

namespace App\Http\Requests\Admin\Card;

use Illuminate\Foundation\Http\FormRequest;

class OptimizeCardImageRequest extends FormRequest
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
            /*
             * Ruta que el panel optimizó: si cambió mientras tanto, no se toca.
             */
            'expected_path' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * WebP codificado en el navegador. Sin él, codifica el servidor.
             */
            'file' => [
                'nullable',
                'file',
                'mimetypes:image/webp',
                'max:15360',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'expected_path' => 'ruta de la imagen',
            'file' => 'imagen optimizada',
        ];
    }
}
