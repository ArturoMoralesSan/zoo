<?php

namespace App\Services\ImageEncoding;

final class TemporaryFile
{
    public static function create(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'webp');

        if ($path === false) {
            throw new ImageEncodingException('No se pudo crear un archivo temporal.');
        }

        return $path;
    }
}
