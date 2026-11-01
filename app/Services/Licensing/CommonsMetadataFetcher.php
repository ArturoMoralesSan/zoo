<?php

namespace App\Services\Licensing;

use App\Models\License;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Datos de un archivo de Wikimedia Commons para autocompletar su crédito.
 *
 * Solo llena el formulario: el administrador revisa los datos antes de
 * guardar. Los errores de red o de la API se devuelven como advertencia.
 */
class CommonsMetadataFetcher
{
    private const COMMONS_HOSTS = ['commons.wikimedia.org', 'commons.m.wikimedia.org'];

    /**
     * Prefijos de las páginas de archivo en las Wikipedias locales.
     */
    private const WIKIPEDIA_FILE_PREFIXES = 'File|Archivo|Image|Imagen|Fichier|Datei|Ficheiro|Bestand';

    /**
     * @return array{
     *     title: ?string,
     *     author_name: ?string,
     *     author_url: ?string,
     *     license_id: ?int,
     *     license_slug: ?string,
     *     source_url: ?string,
     *     warnings: array<int, string>,
     * }
     */
    public function fetch(string $url): array
    {
        $fileTitle = self::fileTitleFromUrl($url);

        if (! $fileTitle) {
            return self::result(warnings: [
                'La URL no es una página de archivo de Wikimedia Commons (commons.wikimedia.org/wiki/File:…).',
            ]);
        }

        $cacheKey = 'licensing.commons.'.sha1($fileTitle);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('licensing.commons.timeout'))
                ->connectTimeout((int) config('licensing.commons.timeout'))
                ->withUserAgent($this->userAgent())
                ->acceptJson()
                ->get(config('licensing.commons.api_url'), [
                    'action' => 'query',
                    'prop' => 'imageinfo',
                    'iiprop' => 'extmetadata|url',
                    'titles' => $fileTitle,
                    'format' => 'json',
                ])
                ->throw();

            $page = collect($response->json('query.pages', []))->first();
        } catch (ConnectionException) {
            return self::result(warnings: [
                'Wikimedia Commons no respondió a tiempo. Intenta de nuevo o llena los datos a mano.',
            ]);
        } catch (Throwable) {
            return self::result(warnings: [
                'No se pudieron obtener los datos de Wikimedia Commons. Llena los datos a mano.',
            ]);
        }

        $info = $page['imageinfo'][0] ?? null;

        if (! is_array($page) || isset($page['missing']) || ! $info) {
            return self::result(warnings: [
                'El archivo no existe en Wikimedia Commons. Revisa la URL.',
            ]);
        }

        $result = $this->parse($fileTitle, $info);

        Cache::put($cacheKey, $result, (int) config('licensing.commons.cache_ttl'));

        return $result;
    }

    /**
     * "File:Nombre del archivo.jpg" a partir de la URL de su página en Commons,
     * o null si no es una página de archivo de Commons.
     */
    public static function fileTitleFromUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($host, self::COMMONS_HOSTS, true)) {
            return null;
        }

        $path = rawurldecode($parts['path'] ?? '');

        if (str_starts_with($path, '/wiki/')) {
            $title = substr($path, strlen('/wiki/'));
        } else {
            parse_str($parts['query'] ?? '', $query);
            $title = is_string($query['title'] ?? null) ? $query['title'] : '';
        }

        $title = trim(str_replace('_', ' ', $title));

        if (! preg_match('/^File:(.+)$/i', $title, $matches) || trim($matches[1]) === '') {
            return null;
        }

        return 'File:'.trim($matches[1]);
    }

    /**
     * Página de archivo de una Wikipedia local (puede ser un archivo no libre).
     */
    public static function isWikipediaFileUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        $host = strtolower($parts['host'] ?? '');

        if (! preg_match('/(^|\.)wikipedia\.org$/', $host)) {
            return false;
        }

        $path = rawurldecode($parts['path'] ?? '');

        return (bool) preg_match('#^/wiki/('.self::WIKIPEDIA_FILE_PREFIXES.'):#i', $path);
    }

    /**
     * @param  array<string, mixed>  $info
     * @return array<string, mixed>
     */
    private function parse(string $fileTitle, array $info): array
    {
        $meta = fn (string $key): string => self::plainText((string) ($info['extmetadata'][$key]['value'] ?? ''));

        $artistHtml = (string) ($info['extmetadata']['Artist']['value'] ?? '');

        $fileName = Str::after($fileTitle, 'File:');

        $warnings = [];

        $shortName = $meta('LicenseShortName');
        $license = $this->matchLicense($shortName, $meta('LicenseUrl'));

        if (! $license) {
            $warnings[] = $shortName !== ''
                ? "No se reconoció la licencia «{$shortName}». Elígela a mano y revisa que permita el uso comercial."
                : 'El archivo no indica su licencia. Revísala en la página de Commons y elígela a mano.';
        } elseif (! $license->is_selectable && ! config('licensing.allow_restricted')) {
            $warnings[] = "La licencia del archivo ({$license->short_name}) no se puede usar en el juego. {$license->summary}";
            $license = null;
        }

        return self::result(
            title: $meta('ObjectName') ?: pathinfo($fileName, PATHINFO_FILENAME),
            authorName: self::plainText($artistHtml) ?: null,
            authorUrl: self::singleLink($artistHtml),
            license: $license,
            sourceUrl: $info['descriptionurl']
                ?? 'https://commons.wikimedia.org/wiki/'.str_replace(' ', '_', $fileTitle),
            warnings: $warnings,
        );
    }

    /**
     * Licencia del catálogo según el nombre corto de Commons ("CC BY-SA 4.0",
     * "CC0", "Public domain"…) o, si no, según su URL.
     */
    private function matchLicense(string $shortName, string $url): ?License
    {
        $name = strtolower(trim($shortName));

        $slug = match (true) {
            $name === '' => null,
            str_starts_with($name, 'cc0') || str_starts_with($name, 'cc-zero') => 'cc0-1.0',
            str_starts_with($name, 'public domain') || $name === 'pd' || str_starts_with($name, 'pd-') => 'public-domain',
            str_starts_with($name, 'gfdl') => 'gfdl',
            (bool) preg_match('/^cc[ -](by(?:-nc)?(?:-nd|-sa)?)[ -](\d\.\d)/', $name, $matches) => "cc-{$matches[1]}-{$matches[2]}",
            default => null,
        };

        $license = $slug ? License::where('slug', $slug)->first() : null;

        if ($license || $url === '') {
            return $license;
        }

        $normalize = fn (?string $value): string => rtrim(
            preg_replace(['#^https?://#i', '#/deed\.[a-z_-]+$#i', '#/legalcode(\.[a-z_-]+)?$#i'], '', strtolower(trim((string) $value))),
            '/'
        );

        $target = $normalize($url);

        return License::query()
            ->whereNotNull('url')
            ->ordered()
            ->get()
            ->first(fn (License $license) => $normalize($license->url) === $target);
    }

    /**
     * URL del autor si el campo Artist contiene un solo enlace.
     */
    private static function singleLink(string $html): ?string
    {
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $matches);

        if (count($matches[1]) !== 1) {
            return null;
        }

        $href = html_entity_decode($matches[1][0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return match (true) {
            str_starts_with($href, '//') => 'https:'.$href,
            str_starts_with($href, '/') => 'https://commons.wikimedia.org'.$href,
            (bool) preg_match('#^https?://#i', $href) => $href,
            default => null,
        };
    }

    /**
     * Texto sin HTML, con entidades decodificadas y espacios normalizados.
     */
    private static function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function userAgent(): string
    {
        $contact = config('licensing.commons.contact');

        return sprintf(
            '%s/1.0 (%s%s) Laravel-HTTP',
            Str::slug((string) config('app.name'), ''),
            config('app.url'),
            $contact ? '; '.$contact : ''
        );
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private static function result(
        ?string $title = null,
        ?string $authorName = null,
        ?string $authorUrl = null,
        ?License $license = null,
        ?string $sourceUrl = null,
        array $warnings = [],
    ): array {
        return [
            'title' => $title,
            'author_name' => $authorName,
            'author_url' => $authorUrl,
            'license_id' => $license?->id,
            'license_slug' => $license?->slug,
            'source_url' => $sourceUrl,
            'warnings' => $warnings,
        ];
    }
}
