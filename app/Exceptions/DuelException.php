<?php

namespace App\Exceptions;

use App\Http\Resources\CardResource;
use App\Http\Resources\Duel\DuelAttemptsResource;
use App\Services\Duel\DuelAttempts;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Error controlado del flujo de duelos.
 *
 * Laravel llama a render() automáticamente y responde con
 * el mismo formato { success, message, data } del resto de la API.
 */
class DuelException extends Exception
{
    public function __construct(
        string $message,
        private int $status,
        private array $data = []
    ) {
        parent::__construct($message);
    }

    public static function invalidQr(): self
    {
        return new self('Código QR no válido.', 404);
    }

    public static function expiredQr(): self
    {
        return new self('Este código QR ya venció.', 410);
    }

    public static function cardNotAvailable(): self
    {
        return new self('Esta tarjeta no está disponible.', 404);
    }

    public static function alreadyOwned(CardResource $card): self
    {
        return new self('Ya tienes esta tarjeta.', 409, [
            'card' => $card,
        ]);
    }

    public static function locked(DuelAttempts $attempts): self
    {
        return new self(
            'Agotaste tus intentos. Podrás volver a intentarlo más tarde.',
            423,
            ['attempts' => new DuelAttemptsResource($attempts)]
        );
    }

    public static function noQuestions(): self
    {
        return new self('Esta tarjeta aún no tiene preguntas disponibles.', 422);
    }

    public static function attemptClosed(): self
    {
        return new self('Este duelo ya fue respondido.', 409);
    }

    public static function attemptExpired(DuelAttempts $attempts): self
    {
        return new self('El tiempo del duelo terminó. Se contó como un intento fallido.', 410, [
            'attempts' => new DuelAttemptsResource($attempts),
        ]);
    }

    public static function invalidAnswers(): self
    {
        return new self(
            'Las respuestas no corresponden a las preguntas del duelo.',
            422
        );
    }

    public function render(): JsonResponse
    {
        $body = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        if ($this->data) {
            $body['data'] = $this->data;
        }

        return response()->json($body, $this->status);
    }
}
