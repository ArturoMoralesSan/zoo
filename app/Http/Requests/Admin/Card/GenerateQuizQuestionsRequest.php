<?php

namespace App\Http\Requests\Admin\Card;

use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateQuizQuestionsRequest extends FormRequest
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
            'count' => [
                'required',
                'integer',
                'between:'.QuizGeneratorService::MIN_QUESTIONS.','.QuizGeneratorService::MAX_QUESTIONS,
            ],

            'topic' => [
                'required',
                'string',
                Rule::in(array_keys(QuizGeneratorService::TOPICS)),
            ],

            // Preguntas de la revisión aún sin guardar, para "Generar más".
            'exclude' => [
                'nullable',
                'array',
                'max:'.ImportQuizQuestionsRequest::MAX_QUESTIONS,
            ],

            'exclude.*' => [
                'string',
                'max:5000',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'count' => 'cantidad de preguntas',
            'topic' => 'tema',
            'exclude' => 'preguntas por excluir',
        ];
    }
}
