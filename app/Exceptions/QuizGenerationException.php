<?php

namespace App\Exceptions;

/**
 * Error controlado al generar preguntas con IA.
 */
class QuizGenerationException extends AiGenerationException
{
    public static function blocked(): static
    {
        return new static('La IA no pudo crear preguntas para esta tarjeta. Prueba con otro tema.', 422);
    }

    public static function truncated(): static
    {
        return new static('La respuesta de la IA quedó incompleta. Prueba con menos preguntas.', 502);
    }

    public static function emptyResult(): static
    {
        return new static('La IA no devolvió preguntas válidas. Intenta de nuevo.', 502);
    }
}
