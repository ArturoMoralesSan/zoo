<?php

namespace App\Enums;

use App\Models\License;

/**
 * Origen de un recurso (imagen o modelo 3D) de una tarjeta.
 *
 * Cada origen solo admite las licencias que realmente usa esa plataforma y
 * solo aplica a los recursos que ofrece (p. ej. Meshy solo genera modelos).
 * Como "Basado en" o "Incluye" se acepta cualquier origen: un render usa
 * modelos y HDRI, un modelo de Meshy parte de una foto…
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
    case Ai = 'ai';
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
            self::Ai => 'Generada con IA',
            self::Other => 'Otro',
        };
    }

    /**
     * Nombre que aparece en el crédito ("vía …"). Los orígenes propios u
     * "otro" no aportan nada; en las imágenes de IA lo dice la licencia.
     */
    public function creditName(): ?string
    {
        return match ($this) {
            self::Own, self::Other, self::Ai => null,
            default => $this->label(),
        };
    }

    /**
     * Recursos que puede tener este origen como crédito de un papel.
     *
     * @return array<int, CreditAsset>
     */
    public function assets(CreditRole $role = CreditRole::Origin): array
    {
        if ($role !== CreditRole::Origin) {
            return CreditAsset::cases();
        }

        $images = [CreditAsset::Artwork, CreditAsset::Thumbnail];

        return match ($this) {
            self::Own, self::Other => [...$images, CreditAsset::Model],
            // Generadores y sitios de descarga de modelos 3D.
            self::Meshy, self::Sketchfab, self::PolyPizza, self::PolyHaven => [CreditAsset::Model],
            default => $images,
        };
    }

    public function allowsAsset(CreditAsset $asset, CreditRole $role = CreditRole::Origin): bool
    {
        return in_array($asset, $this->assets($role), true);
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
     * - Generada con IA: los términos de la herramienta (licencias "ai-").
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
            self::Ai => str_starts_with($license->slug, 'ai-'),
            self::WikimediaCommons => $license->isOpen()
                && $license->allows_commercial
                && $license->allows_modification,
            self::Other => $license->isOpen(),
        };
    }

    /**
     * Opciones para los formularios: etiqueta, recursos por papel y
     * licencias permitidas.
     *
     * @param  iterable<License>  $licenses
     * @return array<int, array{value: string, label: string, assets: array<string, array<int, string>>, licenses: array<int, string>}>
     */
    public static function formOptions(iterable $licenses): array
    {
        $licenses = collect($licenses);

        return array_map(fn (self $source) => [
            'value' => $source->value,
            'label' => $source->label(),
            'assets' => collect(CreditRole::cases())
                ->mapWithKeys(fn (CreditRole $role) => [
                    $role->value => array_map(fn (CreditAsset $asset) => $asset->value, $source->assets($role)),
                ])
                ->all(),
            'licenses' => $licenses
                ->filter(fn (License $license) => $source->allowsLicense($license))
                ->pluck('slug')
                ->values()
                ->all(),
        ], self::cases());
    }
}
