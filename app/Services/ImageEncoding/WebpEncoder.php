<?php

namespace App\Services\ImageEncoding;

/**
 * Convierte imágenes a WebP en el servidor, si el hosting lo permite.
 */
interface WebpEncoder
{
    /**
     * Nombre del codificador (imagick, gd) o null si no hay ninguno.
     */
    public function name(): ?string;

    public function isAvailable(): bool;

    /**
     * Codificar $source dentro de los límites del perfil.
     *
     * @return string Ruta de un archivo temporal; quien llama lo elimina.
     *
     * @throws ImageEncodingException
     */
    public function encode(string $source, ImageProfile $profile): string;
}
