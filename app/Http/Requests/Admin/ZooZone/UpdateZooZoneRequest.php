<?php

namespace App\Http\Requests\Admin\ZooZone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateZooZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('zoo-zones.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'geometry' => [
                'nullable',
                'array',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Validaciones adicionales para el GeoJSON.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $geometry = $this->input('geometry');

                // La geometría es opcional.
                if ($geometry === null) {
                    return;
                }

                // Debe ser un Polygon.
                if (($geometry['type'] ?? null) !== 'Polygon') {
                    $validator->errors()->add(
                        'geometry',
                        'La zona debe ser un polígono.'
                    );

                    return;
                }

                $coordinates = $geometry['coordinates'] ?? null;

                if (!is_array($coordinates) || count($coordinates) !== 1) {
                    $validator->errors()->add(
                        'geometry',
                        'La zona debe contener un único polígono.'
                    );

                    return;
                }

                $ring = $coordinates[0] ?? null;

                if (!is_array($ring) || count($ring) < 4) {
                    $validator->errors()->add(
                        'geometry',
                        'El polígono debe tener al menos 4 puntos.'
                    );

                    return;
                }

                foreach ($ring as $point) {
                    if (
                        !is_array($point) ||
                        count($point) !== 2 ||
                        !is_numeric($point[0]) ||
                        !is_numeric($point[1])
                    ) {
                        $validator->errors()->add(
                            'geometry',
                            'Los puntos del polígono deben contener longitud y latitud válidas.'
                        );

                        return;
                    }

                    $longitude = (float) $point[0];
                    $latitude = (float) $point[1];

                    if ($longitude < -180 || $longitude > 180) {
                        $validator->errors()->add(
                            'geometry',
                            'La longitud de la zona no es válida.'
                        );

                        return;
                    }

                    if ($latitude < -90 || $latitude > 90) {
                        $validator->errors()->add(
                            'geometry',
                            'La latitud de la zona no es válida.'
                        );

                        return;
                    }
                }

                // El primer y último punto deben ser iguales
                // para cerrar correctamente el polígono.
                $firstPoint = $ring[0];
                $lastPoint = $ring[count($ring) - 1];

                if (
                    (float) $firstPoint[0] !== (float) $lastPoint[0] ||
                    (float) $firstPoint[1] !== (float) $lastPoint[1]
                ) {
                    $validator->errors()->add(
                        'geometry',
                        'El polígono debe estar cerrado.'
                    );
                }
            },
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}