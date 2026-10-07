<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Obtener el quiz activo de una tarjeta.
     */
    public function show(Card $card): JsonResponse
    {
        $questions = $card->quizQuestions()
            ->where('is_active', true)
            ->with([
                'answers' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,

            'data' => [
                'card' => [
                    'id' => $card->id,
                    'name' => $card->name,
                    'rarity' => $card->rarity,
                    'edition' => $card->edition,
                    'description' => $card->description,
                    'card_image' => $card->card_image,
                    'model_name' => $card->model_name,
                    'model_file' => $card->model_file,
                    'model_url' => $card->model_url,
                    'model_format' => $card->model_format,
                    'model_description' => $card->model_description,
                ],

                'questions' => $questions->map(
                    function ($question) {
                        return [
                            'id' => $question->id,
                            'question' => $question->question,
                            'sort_order' => $question->sort_order,

                            'answers' => $question->answers->map(
                                function ($answer) {
                                    return [
                                        'id' => $answer->id,
                                        'answer' => $answer->answer,
                                        'sort_order' => $answer->sort_order,
                                    ];
                                }
                            )->values(),
                        ];
                    }
                )->values(),

                'total_questions' => $questions->count(),
            ],
        ]);
    }

    /**
     * Evaluar las respuestas del quiz.
     */
    public function submit(
        Request $request,
        Card $card
    ): JsonResponse {
        $validated = $request->validate([
            'answers' => [
                'required',
                'array',
                'min:1',
            ],

            'answers.*.question_id' => [
                'required',
                'integer',
            ],

            'answers.*.answer_id' => [
                'required',
                'integer',
            ],
        ]);

        $questions = $card->quizQuestions()
            ->where('is_active', true)
            ->with([
                'answers' => function ($query) {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->get();

        if ($questions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarjeta no tiene preguntas activas.',
            ], 404);
        }

        $questionMap = $questions->keyBy('id');

        $submittedAnswers = collect(
            $validated['answers']
        );

        $score = 0;

        $results = [];

        foreach ($submittedAnswers as $submittedAnswer) {
            $questionId = (int) $submittedAnswer['question_id'];

            $answerId = (int) $submittedAnswer['answer_id'];

            if (! $questionMap->has($questionId)) {
                return response()->json([
                    'success' => false,
                    'message' => "La pregunta {$questionId} no pertenece a esta tarjeta.",
                ], 422);
            }

            $question = $questionMap->get($questionId);

            $answer = $question->answers
                ->firstWhere('id', $answerId);

            if (! $answer) {
                return response()->json([
                    'success' => false,
                    'message' => "La respuesta {$answerId} no pertenece a la pregunta {$questionId}.",
                ], 422);
            }

            $isCorrect = (bool) $answer->is_correct;

            if ($isCorrect) {
                $score++;
            }

            $correctAnswer = $question->answers
                ->firstWhere('is_correct', true);

            $results[] = [
                'question_id' => $question->id,
                'answer_id' => $answer->id,
                'is_correct' => $isCorrect,
                'correct_answer_id' => $correctAnswer?->id,
            ];
        }

        $totalQuestions = $questions->count();

        $answeredQuestions = $submittedAnswers->count();

        $percentage = $answeredQuestions > 0
            ? round(($score / $answeredQuestions) * 100)
            : 0;

        return response()->json([
            'success' => true,

            'data' => [
                'card_id' => $card->id,

                'score' => $score,

                'answered_questions' => $answeredQuestions,

                'total_questions' => $totalQuestions,

                'percentage' => $percentage,

                'passed' => $percentage >= 70,

                'results' => $results,
            ],
        ]);
    }
}