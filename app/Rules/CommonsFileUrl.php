<?php

namespace App\Rules;

use App\Services\Licensing\CommonsMetadataFetcher;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * URL de una página de archivo de Wikimedia Commons
 * (commons.wikimedia.org/wiki/File:…).
 *
 * Rechaza las páginas de archivo locales de Wikipedia: pueden ser archivos
 * no libres (uso legítimo / fair use).
 */
class CommonsFileUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || CommonsMetadataFetcher::fileTitleFromUrl($value)) {
            return;
        }

        if (CommonsMetadataFetcher::isWikipediaFileUrl($value)) {
            $fail('Es una página de archivo de Wikipedia, no de Commons: esos archivos pueden no ser libres (uso legítimo / fair use). Busca el archivo en commons.wikimedia.org y usa su página.');

            return;
        }

        $fail('Usa la página del archivo en Wikimedia Commons (commons.wikimedia.org/wiki/File:…).');
    }
}
