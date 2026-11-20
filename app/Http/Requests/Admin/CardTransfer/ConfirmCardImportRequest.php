<?php

namespace App\Http\Requests\Admin\CardTransfer;

use App\Services\CardTransfer\CardPackageImporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmCardImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([CardPackageImporter::MODE_SKIP, CardPackageImporter::MODE_OVERWRITE])],
            'replace_settings' => ['sometimes', 'boolean'],
            // Lista y no objeto: la clave puede ser un nombre científico con puntos.
            'species_map' => ['sometimes', 'array'],
            'species_map.*.key' => ['required', 'string', 'max:255'],
            'species_map.*.species_id' => ['nullable', 'integer', Rule::exists('species', 'id')],

            // Lo que falta en este servidor, por nombre normalizado (CardPackage::nameKey).
            'categories' => ['sometimes', 'array'],
            'categories.*.key' => ['required', 'string', 'max:255'],
            'categories.*.action' => ['required', Rule::in(['create', 'existing'])],
            'categories.*.id' => ['nullable', 'required_if:categories.*.action,existing', 'integer', Rule::exists('species_categories', 'id')],
            'tags' => ['sometimes', 'array'],
            'tags.*.key' => ['required', 'string', 'max:255'],
            'tags.*.action' => ['required', Rule::in(['create', 'existing', 'skip'])],
            'tags.*.id' => ['nullable', 'required_if:tags.*.action,existing', 'integer', Rule::exists('species_tags', 'id')],
            'zones' => ['sometimes', 'array'],
            'zones.*.key' => ['required', 'string', 'max:255'],
            'zones.*.zone_id' => ['nullable', 'integer', Rule::exists('zoo_zones', 'id')],
        ];
    }

    /**
     * Decisiones para CardPackageImporter: categorías (crear o una
     * existente), etiquetas (crear, existente u omitir) y zonas (una
     * existente o null para omitir la ubicación).
     *
     * @return array{categories: array<string, 'create'|int>, tags: array<string, 'create'|'skip'|int>, zones: array<string, int|null>}
     */
    public function references(): array
    {
        $choice = fn (array $item) => match ($item['action']) {
            'existing' => (int) $item['id'],
            default => $item['action'],
        };

        return [
            'categories' => collect($this->items('categories'))
                ->mapWithKeys(fn (array $item) => [$item['key'] => $choice($item)])
                ->all(),
            'tags' => collect($this->items('tags'))
                ->mapWithKeys(fn (array $item) => [$item['key'] => $choice($item)])
                ->all(),
            'zones' => collect($this->items('zones'))
                ->mapWithKeys(fn (array $item) => [
                    $item['key'] => isset($item['zone_id']) ? (int) $item['zone_id'] : null,
                ])
                ->all(),
        ];
    }

    /**
     * Especie elegida para cada especie del paquete que no se encontró.
     *
     * @return array<string, int|null>
     */
    public function speciesMap(): array
    {
        return collect($this->items('species_map'))
            ->mapWithKeys(fn (array $item) => [
                $item['key'] => isset($item['species_id']) ? (int) $item['species_id'] : null,
            ])
            ->all();
    }

    /**
     * Lista validada (o vacía) de elementos con forma de objeto.
     *
     * @return list<array<string, mixed>>
     */
    private function items(string $key): array
    {
        $items = $this->validated($key, []);

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    public function attributes(): array
    {
        return [
            'mode' => 'tarjetas existentes',
            'replace_settings' => 'reemplazar configuración global',
            'species_map' => 'especies',
            'species_map.*.species_id' => 'especie',
            'categories.*.id' => 'categoría',
            'tags.*.id' => 'etiqueta',
            'zones.*.zone_id' => 'zona',
        ];
    }
}
