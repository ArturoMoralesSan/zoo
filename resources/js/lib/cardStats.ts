import {
    BicepsFlexedIcon,
    Brain03Icon,
    EnergyIcon,
} from '@hugeicons/core-free-icons';

/**
 * Icono de Hugeicons (mismo formato que los de la plantilla de la tarjeta).
 */
export type CardStatIcon = typeof Brain03Icon;

export type CardStatKey = 'astucia' | 'rapidez' | 'fuerza';

/**
 * Estadísticas de la tarjeta, con los mismos iconos que la plantilla.
 */
export const CARD_STATS: {
    key: CardStatKey;
    label: string;
    help: string;
    icon: CardStatIcon;
}[] = [
    {
        key: 'astucia',
        label: 'Astucia',
        help: 'Nivel de ingenio y astucia.',
        icon: Brain03Icon,
    },
    {
        key: 'rapidez',
        label: 'Rapidez',
        help: 'Velocidad y agilidad.',
        icon: EnergyIcon,
    },
    {
        key: 'fuerza',
        label: 'Fuerza',
        help: 'Potencia y resistencia física.',
        icon: BicepsFlexedIcon,
    },
];

export const cardStat = (key: CardStatKey) =>
    CARD_STATS.find((stat) => stat.key === key)!;
