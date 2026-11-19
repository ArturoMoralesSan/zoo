// Íconos y colores del aviso de temporada (modos de puntos). El catálogo se
// comparte con el servidor (resources/data/point-season-styles.json); la app
// móvil traduce las mismas claves a sus íconos de Hugeicons.
import {
    BalloonsIcon,
    BeachIcon,
    BellIcon,
    BirdIcon,
    BugIcon,
    CactusIcon,
    CakeIcon,
    CampfireIcon,
    CatIcon,
    CrabIcon,
    CrownIcon,
    EggIcon,
    FireworksIcon,
    FishIcon,
    FlagIcon,
    FlowerIcon,
    GhostIcon,
    GiftIcon,
    GraduationCapIcon,
    HeartIcon,
    HorseIcon,
    IceCreamIcon,
    LeafIcon,
    MoonIcon,
    MountainIcon,
    OctopusIcon,
    PandaIcon,
    PartyPopperIcon,
    PawPrintIcon,
    PineTreeIcon,
    PumpkinIcon,
    RabbitIcon,
    RoseIcon,
    SchoolIcon,
    ShellIcon,
    SkullIcon,
    SnailIcon,
    SnowflakeIcon,
    SnowIcon,
    SparklesIcon,
    SproutIcon,
    SquirrelIcon,
    StarIcon,
    SunIcon,
    TicketIcon,
    TulipIcon,
    TurtleIcon,
    UmbrellaIcon,
    WatermelonIcon,
} from '@hugeicons/core-free-icons';

import catalogue from '../../data/point-season-styles.json';

export type HugeIcon = typeof SparklesIcon;

export interface SeasonIconGroup {
    key: string;
    label: string;
}

export interface SeasonIcon {
    key: string;
    label: string;
    group: string;
    // Nombre del export en @hugeicons/core-free-icons (el mismo en la app).
    icon: string;
    keywords: string[];
}

export interface SeasonColor {
    key: string;
    label: string;
    accent: string;
    on_accent: string;
    soft: string;
    text: string;
}

export const seasonIconGroups: SeasonIconGroup[] = catalogue.groups;
export const seasonIcons: SeasonIcon[] = catalogue.icons;
export const seasonColors: SeasonColor[] = catalogue.colors;

// Por nombre del export, para que el bundle solo incluya estos íconos.
export const hugeIconsByName: Record<string, HugeIcon> = {
    BalloonsIcon,
    BeachIcon,
    BellIcon,
    BirdIcon,
    BugIcon,
    CactusIcon,
    CakeIcon,
    CampfireIcon,
    CatIcon,
    CrabIcon,
    CrownIcon,
    EggIcon,
    FireworksIcon,
    FishIcon,
    FlagIcon,
    FlowerIcon,
    GhostIcon,
    GiftIcon,
    GraduationCapIcon,
    HeartIcon,
    HorseIcon,
    IceCreamIcon,
    LeafIcon,
    MoonIcon,
    MountainIcon,
    OctopusIcon,
    PandaIcon,
    PartyPopperIcon,
    PawPrintIcon,
    PineTreeIcon,
    PumpkinIcon,
    RabbitIcon,
    RoseIcon,
    SchoolIcon,
    ShellIcon,
    SkullIcon,
    SnailIcon,
    SnowflakeIcon,
    SnowIcon,
    SparklesIcon,
    SproutIcon,
    SquirrelIcon,
    StarIcon,
    SunIcon,
    TicketIcon,
    TulipIcon,
    TurtleIcon,
    UmbrellaIcon,
    WatermelonIcon,
};

// Lo que la app muestra cuando el modo deja "Predeterminado".
export const defaultSeasonIconKey: string = catalogue.default.icon;
export const defaultSeasonColorKey: string = catalogue.default.color;

const iconByKey = new Map(seasonIcons.map((icon) => [icon.key, icon]));
const colorByKey = new Map(seasonColors.map((color) => [color.key, color]));

export function findSeasonIcon(key: string | null): SeasonIcon | null {
    return key ? (iconByKey.get(key) ?? null) : null;
}

export function findSeasonColor(key: string | null): SeasonColor | null {
    return key ? (colorByKey.get(key) ?? null) : null;
}

/**
 * Ícono de Hugeicons para una clave; el predeterminado si es null o desconocida.
 */
export function seasonHugeIcon(key: string | null): HugeIcon {
    const icon = findSeasonIcon(key) ?? findSeasonIcon(defaultSeasonIconKey);

    return (icon && hugeIconsByName[icon.icon]) || SparklesIcon;
}

/**
 * Color para mostrar; el predeterminado si es null o desconocido.
 */
export function resolveSeasonColor(key: string | null): SeasonColor {
    return (
        findSeasonColor(key) ??
        (findSeasonColor(defaultSeasonColorKey) as SeasonColor)
    );
}

// Sin acentos ni mayúsculas: "Día de muertos" encuentra "dia de muertos".
export function normalizeSearch(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

/**
 * Íconos que coinciden con la búsqueda (nombre o palabras clave) y el grupo.
 */
export function searchSeasonIcons(
    query: string,
    group: string | null = null,
): SeasonIcon[] {
    const terms = normalizeSearch(query).split(/\s+/).filter(Boolean);

    return seasonIcons.filter((icon) => {
        if (group && icon.group !== group) {
            return false;
        }

        if (terms.length === 0) {
            return true;
        }

        const haystack = normalizeSearch(
            [icon.label, ...icon.keywords].join(' '),
        );

        return terms.every((term) => haystack.includes(term));
    });
}
