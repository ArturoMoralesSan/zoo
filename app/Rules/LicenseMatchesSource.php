<?php

namespace App\Rules;

use App\Enums\AssetSource;
use App\Models\License;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La licencia debe ser una de las que usa el origen elegido
 * (ver AssetSource::allowsLicense).
 */
class LicenseMatchesSource implements ValidationRule
{
    public function __construct(
        private ?AssetSource $source
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $license = License::find($value);

        if (! $license || ! $this->source || $this->source->allowsLicense($license)) {
            return;
        }

        $fail(match ($this->source) {
            AssetSource::Own => 'El contenido propio usa la licencia "Obra propia" u "Obra por encargo".',
            AssetSource::Pexels => 'Las imágenes de Pexels usan la Licencia de Pexels.',
            AssetSource::Unsplash => 'Las imágenes de Unsplash usan la Licencia de Unsplash.',
            AssetSource::Pixabay => 'Las imágenes de Pixabay usan la Licencia de contenido de Pixabay.',
            AssetSource::Meshy => 'Los modelos de Meshy usan la licencia de Meshy: plan gratuito (CC BY 4.0) o de pago.',
            AssetSource::Sketchfab => 'Los modelos de Sketchfab usan la licencia Creative Commons que eligió su autor.',
            AssetSource::PolyPizza => 'Los modelos de Poly Pizza son CC0 o CC BY: revisa la licencia en la página del modelo.',
            AssetSource::PolyHaven => 'Los modelos de Poly Haven son CC0.',
            AssetSource::Ai => 'Las imágenes generadas con IA usan los términos de la herramienta (Adobe Firefly, ChatGPT, Gemini, Midjourney o Stable Diffusion).',
            AssetSource::WikimediaCommons => 'Wikimedia Commons solo aloja archivos con licencias libres: CC0, dominio público, CC BY, CC BY-SA o GFDL.',
            AssetSource::Other => 'Con el origen "Otro" elige una licencia Creative Commons o de dominio público. Las licencias de Pexels, Unsplash, Pixabay y Meshy solo se usan con su origen.',
        });
    }
}
