<?php

namespace App\Enums;

use App\Models\License;

/**
 * Origen de un recurso (imagen o modelo 3D) de una tarjeta.
 *
 * Cada origen solo admite las licencias que realmente usa esa plataforma y
 * solo aplica a los recursos que ofrece (p. ej. Meshy solo genera modelos).
 */
enum AssetSource: string
{
    case Own = 'own';
    case Pexels = 'pexels';
    case Unsplash = 'unsplash';
    case Pixabay = 'pixabay';
    case WikimediaCommons = 'wikimedia_commons';
    case Meshy = 'meshy';
    case Sketchfab = 'sketchfab';
    case PolyPizza = 'poly_pizza';
    case PolyHaven = 'poly_haven';
    case Other = 'other';

    /**
     * Etiqueta legible del origen.
     */
    public function label(): string
    {
        return match ($this) {
            self::Own => 'Propio',
            self::Pexels => 'Pexels',
            self::Unsplash => 'Unsplash',
            self::Pixabay => 'Pixabay',
            self::WikimediaCommons => 'Wikimedia Commons',
            self::Meshy => 'Meshy',
            self::Sketchfab => 'Sketchfab',
            self::PolyPizza => 'Poly Pizza',
            self::PolyHaven => 'Poly Haven',
            self::Other => 'Otro',
        };
    }

    /**
     * Nombre que aparece en el crédito ("vía …"). Los orígenes propios u
     * "otro" no aportan nada a la atribución.
     */
    public function creditName(): ?string
    {
        return match ($this) {
            self::Own, self::Other => null,
            default => $this->label(),
        };
    }

    /**
     * Recursos que puede tener este origen.
     *
     * @return array<int, CreditAsset>
     */
    public function assets(): array
    {
        $images = [CreditAsset::Artwork, CreditAsset::Thumbnail];

        return match ($this) {
            self::Own, self::Other => [...$images, CreditAsset::Model],
            // Generadores y sitios de descarga de modelos 3D.
            self::Meshy, self::Sketchfab, self::PolyPizza, self::PolyHaven => [CreditAsset::Model],
            default => $images,
        };
    }

    /**
     * La licencia corresponde a este origen.
     *
     * - Propio: obra propia o por encargo.
     * - Pexels, Unsplash, Pixabay, Meshy: solo sus propias licencias.
     * - Wikimedia Commons: solo licencias libres (Commons no acepta NC ni ND).
     * - Sketchfab: las Creative Commons que elige cada autor (no la licencia
     *   Standard: prohíbe redistribuir el modelo y el juego descarga el GLB).
     * - Poly Pizza: CC0 o CC BY, según el modelo. Poly Haven: siempre CC0.
     * - Otro: cualquier licencia abierta (CC, dominio público, GFDL).
     */
    public function allowsLicense(License $license): bool
    {
        return match ($this) {
            self::Own => in_array($license->slug, ['proprietary', 'proprietary-commissioned'], true),
            self::Pexels => $license->slug === 'pexels-license',
            self::Unsplash => $license->slug === 'unsplash-license',
            self::Pixabay => $license->slug === 'pixabay-license',
            self::Meshy => in_array($license->slug, ['meshy-free', 'meshy-paid'], true),
            self::Sketchfab => str_starts_with($license->slug, 'cc'),
            self::PolyPizza => $license->slug === 'cc0-1.0' || preg_match('/^cc-by-\d/', $license->slug) === 1,
            self::PolyHaven => $license->slug === 'cc0-1.0',
            self::WikimediaCommons => $license->isOpen()
                && $license->allows_commercial
                && $license->allows_modification,
            self::Other => $license->isOpen(),
        };
    }

    /**
     * Opciones para los formularios: etiqueta, recursos y licencias permitidas.
     *
     * @param  iterable<License>  $licenses
     * @return array<int, array{value: string, label: string, assets: array<int, string>, licenses: array<int, string>}>
     */
    public static function formOptions(iterable $licenses): array
    {
        $licenses = collect($licenses);

        return array_map(fn (self $source) => [
            'value' => $source->value,
            'label' => $source->label(),
            'assets' => array_map(fn (CreditAsset $asset) => $asset->value, $source->assets()),
            'licenses' => $licenses
                ->filter(fn (License $license) => $source->allowsLicense($license))
                ->pluck('slug')
                ->values()
                ->all(),
        ], self::cases());
    }
}
