<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensajes predeterminados del resultado de un duelo (una sola fila).
 *
 * Se editan en el panel. Un mensaje vacío usa el texto integrado.
 * Cada tarjeta puede reemplazarlos con mensajes propios.
 */
class DuelMessageSetting extends Model
{
    /**
     * Textos integrados, usados cuando el mensaje predeterminado está vacío.
     *
     * Admiten los marcadores {tarjeta}, {correctas}, {total},
     * {intentos_restantes}, {intentos_maximos} y {espera}.
     */
    public const BUILT_IN = [
        'win_message' => '¡Ganaste el duelo! {tarjeta} ya es parte de tu colección.',
        'retry_message' => 'Acertaste {correctas} de {total}. Te quedan {intentos_restantes} de {intentos_maximos} intentos.',
        'locked_message' => 'Acertaste {correctas} de {total} y ya no te quedan intentos. Vuelve a intentarlo en {espera}.',
    ];

    protected $fillable = [
        'win_message',
        'retry_message',
        'locked_message',
    ];

    /**
     * La configuración (se crea si aún no existe).
     */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first()
            ?? self::create();
    }

    /**
     * Mensajes predeterminados efectivos (los configurados o, si están vacíos, los integrados).
     *
     * @return array{win_message: string, retry_message: string, locked_message: string}
     */
    public function effective(): array
    {
        return collect(self::BUILT_IN)
            ->map(fn (string $builtIn, string $field) => $this->{$field} ?: $builtIn)
            ->all();
    }
}
