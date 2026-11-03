<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitDuelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => [
                'required',
                'array',
                'min:1',
            ],

            'answers.*.question_id' => [
                'required',
                'integer',
                'distinct',
            ],

            'answers.*.answer_id' => [
                'required',
                'integer',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],
        ];
    }
}
