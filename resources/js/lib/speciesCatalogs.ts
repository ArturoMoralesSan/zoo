// Catálogos de especies. Espejo de App\Enums\{ConservationStatus,Diet,Habitat}:
// si cambias un código o una etiqueta aquí, cámbialo también allá.

export interface CatalogOption<T extends string> {
    value: T;
    label: string;
}

/*
 * Lista Roja de la UICN, de mayor a menor riesgo. Los colores siguen la
 * escala oficial; el texto oscuro sobre los tonos claros mantiene el contraste.
 */
export type ConservationStatusCode =
    'EX' | 'EW' | 'CR' | 'EN' | 'VU' | 'NT' | 'LC' | 'DD' | 'NE';

export interface ConservationStatusOption extends CatalogOption<ConservationStatusCode> {
    // Categorías "amenazadas" según la UICN (CR, EN, VU).
    threatened: boolean;
    badgeClass: string;
}

export const conservationStatusOptions: ConservationStatusOption[] = [
    {
        value: 'EX',
        label: 'Extinto',
        threatened: false,
        badgeClass: 'border-black bg-black text-white',
    },
    {
        value: 'EW',
        label: 'Extinto en estado silvestre',
        threatened: false,
        badgeClass: 'border-[#542344] bg-[#542344] text-white',
    },
    {
        value: 'CR',
        label: 'En peligro crítico',
        threatened: true,
        badgeClass: 'border-[#d81e05] bg-[#d81e05] text-white',
    },
    {
        value: 'EN',
        label: 'En peligro',
        threatened: true,
        badgeClass: 'border-[#fc7f3f] bg-[#fc7f3f] text-[#3b1600]',
    },
    {
        value: 'VU',
        label: 'Vulnerable',
        threatened: true,
        badgeClass: 'border-[#f9e814] bg-[#f9e814] text-[#3b3500]',
    },
    {
        value: 'NT',
        label: 'Casi amenazado',
        threatened: false,
        badgeClass: 'border-[#cce226] bg-[#cce226] text-[#2f3600]',
    },
    {
        value: 'LC',
        label: 'Preocupación menor',
        threatened: false,
        badgeClass: 'border-[#60c659] bg-[#60c659] text-[#0f3a0c]',
    },
    {
        value: 'DD',
        label: 'Datos insuficientes',
        threatened: false,
        badgeClass: 'border-[#d1d1c6] bg-[#d1d1c6] text-[#33332c]',
    },
    {
        value: 'NE',
        label: 'No evaluado',
        threatened: false,
        badgeClass: 'border-[#c8dccf] bg-white text-[#52685a]',
    },
];

export type DietCode =
    | 'carnivore'
    | 'herbivore'
    | 'omnivore'
    | 'insectivore'
    | 'piscivore'
    | 'frugivore'
    | 'nectarivore'
    | 'granivore'
    | 'folivore'
    | 'scavenger'
    | 'planktivore';

export const dietOptions: (CatalogOption<DietCode> & { hint: string })[] = [
    { value: 'carnivore', label: 'Carnívoro', hint: 'otros animales' },
    { value: 'herbivore', label: 'Herbívoro', hint: 'plantas' },
    { value: 'omnivore', label: 'Omnívoro', hint: 'plantas y animales' },
    { value: 'insectivore', label: 'Insectívoro', hint: 'insectos' },
    { value: 'piscivore', label: 'Piscívoro', hint: 'peces' },
    { value: 'frugivore', label: 'Frugívoro', hint: 'frutas' },
    { value: 'nectarivore', label: 'Nectarívoro', hint: 'néctar' },
    { value: 'granivore', label: 'Granívoro', hint: 'semillas' },
    { value: 'folivore', label: 'Folívoro', hint: 'hojas' },
    { value: 'scavenger', label: 'Carroñero', hint: 'animales muertos' },
    { value: 'planktivore', label: 'Planctívoro', hint: 'plancton' },
];

export type HabitatCode =
    | 'tropical_rainforest'
    | 'dry_forest'
    | 'temperate_forest'
    | 'coniferous_forest'
    | 'cloud_forest'
    | 'savanna'
    | 'grassland'
    | 'scrubland'
    | 'desert'
    | 'mountain'
    | 'polar'
    | 'wetland'
    | 'mangrove'
    | 'freshwater'
    | 'coast'
    | 'ocean'
    | 'coral_reef'
    | 'cave'
    | 'urban';

// Agrupados para que el selector sea fácil de recorrer.
export const habitatGroups: {
    name: string;
    options: CatalogOption<HabitatCode>[];
}[] = [
    {
        name: 'Bosques y selvas',
        options: [
            { value: 'tropical_rainforest', label: 'Selva tropical' },
            { value: 'dry_forest', label: 'Selva seca' },
            { value: 'temperate_forest', label: 'Bosque templado' },
            { value: 'coniferous_forest', label: 'Bosque de coníferas' },
            { value: 'cloud_forest', label: 'Bosque de niebla' },
        ],
    },
    {
        name: 'Zonas abiertas y áridas',
        options: [
            { value: 'savanna', label: 'Sabana' },
            { value: 'grassland', label: 'Pastizal' },
            { value: 'scrubland', label: 'Matorral' },
            { value: 'desert', label: 'Desierto' },
            { value: 'mountain', label: 'Montaña' },
            { value: 'polar', label: 'Tundra y regiones polares' },
        ],
    },
    {
        name: 'Agua',
        options: [
            { value: 'wetland', label: 'Humedal' },
            { value: 'mangrove', label: 'Manglar' },
            { value: 'freshwater', label: 'Ríos y lagos' },
            { value: 'coast', label: 'Costa' },
            { value: 'ocean', label: 'Océano' },
            { value: 'coral_reef', label: 'Arrecife de coral' },
        ],
    },
    {
        name: 'Otros',
        options: [
            { value: 'cave', label: 'Cuevas' },
            { value: 'urban', label: 'Zonas urbanas' },
        ],
    },
];

export const habitatOptions: CatalogOption<HabitatCode>[] =
    habitatGroups.flatMap((group) => group.options);

export function conservationStatusOption(
    code: string | null | undefined,
): ConservationStatusOption | null {
    return (
        conservationStatusOptions.find((option) => option.value === code) ??
        null
    );
}

export function dietLabel(code: string | null | undefined): string | null {
    return dietOptions.find((option) => option.value === code)?.label ?? null;
}

// Etiquetas en el orden del catálogo, igual que Species::sortedHabitats().
export function habitatLabels(
    codes: readonly string[] | null | undefined,
): string[] {
    const selected = new Set(codes ?? []);

    return habitatOptions
        .filter((option) => selected.has(option.value))
        .map((option) => option.label);
}

export function sortHabitats(codes: readonly string[]): HabitatCode[] {
    const selected = new Set(codes);

    return habitatOptions
        .filter((option) => selected.has(option.value))
        .map((option) => option.value);
}
