<?php

namespace App\Concerns;

use App\Models\Card;
use App\Rules\GlbFile;
use Closure;

/**
 * Reglas del modelo 3D de la tarjeta.
 *
 * El navegador escala el modelo y envía el original, el archivo escalado y
 * sus medidas. Todos los campos son opcionales (una tarjeta puede no tener
 * modelo), pero deben llegar juntos.
 */
trait CardModelValidationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function cardModelRules(?Card $card = null): array
    {
        $maxSize = (int) config('glb.max_file_size');

        $creating = $card === null;

        return [
            'model_original_file' => [
                'nullable',
                'file',
                'max:'.$maxSize,
                new GlbFile,
                ...($creating ? ['required_with:model_file'] : []),
            ],

            /*
             * Archivo escalado que descargará el juego.
             */
            'model_file' => [
                'nullable',
                'file',
                'max:'.$maxSize,
                new GlbFile,
                'required_with:model_original_file,model_target_height',
                function (string $attribute, mixed $value, Closure $fail) use ($card) {
                    if (
                        $card &&
                        ! $card->model_file &&
                        ! $this->hasFile('model_original_file')
                    ) {
                        $fail('Sube el archivo original del modelo 3D.');
                    }
                },
            ],

            'model_target_height' => [
                'nullable',
                'required_with:model_file,model_original_file',
                'numeric',
                'min:'.config('glb.min_height'),
                'max:'.config('glb.max_height'),
            ],

            'model_original_height' => [
                'nullable',
                'required_with:model_file',
                'numeric',
                'gt:0',
            ],

            'model_final_height' => [
                'nullable',
                'required_with:model_file',
                'numeric',
                'gt:0',
            ],

            'model_final_width' => [
                'nullable',
                'required_with:model_file',
                'numeric',
                'min:0',
            ],

            'model_final_depth' => [
                'nullable',
                'required_with:model_file',
                'numeric',
                'min:0',
            ],
        ];
    }

    /**
     * Nombres de los campos en los mensajes de error.
     *
     * @return array<string, string>
     */
    protected function cardModelAttributes(): array
    {
        return [
            'model_original_file' => 'archivo original del modelo',
            'model_file' => 'modelo 3D',
            'model_target_height' => 'alto del modelo',
            'model_original_height' => 'alto original del modelo',
            'model_final_height' => 'alto final del modelo',
            'model_final_width' => 'ancho final del modelo',
            'model_final_depth' => 'largo final del modelo',
        ];
    }
}
