<?php

namespace App\Services\CardTransfer;

use App\Models\Species;

/**
 * Busca en este servidor la especie a la que apunta una tarjeta del paquete.
 *
 * 1. Por slug (único en `species`).
 * 2. Si no, por nombre científico (sin distinguir mayúsculas ni espacios),
 *    solo si coincide exactamente una especie.
 * Nunca crea especies.
 */
class SpeciesResolver
{
    /** @var array<string, int>|null */
    private ?array $bySlug = null;

    /** @var array<string, list<int>>|null */
    private ?array $byScientificName = null;

    /**
     * @param  array{slug?: ?string, scientific_name?: ?string}  $reference
     * @return array{species_id: int|null, match: 'slug'|'scientific_name'|null}
     */
    public function resolve(array $reference): array
    {
        $this->load();

        $slug = (string) ($reference['slug'] ?? '');

        if ($slug !== '' && isset($this->bySlug[$slug])) {
            return ['species_id' => $this->bySlug[$slug], 'match' => 'slug'];
        }

        $name = self::normalize((string) ($reference['scientific_name'] ?? ''));
        $candidates = $name !== '' ? ($this->byScientificName[$name] ?? []) : [];

        if (count($candidates) === 1) {
            return ['species_id' => $candidates[0], 'match' => 'scientific_name'];
        }

        return ['species_id' => null, 'match' => null];
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * Clave con la que el panel identifica una especie del paquete
     * (en `species_map`): el slug o, si no lo trae, el nombre científico.
     *
     * @param  array{slug?: ?string, scientific_name?: ?string}  $reference
     */
    public static function key(array $reference): string
    {
        return (string) (($reference['slug'] ?? '') ?: self::normalize((string) ($reference['scientific_name'] ?? '')));
    }

    private function load(): void
    {
        if ($this->bySlug !== null) {
            return;
        }

        $this->bySlug = [];
        $this->byScientificName = [];

        foreach (Species::query()->get(['id', 'slug', 'scientific_name']) as $species) {
            $this->bySlug[$species->slug] = $species->id;
            $this->byScientificName[self::normalize((string) $species->scientific_name)][] = $species->id;
        }
    }
}
