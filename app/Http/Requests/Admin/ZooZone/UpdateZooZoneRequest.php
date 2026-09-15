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

            'map_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],

            'map_image_bounds' => [
                'nullable',
                'array',
            ],

            'map_image_bounds.north' => [
                'required_with:map_image_bounds',
                'numeric',
                'between:-90,90',
            ],

            'map_image_bounds.south' => [
                'required_with:map_image_bounds',
                'numeric',
                'between:-90,90',
            ],

            'map_image_bounds.east' => [
                'required_with:map_image_bounds',
                'numeric',
                'between:-180,180',
            ],

            'map_image_bounds.west' => [
                'required_with:map_image_bounds',
                'numeric',
                'between:-180,180',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Validaciones adicionales para el GeoJSON y el plano.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $geometry = $this->input('geometry');

                /*
                 * ----------------------------------------------------------
                 * Geometría opcional
                 * ----------------------------------------------------------
                 */
                if ($geometry !== null) {
                    /*
                     * Debe ser un Polygon.
                     */
                    if (($geometry['type'] ?? null) !== 'Polygon') {
                        $validator->errors()->add(
                            'geometry',
                            'La zona debe ser un polígono.'
                        );

                        return;
                    }

                    $coordinates = $geometry['coordinates'] ?? null;

                    if (
                        ! is_array($coordinates) ||
                        count($coordinates) !== 1
                    ) {
                        $validator->errors()->add(
                            'geometry',
                            'La zona debe contener un único polígono.'
                        );

                        return;
                    }

                    $ring = $coordinates[0] ?? null;

                    if (! is_array($ring) || count($ring) < 4) {
                        $validator->errors()->add(
                            'geometry',
                            'El polígono debe tener al menos 4 puntos.'
                        );

                        return;
                    }

                    foreach ($ring as $point) {
                        if (
                            ! is_array($point) ||
                            count($point) !== 2 ||
                            ! is_numeric($point[0]) ||
                            ! is_numeric($point[1])
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

                    /*
                     * El primer y último punto deben ser iguales
                     * para cerrar correctamente el polígono.
                     */
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
                }

                /*
                 * ----------------------------------------------------------
                 * Validación adicional del plano
                 * ----------------------------------------------------------
                 */
                $bounds = $this->input('map_image_bounds');

                if ($bounds === null) {
                    return;
                }

                if (
                    ! isset(
                        $bounds['north'],
                        $bounds['south'],
                        $bounds['east'],
                        $bounds['west']
                    )
                ) {
                    return;
                }

                $north = (float) $bounds['north'];
                $south = (float) $bounds['south'];
                $east = (float) $bounds['east'];
                $west = (float) $bounds['west'];

                /*
                 * Norte debe estar por encima de Sur.
                 */
                if ($north <= $south) {
                    $validator->errors()->add(
                        'map_image_bounds',
                        'La coordenada norte debe ser mayor que la coordenada sur.'
                    );
                }

                /*
                 * Este debe estar al este de Oeste.
                 */
                if ($east <= $west) {
                    $validator->errors()->add(
                        'map_image_bounds',
                        'La coordenada este debe ser mayor que la coordenada oeste.'
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
