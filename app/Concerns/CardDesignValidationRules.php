<?php

namespace App\Concerns;

use Closure;
use Illuminate\Validation\Rule;

trait CardDesignValidationRules
{
    /**
     * Tamaños disponibles para el nombre de la especie (clases de Tailwind).
     */
    public const TITLE_FONT_SIZES = ['4xl', '5xl', '6xl', '7xl'];

    /**
     * Catálogo de fuentes compartido con el frontend (resources/data/card-fonts.json).
     *
     * @return array<string, array{family: string, weights: array<int, int>}>
     */
    protected function cardFonts(): array
    {
        return json_decode(
            file_get_contents(resource_path('data/card-fonts.json')),
            true
        );
    }

    /**
     * Reglas del diseño de la plantilla de la tarjeta.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function cardDesignRules(): array
    {
        $fonts = $this->cardFonts();

        return [
            'fade_color' => [
                'required',
                'string',
                'regex:/^#[0-9a-fA-F]{6}$/',
            ],

            'accent_color' => [
                'nullable',
                Rule::in(['white', 'black']),
            ],

            'title_font_family' => [
                'required',
                'string',
                Rule::in(array_keys($fonts)),
            ],

            'title_font_weight' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) use ($fonts) {
                    $family = $fonts[$this->input('title_font_family')] ?? null;

                    if ($family && ! in_array((int) $value, $family['weights'], true)) {
                        $fail('La fuente seleccionada no tiene ese peso.');
                    }
                },
            ],

            'title_font_size' => [
                'required',
                Rule::in(self::TITLE_FONT_SIZES),
            ],
        ];
    }
}
