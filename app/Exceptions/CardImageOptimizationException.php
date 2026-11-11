<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Error controlado al optimizar una imagen guardada de la tarjeta.
 * Laravel llama a render() y responde { success: false, message }.
 */
class CardImageOptimizationException extends Exception
{
    public function __construct(string $message, private int $status)
    {
        parent::__construct($message);
    }

    public static function changed(): self
    {
        return new self('La imagen cambió mientras se optimizaba. Recarga la lista.', 409);
    }

    public static function missing(): self
    {
        return new self('El archivo original ya no existe.', 404);
    }

    public static function invalid(string $reason): self
    {
        return new self($reason, 422);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
