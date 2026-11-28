<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Error controlado al pedirle algo a la IA.
 *
 * Los mensajes los lee personal no técnico: dicen qué hacer, sin detalles de
 * la API. El detalle queda en el log a través de la excepción previa. Cada
 * función de IA hereda de esta clase y adapta los mensajes que dependen de ella.
 */
class AiGenerationException extends Exception
{
    final public function __construct(
        string $message,
        private int $status,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): static
    {
        return new static('La generación con IA aún no está activada. Avisa al equipo técnico.', 503);
    }

    public static function unavailable(?Throwable $previous = null): static
    {
        return new static('El servicio de IA no respondió. Intenta de nuevo en un momento.', 503, $previous);
    }

    public static function fromResponse(Response $response): static
    {
        $previous = $response->toException();

        return match (true) {
            $response->status() === 429 => new static(
                'Se alcanzó el límite de uso de la IA. Intenta de nuevo más tarde.',
                429,
                $previous
            ),

            // Clave inválida o petición rechazada: el personal no puede corregirlo.
            in_array($response->status(), [400, 401, 403, 404], true) => new static(
                'La IA no está bien configurada. Avisa al equipo técnico.',
                502,
                $previous
            ),

            default => static::unavailable($previous),
        };
    }

    public static function blocked(): static
    {
        return new static('La IA no pudo responder a esta petición.', 422);
    }

    public static function truncated(): static
    {
        return new static('La respuesta de la IA quedó incompleta. Intenta de nuevo.', 502);
    }

    public static function emptyResult(): static
    {
        return new static('La IA no devolvió datos válidos. Intenta de nuevo.', 502);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
