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
        ];
    }

    /**
     * Especie elegida para cada especie del paquete que no se encontró.
     *
     * @return array<string, int|null>
     */
    public function speciesMap(): array
    {
        return collect($this->validated('species_map', []))
            ->mapWithKeys(fn (array $item) => [
                $item['key'] => isset($item['species_id']) ? (int) $item['species_id'] : null,
            ])
            ->all();
    }

    public function attributes(): array
    {
        return [
            'mode' => 'tarjetas existentes',
            'replace_settings' => 'reemplazar configuración global',
            'species_map' => 'especies',
            'species_map.*.species_id' => 'especie',
        ];
    }
}
