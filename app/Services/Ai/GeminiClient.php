<?php

namespace App\Services\Ai;

use App\Exceptions\AiGenerationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Llamada a Gemini con respuesta JSON validada contra un esquema.
 *
 * Los errores se lanzan con la clase de excepción de cada función de IA, para
 * que el mensaje hable de lo que la persona estaba haciendo.
 */
class GeminiClient
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.key'));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  class-string<AiGenerationException>  $errors
     * @return array<mixed>|null JSON decodificado, o null si no es un JSON válido.
     *
     * @throws AiGenerationException
     */
    public function generateJson(string $prompt, array $schema, string $errors = AiGenerationException::class): ?array
    {
        if (! $this->isConfigured()) {
            throw $errors::notConfigured();
        }

        $config = config('services.gemini');

        try {
            $response = Http::timeout((int) $config['timeout'])
                ->connectTimeout(10)
                ->acceptJson()
                // Solo se reintenta la saturación momentánea: repetir un timeout dejaría a la persona esperando el doble.
                ->retry(2, 500, fn (Throwable $e) => $e instanceof RequestException && $e->response->status() === 503, throw: false)
                ->withHeaders(['x-goog-api-key' => $config['key']])
                ->post(sprintf(self::API_URL, $config['model']), [
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseJsonSchema' => $schema,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw $errors::unavailable($exception);
        }

        if ($response->failed()) {
            throw $errors::fromResponse($response);
        }

        if ($response->json('promptFeedback.blockReason')) {
            throw $errors::blocked();
        }

        $finishReason = $response->json('candidates.0.finishReason');

        if ($finishReason === 'MAX_TOKENS') {
            throw $errors::truncated();
        }

        if ($finishReason !== null && $finishReason !== 'STOP') {
            throw $errors::blocked();
        }

        /** @var array<int, array<string, mixed>> $parts */
        $parts = $response->json('candidates.0.content.parts', []);

        $text = collect($parts)
            ->reject(fn ($part) => $part['thought'] ?? false)
            ->pluck('text')
            ->implode('');

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
