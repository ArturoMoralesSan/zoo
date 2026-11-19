<?php

namespace App\Http\Requests\Admin\PointMode;

use App\Models\PointMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePointModeRequest extends FormRequest
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
        $mode = $this->route('point_mode');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('point_modes', 'name')->ignore(
                    $mode instanceof PointMode ? $mode->id : null
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'rules' => [
                'present',
                'array',
            ],

            'rules.*.point_rule_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('point_rules', 'id'),
            ],

            // Vacío = la regla usa sus puntos base en este modo.
            'rules.*.points' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'rules' => 'reglas',
            'rules.*.point_rule_id' => 'regla',
            'rules.*.points' => 'puntos',
        ];
    }

    /**
     * Puntos por regla para sync(): solo las reglas que el modo cambia.
     *
     * @return array<int, array{points: int}>
     */
    public function overrides(): array
    {
        $overrides = [];

        foreach ((array) $this->validated('rules', []) as $rule) {
            if (isset($rule['points'])) {
                $overrides[(int) $rule['point_rule_id']] = ['points' => (int) $rule['points']];
            }
        }

        return $overrides;
    }
}
