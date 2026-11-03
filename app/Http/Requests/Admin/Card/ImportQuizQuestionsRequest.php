<?php

namespace App\Http\Requests\Admin\Card;

use App\Models\QuizQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Importación de preguntas en lote (JSON generado con un LLM).
 *
 * Cada pregunta trae su respuesta correcta y dos incorrectas; el orden no
 * importa porque los duelos mezclan las respuestas.
 */
class ImportQuizQuestionsRequest extends FormRequest
{
    public const MAX_QUESTIONS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => [
                'required',
                'array',
                'min:1',
                'max:'.self::MAX_QUESTIONS,
            ],

            'questions.*.question' => [
                'required',
                'string',
                'max:5000',
            ],

            'questions.*.correct_answer' => [
                'required',
                'string',
                'max:1000',
            ],

            'questions.*.wrong_answers' => [
                'required',
                'array',
                'size:2',
            ],

            'questions.*.wrong_answers.*' => [
                'required',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Las tres respuestas de cada pregunta deben ser distintas.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ((array) $this->input('questions', []) as $index => $question) {
                    $answers = [
                        (string) ($question['correct_answer'] ?? ''),
                        ...array_map('strval', (array) ($question['wrong_answers'] ?? [])),
                    ];

                    $normalized = array_map(fn (string $answer) => QuizQuestion::normalizeText($answer), $answers);

                    if (count(array_unique($normalized)) < count($normalized)) {
                        $validator->errors()->add(
                            "questions.{$index}.wrong_answers",
                            'Las tres respuestas deben ser distintas.'
                        );
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'No hay preguntas para importar.',
            'questions.max' => 'Importa como máximo :max preguntas a la vez.',
            'questions.*.question.required' => 'Falta el texto de la pregunta.',
            'questions.*.correct_answer.required' => 'Falta la respuesta correcta.',
            'questions.*.wrong_answers.required' => 'Faltan las respuestas incorrectas.',
            'questions.*.wrong_answers.size' => 'Debe haber exactamente 2 respuestas incorrectas.',
            'questions.*.wrong_answers.*.required' => 'Una respuesta incorrecta está vacía.',
        ];
    }

    public function attributes(): array
    {
        return [
            'questions.*.question' => 'pregunta',
            'questions.*.correct_answer' => 'respuesta correcta',
            'questions.*.wrong_answers.*' => 'respuesta incorrecta',
        ];
    }
}
