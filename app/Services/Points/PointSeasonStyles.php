<?php

namespace App\Services\Points;

/**
 * Catálogo de íconos y colores del aviso de temporada, compartido con el
 * panel (resources/data/point-season-styles.json). Las claves no cambian
 * nunca: la app las traduce a sus propios íconos.
 */
final class PointSeasonStyles
{
    /**
     * @var array{icons: list<array{key: string}>, colors: list<array{key: string, accent: string, on_accent: string, soft: string, text: string}>}|null
     */
    private static ?array $catalog = null;

    /**
     * @return list<string>
     */
    public static function iconKeys(): array
    {
        return array_column(self::catalog()['icons'], 'key');
    }

    /**
     * @return list<string>
     */
    public static function colorKeys(): array
    {
        return array_column(self::catalog()['colors'], 'key');
    }

    /**
     * Clave del ícono si sigue en el catálogo; null si no (o si no hay).
     */
    public static function icon(?string $key): ?string
    {
        return $key !== null && in_array($key, self::iconKeys(), true) ? $key : null;
    }

    /**
     * Color con sus tonos; null si no hay o ya no está en el catálogo.
     *
     * @return array{key: string, accent: string, on_accent: string, soft: string, text: string}|null
     */
    public static function color(?string $key): ?array
    {
        foreach (self::catalog()['colors'] as $color) {
            if ($color['key'] === $key) {
                return [
                    'key' => $color['key'],
                    'accent' => $color['accent'],
                    'on_accent' => $color['on_accent'],
                    'soft' => $color['soft'],
                    'text' => $color['text'],
                ];
            }
        }

        return null;
    }

    /**
     * @return array{icons: list<array{key: string}>, colors: list<array{key: string, accent: string, on_accent: string, soft: string, text: string}>}
     */
    private static function catalog(): array
    {
        return self::$catalog ??= json_decode(
            (string) file_get_contents(resource_path('data/point-season-styles.json')),
            true
        );
    }
}
