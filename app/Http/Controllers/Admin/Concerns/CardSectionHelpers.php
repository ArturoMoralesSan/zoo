<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Card;
use Inertia\Inertia;

/**
 * Ayudas de las secciones de una tarjeta (códigos QR, créditos).
 */
trait CardSectionHelpers
{
    /**
     * Datos de la tarjeta para el encabezado de la sección.
     *
     * @return array{id: int, name: string, species: array{common_name: string}|null}
     */
    private function cardSummary(Card $card): array
    {
        return [
            ...$card->only('id', 'name'),
            'species' => $card->species?->only('common_name'),
        ];
    }

    /**
     * Mensaje de éxito (toast) para la siguiente página.
     */
    private function flashSuccess(string $message): void
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }
}
