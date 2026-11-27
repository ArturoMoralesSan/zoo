<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;

/**
 * Proporción obligatoria de algunas imágenes (miniaturas 1:1, ilustración de
 * la tarjeta 572:1016). El navegador recorta antes de subir; esta regla
 * rechaza lo que llegue sin recortar. La proporción vive en
 * resources/data/{card,species}-image-profiles.json (clave "aspect").
 */
trait ImageAspectRules
{
    /**
     * Regla de proporción del perfil. Sin "aspect" admite cualquiera.
     *
     * @param  'cards'|'species'  $group
     */
    protected function aspectRule(string $group, string $kind): Dimensions
    {
        $aspect = $this->profileAspect($group, $kind);

        return $aspect
            ? Rule::dimensions()->ratio($aspect[0] / $aspect[1])
            : Rule::dimensions();
    }

    /**
     * Mensaje de la regla de proporción, que dice cuál se espera.
     *
     * @param  'cards'|'species'  $group
     * @return array<string, string>
     */
    protected function aspectMessages(string $group, string $field, string $kind): array
    {
        $aspect = $this->profileAspect($group, $kind);

        if (! $aspect) {
            return [];
        }

        return [
            $field.'.dimensions' => sprintf(
                'La :attribute debe tener proporción %d:%d. Usa "Recortar" antes de guardar.',
                $aspect[0],
                $aspect[1],
            ),
        ];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function profileAspect(string $group, string $kind): ?array
    {
        $aspect = config("images.$group.$kind.aspect");

        if (! is_array($aspect) || count($aspect) !== 2) {
            return null;
        }

        return [(int) $aspect[0], (int) $aspect[1]];
    }
}
