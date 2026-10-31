<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Archivo GLB: extensión .glb y encabezado binario de glTF 2.0.
 */
class GlbFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (
            ! $value instanceof UploadedFile ||
            strtolower($value->getClientOriginalExtension()) !== 'glb'
        ) {
            $fail('El modelo 3D debe ser un archivo GLB.');

            return;
        }

        $handle = @fopen($value->getRealPath(), 'rb');
        $header = $handle ? fread($handle, 8) : false;

        if ($handle) {
            fclose($handle);
        }

        if (
            $header === false ||
            strlen($header) < 8 ||
            substr($header, 0, 4) !== 'glTF' ||
            unpack('V', substr($header, 4, 4))[1] !== 2
        ) {
            $fail('El archivo no es un GLB válido (glTF 2.0).');
        }
    }
}
