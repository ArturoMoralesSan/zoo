<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Extensión con la que se guarda un archivo subido en el disco público.
 *
 * Nunca se usa la extensión que envía el cliente: una imagen válida llamada
 * x.html o x.php se serviría como página o script desde /storage. Se deduce
 * del contenido y solo se aceptan tipos que el servidor sirve como datos.
 */
final class UploadExtension
{
    public const IMAGES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @param  list<string>  $allowed
     */
    public static function for(
        UploadedFile $file,
        array $allowed = self::IMAGES,
        string $attribute = 'file'
    ): string {
        $extension = strtolower((string) $file->extension());

        if (! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                $attribute => 'El tipo de archivo no está permitido.',
            ]);
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }
}
