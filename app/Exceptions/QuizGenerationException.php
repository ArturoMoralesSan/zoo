<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Error controlado al generar preguntas con IA.
 *
 * Los mensajes los lee personal no técnico: dicen qué hacer, sin detalles de
 * la API. El detalle queda en el log a través de la excepción previa.
 */
class QuizGenerationException extends Exception
{
    public function __construct(
        string $message,
        private int $status,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('La generación con IA aún no está activada. Avisa al equipo técnico.', 503);
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self('El servicio de IA no respondió. Intenta de nuevo en un momento.', 503, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $previous = $response->toException();

        return match (true) {
            $response->status() === 429 => new self(
                'Se alcanzó el límite de uso de la IA. Intenta de nuevo más tarde.',
                429,
                $previous
            ),

            // Clave inválida o petición rechazada: el personal no puede corregirlo.
            in_array($response->status(), [400, 401, 403, 404], true) => new self(
                'La IA no está bien configurada. Avisa al equipo técnico.',
                502,
                $previous
            ),

            default => self::unavailable($previous),
        };
    }

    public static function blocked(): self
    {
        return new self('La IA no pudo crear preguntas para esta tarjeta. Prueba con otro tema.', 422);
    }

    public static function truncated(): self
    {
        return new self('La respuesta de la IA quedó incompleta. Prueba con menos preguntas.', 502);
    }

    public static function emptyResult(): self
    {
        return new self('La IA no devolvió preguntas válidas. Intenta de nuevo.', 502);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
