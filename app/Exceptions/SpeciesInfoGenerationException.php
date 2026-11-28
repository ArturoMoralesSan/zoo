<?php

namespace App\Exceptions;

/**
 * Error controlado al completar los datos de una especie con IA.
 */
class SpeciesInfoGenerationException extends AiGenerationException
{
    public static function blocked(): static
    {
        return new static('La IA no pudo completar los datos de esta especie.', 422);
    }

    public static function emptyResult(): static
    {
        return new static('La IA no devolvió datos válidos para esta especie. Intenta de nuevo.', 502);
    }

    public static function unknownSpecies(): static
    {
        return new static(
            'La IA no reconoce esta especie. Revisa el nombre científico y que coincida con el nombre común.',
            422
        );
    }
}
