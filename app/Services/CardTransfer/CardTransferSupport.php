<?php

namespace App\Services\CardTransfer;

use ZipArchive;

/**
 * Requisitos del servidor para exportar e importar paquetes.
 */
class CardTransferSupport
{
    public const UNAVAILABLE_MESSAGE = 'La extensión PHP zip no está instalada en el servidor. '
        .'Pide a quien administra el servidor que la active para exportar o importar tarjetas.';

    public function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Tamaño máximo de un .zip subido desde el navegador, en bytes
     * (el menor entre upload_max_filesize y post_max_size).
     */
    public function maxUploadBytes(): int
    {
        $limits = array_filter([
            $this->iniBytes((string) ini_get('upload_max_filesize')),
            $this->iniBytes((string) ini_get('post_max_size')),
        ]);

        return $limits ? min($limits) : 0;
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
