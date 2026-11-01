<?php

namespace App\Services\Licensing;

/**
 * Línea de crédito (TASL: título, autor, origen, licencia) lista para mostrar.
 *
 * El mismo algoritmo está en resources/js/lib/credits/attribution.ts (vista
 * previa del panel); ambos se prueban con tests/Fixtures/credits/attribution-cases.json.
 *
 *   “Título” de Autor, vía Origen, LICENCIA, con cambios: nota
 *
 * - attribution_override siempre gana; después, la plantilla de la licencia.
 * - Las partes sin datos se omiten (sin "Desconocido" ni separadores dobles).
 * - Licencias propias o privadas: “Título” de Autor – obra propia.
 * - Un modelo generado a partir de la ilustración de la tarjeta lo dice en
 *   la misma línea ("a partir de la ilustración de la tarjeta"); el crédito
 *   de la ilustración es su propia fila.
 */
class AttributionBuilder
{
    /**
     * Texto final de las licencias propias o privadas, por slug.
     */
    public const PROPRIETARY_LABELS = [
        'proprietary' => 'obra propia',
        'proprietary-commissioned' => 'obra por encargo',
    ];

    public const PRIVATE_LICENSE_LABEL = 'licencia privada';

    public const BASIS = 'a partir de la ilustración de la tarjeta';

    /**
     * Marcador de las plantillas donde va BASIS (con un espacio antes).
     */
    public const BASIS_PLACEHOLDER = '{basis}';

    /**
     * @param  array{
     *     title?: ?string,
     *     author_name?: ?string,
     *     source?: ?string,
     *     modified?: ?bool,
     *     modification_note?: ?string,
     *     attribution_override?: ?string,
     *     based_on_artwork?: ?bool,
     *     license?: ?array{slug?: ?string, short_name?: ?string, is_proprietary?: ?bool, attribution_template?: ?string},
     * }  $credit  `source` es el nombre del origen para el crédito (null si no aporta).
     */
    public static function build(array $credit): string
    {
        $clean = fn (?string $value): string => trim((string) $value);

        $override = $clean($credit['attribution_override'] ?? null);

        if ($override !== '') {
            return $override;
        }

        $license = $credit['license'] ?? [];
        $basedOnArtwork = (bool) ($credit['based_on_artwork'] ?? false);

        $template = $clean($license['attribution_template'] ?? null);

        if ($template !== '') {
            return self::fillTemplate($template, $basedOnArtwork);
        }

        $title = $clean($credit['title'] ?? null);
        $author = $clean($credit['author_name'] ?? null);
        $source = $clean($credit['source'] ?? null);

        $work = match (true) {
            $title !== '' && $author !== '' => "“{$title}” de {$author}",
            $title !== '' => "“{$title}”",
            default => $author,
        };

        $parts = [
            $work,
            $source !== '' ? "vía {$source}" : '',
            $basedOnArtwork ? self::BASIS : '',
        ];

        if ($license['is_proprietary'] ?? false) {
            $label = self::PROPRIETARY_LABELS[$license['slug'] ?? '']
                ?? self::PRIVATE_LICENSE_LABEL;

            $line = self::join($parts);

            return $line === '' ? self::capitalize($label) : self::capitalize($line)." – {$label}";
        }

        $note = $clean($credit['modification_note'] ?? null);

        $parts[] = $clean($license['short_name'] ?? null);

        if ($credit['modified'] ?? false) {
            $parts[] = $note !== '' ? "con cambios: {$note}" : 'con cambios';
        }

        return self::capitalize(self::join($parts));
    }

    /**
     * Plantilla de la licencia con el origen del modelo, si aplica.
     */
    private static function fillTemplate(string $template, bool $basedOnArtwork): string
    {
        if (str_contains($template, self::BASIS_PLACEHOLDER)) {
            return str_replace(
                self::BASIS_PLACEHOLDER,
                $basedOnArtwork ? ' '.self::BASIS : '',
                $template
            );
        }

        return $basedOnArtwork ? "{$template} (".self::BASIS.')' : $template;
    }

    /**
     * Une las partes no vacías con comas.
     *
     * @param  array<int, string>  $parts
     */
    private static function join(array $parts): string
    {
        return implode(', ', array_filter($parts, fn (string $part) => $part !== ''));
    }

    /**
     * Mayúscula inicial en las líneas que empiezan con texto fijo ("obra
     * propia", "a partir de…"); los nombres de autor no se tocan.
     */
    private static function capitalize(string $line): string
    {
        $fixed = [self::BASIS, ...array_values(self::PROPRIETARY_LABELS), self::PRIVATE_LICENSE_LABEL];

        foreach ($fixed as $text) {
            if (str_starts_with($line, $text)) {
                return mb_strtoupper(mb_substr($line, 0, 1)).mb_substr($line, 1);
            }
        }

        return $line;
    }
}
