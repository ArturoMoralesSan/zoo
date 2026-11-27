<?php

namespace App\Concerns;

use App\Rules\GlbFile;

/**
 * Modelo 3D e imagen de tarjeta del formulario de especies. Sin estas reglas
 * los archivos llegaban al controlador sin validar.
 */
trait SpeciesModelValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function modelAndCardImageRules(): array
    {
        return [
            'card_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'model_file' => ['nullable', 'file', 'max:'.(int) config('glb.max_file_size'), new GlbFile],
            'model_url' => ['nullable', 'url:https', 'max:2048'],
            'model_name' => ['nullable', 'string', 'max:255'],
            'model_format' => ['nullable', 'string', 'max:20'],
            'model_description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
