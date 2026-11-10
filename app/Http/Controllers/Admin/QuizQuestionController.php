<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\GenerateQuizQuestionsRequest;
use App\Http\Requests\Admin\Card\ImportQuizQuestionsRequest;
use App\Models\Card;
use App\Models\QuizQuestion;
use App\Services\QuizGeneratorService;
use Illuminate\Http\JsonResponse;
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
            ]);

            foreach ($validated['answers'] as $index => $answer) {
                $question->answers()->create([
                    'answer' => $answer['answer'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'sort_order' => $index,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pregunta creada correctamente.',
        ]);

        return redirect()->route('admin.cards.quiz.index', $card);
    }

    /**
     * Generar preguntas con IA: se revisan en pantalla y se guardan con
     * storeImport().
     */
    public function generate(Card $card, QuizGeneratorService $generator): Response
    {
        return Inertia::render(
            'admin/quizquestions/Generate',
            [
                'card' => $card->load('species'),
                'existingQuestions' => $card->quizQuestions()->pluck('question'),
                'maxQuestions' => ImportQuizQuestionsRequest::MAX_QUESTIONS,
                'aiEnabled' => $generator->isConfigured(),
                'topics' => collect(QuizGeneratorService::TOPICS)
                    ->map(fn (array $topic, string $value) => ['value' => $value, 'label' => $topic['label']])
                    ->values(),
            ]
        );
    }

    /**
     * Pedir preguntas a la IA. No guarda nada.
     */
    public function storeGenerate(
        GenerateQuizQuestionsRequest $request,
        Card $card,
        QuizGeneratorService $generator
    ): JsonResponse {
        $validated = $request->validated();

        return response()->json([
            'success' => true,
            'data' => [
                'questions' => $generator->generate(
                    $card,
                    (int) $validated['count'],
                    $validated['topic'],
                    $validated['exclude'] ?? []
                ),
            ],
        ]);
    }

    /**
     * Importar preguntas en lote: prompt para un LLM, pegar o subir el JSON,
     * vista previa e importación.
     */
    public function import(Card $card): Response
    {
        return Inertia::render(
            'admin/quizquestions/Import',
            [
                'card' => $card->load('species'),

                // Para detectar repetidas y pedirle al LLM que no las repita.
                'existingQuestions' => $card->quizQuestions()->pluck('question'),

                'maxQuestions' => ImportQuizQuestionsRequest::MAX_QUESTIONS,
            ]
        );
    }

    /**
     * Guardar las preguntas importadas. Las repetidas (ya en la tarjeta o
     * dentro del lote) se omiten.
     */
    public function storeImport(
        ImportQuizQuestionsRequest $request,
        Card $card
    ): RedirectResponse {
        $validated = $request->validated();

        $seen = $card->quizQuestions()
            ->pluck('question')
            ->mapWithKeys(fn (string $question) => [QuizQuestion::normalizeText($question) => true])
            ->all();

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $card,
            $validated,
            $request,
            &$seen,
            &$imported,
            &$skipped
        ) {
            foreach ($validated['questions'] as $item) {
                $key = QuizQuestion::normalizeText($item['question']);

                if (isset($seen[$key])) {
                    $skipped++;

                    continue;
                }

                $seen[$key] = true;

                $question = $card->quizQuestions()->create([
                    'question' => trim($item['question']),
                    'is_active' => $request->boolean('is_active', true),
                ]);

                /*
                 * Orden al azar: la correcta no queda siempre primero en el
                 * panel (en los duelos las respuestas se mezclan de todos modos).
                 */
                $answers = collect([
                    ['answer' => $item['correct_answer'], 'is_correct' => true],
                    ...array_map(fn (string $answer) => ['answer' => $answer, 'is_correct' => false], $item['wrong_answers']),
                ])->shuffle();

                foreach ($answers->values() as $index => $answer) {
                    $question->answers()->create([
                        'answer' => trim($answer['answer']),
                        'is_correct' => $answer['is_correct'],
                        'sort_order' => $index,
                    ]);
                }

                $imported++;
            }
        });

        $message = $imported === 1
            ? '1 pregunta importada'
            : "{$imported} preguntas importadas";

        if ($skipped) {
            $message .= " ({$skipped} omitida".($skipped === 1 ? '' : 's').' por repetida'.($skipped === 1 ? '' : 's').')';
        }

        Inertia::flash('toast', [
            'type' => $imported ? 'success' : 'warning',
            'message' => $message.'.',
        ]);

        return redirect()->route('admin.cards.quiz.index', $card);
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
            ]);

            /*
             * Se actualizan las respuestas existentes en lugar de recrearlas,
             * para conservar sus ids: un duelo en curso las envía al responder.
             */
            $existingAnswers = $quizQuestion->answers()->get()->keyBy('id');

            $keptAnswerIds = [];

            foreach ($validated['answers'] as $index => $answer) {
                $attributes = [
                    'answer' => $answer['answer'],
                    'is_correct' => (bool) $answer['is_correct'],
                    'sort_order' => $index,
                ];

                $existing = $existingAnswers->get($answer['id'] ?? null);

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    $existing = $quizQuestion->answers()->create($attributes);
                }

                $keptAnswerIds[] = $existing->id;
            }

            $quizQuestion->answers()
                ->whereNotIn('id', $keptAnswerIds)
                ->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pregunta actualizada correctamente.',
        ]);

        return redirect()->route('admin.cards.quiz.index', $card);
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pregunta eliminada correctamente.',
        ]);

        return redirect()->route('admin.cards.quiz.index', $card);
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
