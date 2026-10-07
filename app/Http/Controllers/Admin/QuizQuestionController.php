<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\QuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuizQuestionController extends Controller
{
    /**
     * Mostrar las preguntas de una Card.
     */
    public function index(Card $card): Response
    {
        $questions = QuizQuestion::query()
            ->where('card_id', $card->id)
            ->with([
                'answers',
            ])
            ->when(
                request('search'),
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where(
                                'question',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'answers',
                                function ($query) use ($search) {
                                    $query->where(
                                        'answer',
                                        'like',
                                        "%{$search}%"
                                    );
                                }
                            );
                    });
                }
            )
            ->when(
                request('is_active') !== null &&
                request('is_active') !== '',
                fn ($query) => $query->where(
                    'is_active',
                    filter_var(
                        request('is_active'),
                        FILTER_VALIDATE_BOOLEAN
                    )
                )
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/quizquestions/Index',
            [
                'card' => $card->load('species'),

                'questions' => $questions,

                'filters' => [
                    'search' => request('search'),
                    'is_active' => request('is_active'),
                ],
            ]
        );
    }

    /**
     * Mostrar formulario para crear una pregunta.
     */
    public function create(Card $card): Response
    {
        return Inertia::render(
            'admin/quizquestions/Create',
            [
                'card' => $card->load('species'),
            ]
        );
    }

    /**
     * Guardar una pregunta con sus 3 respuestas.
     */
    public function store(
        Request $request,
        Card $card
    ): RedirectResponse {
        $validated = $request->validate([
            'question' => [
                'required',
                'string',
                'max:5000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'answers' => [
                'required',
                'array',
                'size:3',
            ],

            'answers.*.answer' => [
                'required',
                'string',
                'max:1000',
            ],

            'answers.*.is_correct' => [
                'required',
                'boolean',
            ],
        ]);

        $this->validateCorrectAnswer(
            $validated['answers']
        );

        DB::transaction(function () use (
            $card,
            $validated
        ) {
            $question = $card->quizQuestions()->create([
                'question' => $validated['question'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            foreach ($validated['answers'] as $index => $answer) {
                $question->answers()->create([
                    'answer' => $answer['answer'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('admin.cards.quiz.index', $card)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pregunta creada correctamente.',
            ]);
    }

    /**
     * Mostrar una pregunta.
     */
    public function show(
        Card $card,
        QuizQuestion $quizQuestion
    ): Response {
        $this->ensureQuestionBelongsToCard(
            $card,
            $quizQuestion
        );

        $quizQuestion->load([
            'answers',
            'card.species',
        ]);

        return Inertia::render(
            'admin/quizquestions/Show',
            [
                'card' => $card->load('species'),
                'question' => $quizQuestion,
            ]
        );
    }

    /**
     * Mostrar formulario para editar una pregunta.
     */
    public function edit(
        Card $card,
        QuizQuestion $quizQuestion
    ): Response {
        $this->ensureQuestionBelongsToCard(
            $card,
            $quizQuestion
        );

        $quizQuestion->load([
            'answers',
        ]);

        return Inertia::render(
            'admin/quizquestions/Edit',
            [
                'card' => $card->load('species'),
                'question' => $quizQuestion,
            ]
        );
    }

    /**
     * Actualizar una pregunta y sus respuestas.
     */
    public function update(
        Request $request,
        Card $card,
        QuizQuestion $quizQuestion
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCard(
            $card,
            $quizQuestion
        );

        $validated = $request->validate([
            'question' => [
                'required',
                'string',
                'max:5000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'answers' => [
                'required',
                'array',
                'size:3',
            ],

            'answers.*.id' => [
                'nullable',
                'integer',
            ],

            'answers.*.answer' => [
                'required',
                'string',
                'max:1000',
            ],

            'answers.*.is_correct' => [
                'required',
                'boolean',
            ],
        ]);

        $this->validateCorrectAnswer(
            $validated['answers']
        );

        DB::transaction(function () use (
            $quizQuestion,
            $validated
        ) {
            $quizQuestion->update([
                'question' => $validated['question'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $quizQuestion->answers()->delete();

            foreach ($validated['answers'] as $index => $answer) {
                $quizQuestion->answers()->create([
                    'answer' => $answer['answer'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'sort_order' => $index,
                ]);
            }
        });

        return redirect()
            ->route('admin.cards.quiz.index', $card)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pregunta actualizada correctamente.',
            ]);
    }

    /**
     * Eliminar una pregunta.
     */
    public function destroy(
        Card $card,
        QuizQuestion $quizQuestion
    ): RedirectResponse {
        $this->ensureQuestionBelongsToCard(
            $card,
            $quizQuestion
        );

        $quizQuestion->delete();

        return redirect()
            ->route('admin.cards.quiz.index', $card)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pregunta eliminada correctamente.',
            ]);
    }

    /**
     * Validar las reglas del quiz:
     *
     * - exactamente 3 respuestas
     * - exactamente 1 correcta
     */
    private function validateCorrectAnswer(
        array $answers
    ): void {
        if (count($answers) !== 3) {
            throw ValidationException::withMessages([
                'answers' => 'La pregunta debe tener exactamente 3 respuestas.',
            ]);
        }

        $correctAnswers = collect($answers)
            ->filter(
                fn ($answer) => (bool) ($answer['is_correct'] ?? false)
            )
            ->count();

        if ($correctAnswers !== 1) {
            throw ValidationException::withMessages([
                'answers' => 'Debes seleccionar exactamente una respuesta correcta.',
            ]);
        }
    }

    /**
     * Evitar que se manipule una pregunta
     * perteneciente a otra Card.
     */
    private function ensureQuestionBelongsToCard(
        Card $card,
        QuizQuestion $quizQuestion
    ): void {
        abort_unless(
            $quizQuestion->card_id === $card->id,
            404
        );
    }
}
