<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Error controlado al optimizar una imagen ya guardada.
 * Laravel llama a render() y responde { success: false, message }.
 */
class ImageOptimizationException extends Exception
{
    final public function __construct(string $message, private int $status)
    {
        parent::__construct($message);
    }

    public static function changed(): static
    {
        return new static('La imagen cambió mientras se optimizaba. Recarga la lista.', 409);
    }

    public static function missing(): static
    {
        return new static('El archivo original ya no existe.', 404);
    }

    public static function invalid(string $reason): static
    {
        return new static($reason, 422);
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
