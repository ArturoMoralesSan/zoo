<?php

namespace App\Enums;

enum CardRarity: string
{
    case Comun = 'comun';
    case Rara = 'rara';
    case Epica = 'epica';
    case EdicionEspecial = 'edicion_especial';

    /**
     * Etiqueta legible de la rareza.
     */
    public function label(): string
    {
        return match ($this) {
            self::Comun => 'Común',
            self::Rara => 'Rara',
            self::Epica => 'Épica',
            self::EdicionEspecial => 'Edición especial',
        };
    }

    /**
     * Configuración de duelos por defecto de la rareza. Se usa al crear la
     * configuración por rareza en una instalación nueva; después se
     * administra desde el panel (tabla "Configuración por rareza").
     *
     * @return array{questions_per_duel: int, retries: int, cooldown_minutes: int}
     */
    public function defaultDuelSettings(): array
    {
        return match ($this) {
            self::Comun => [
                'questions_per_duel' => 2,
                'retries' => 3,
                'cooldown_minutes' => 5,
            ],
            self::Rara => [
                'questions_per_duel' => 3,
                'retries' => 3,
                'cooldown_minutes' => 10,
            ],
            self::Epica => [
                'questions_per_duel' => 3,
                'retries' => 2,
                'cooldown_minutes' => 15,
            ],
            self::EdicionEspecial => [
                'questions_per_duel' => 4,
                'retries' => 1,
                'cooldown_minutes' => 30,
            ],
        };
    }
}
