export type CardRarity = 'comun' | 'rara' | 'epica' | 'edicion_especial';

export type DuelSettingsSource = 'rarity' | 'card';

export interface CardRaritySetting {
    id: number;
    rarity: CardRarity;
    questions_per_duel: number;
    retries: number;
    cooldown_minutes: number;
}

export const rarityOptions: { value: CardRarity; label: string }[] = [
    { value: 'comun', label: 'Común' },
    { value: 'rara', label: 'Rara' },
    { value: 'epica', label: 'Épica' },
    { value: 'edicion_especial', label: 'Edición especial' },
];

const rarityClasses: Record<CardRarity, string> = {
    comun: 'border-[#dce7df] bg-[#f2f7f4] text-[#52685a]',
    rara: 'border-blue-200 bg-blue-50 text-blue-700',
    epica: 'border-purple-200 bg-purple-50 text-purple-700',
    edicion_especial: 'border-amber-200 bg-amber-50 text-amber-700',
};

export const getRarityLabel = (value: string): string =>
    rarityOptions.find((option) => option.value === value)?.label ?? value;

export const getRarityClasses = (value: string): string =>
    rarityClasses[value as CardRarity] ?? rarityClasses.comun;
