<?php

namespace App\Rules;

use App\Models\License;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Solo se pueden elegir licencias seleccionables, salvo que
 * licensing.allow_restricted esté activo. Explica por qué una licencia
 * NC/ND no se puede usar en el juego.
 */
class SelectableLicense implements ValidationRule
{
    /**
     * @param  array<int, int>  $alwaysAllowed  Licencias ya guardadas en el crédito
     *                                          (no se obliga a cambiarlas al editar otra cosa).
     */
    public function __construct(
        private array $alwaysAllowed = []
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $license = License::find($value);

        if (
            ! $license ||
            $license->is_selectable ||
            config('licensing.allow_restricted') ||
            in_array($license->id, $this->alwaysAllowed, true)
        ) {
            return;
        }

        $fail(match (true) {
            ! $license->allows_commercial && ! $license->allows_modification => "La licencia {$license->short_name} no se puede usar: prohíbe el uso comercial (el juego es comercial) y las obras derivadas (la tarjeta modifica la imagen).",
            ! $license->allows_commercial => "La licencia {$license->short_name} no se puede usar: prohíbe el uso comercial y el juego es comercial.",
            ! $license->allows_modification => "La licencia {$license->short_name} no se puede usar: prohíbe modificar la obra y la tarjeta recorta y adapta la imagen.",
            default => "La licencia {$license->short_name} no está disponible. Si el archivo tiene doble licencia, elige su opción Creative Commons.",
        });
    }
}
