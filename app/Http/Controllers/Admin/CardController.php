<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\StoreCardRequest;
use App\Http\Requests\Admin\Card\UpdateCardRequest;
use App\Models\Card;
use App\Models\CardRaritySetting;
use App\Models\Species;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    /**
     * Lista de tarjetas.
     */
    public function index(): Response
    {
        $cards = Card::query()
            ->with('species:id,common_name,scientific_name')
            ->when(
                request('search'),
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('edition', 'like', "%{$search}%")
                            ->orWhereHas(
                                'species',
                                function ($query) use ($search) {
                                    $query
                                        ->where(
                                            'common_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'scientific_name',
                                            'like',
                                            "%{$search}%"
                                        );
                                }
                            );
                    });
                }
            )
            ->when(
                request('species_id'),
                fn ($query, $speciesId) => $query->where(
                    'species_id',
                    $speciesId
                )
            )
            ->when(
                request('rarity'),
                fn ($query, $rarity) => $query->where(
                    'rarity',
                    $rarity
                )
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(1)
            ->withQueryString();

        return Inertia::render(
            'admin/cards/Index',
            [
                'cards' => $cards,

                'raritySettings' => $this->raritySettings(),

                'species' => Species::query()
                    ->where('is_active', true)
                    ->orderBy('common_name')
                    ->get([
                        'id',
                        'common_name',
                        'scientific_name',
                    ]),

                'filters' => [
                    'search' => request('search'),
                    'species_id' => request('species_id'),
                    'rarity' => request('rarity'),
                ],
            ]
        );
    }

    /**
     * Mostrar una tarjeta.
     */
    public function show(Card $card): Response
    {
        $card->load([
            'species',
            'raritySetting',
        ])->append([
            'effective_questions_per_duel',
            'effective_retries',
            'effective_cooldown_minutes',
        ]);

        return Inertia::render(
            'admin/cards/Show',
            [
                'card' => $card,
            ]
        );
    }

    /**
     * Formulario para crear.
     */
    public function create(): Response
    {
        return Inertia::render(
            'admin/cards/Create',
            [
                'raritySettings' => $this->raritySettings(),

                'species' => Species::query()
                    ->where('is_active', true)
                    ->orderBy('common_name')
                    ->get([
                        'id',
                        'common_name',
                        'scientific_name',
                    ]),
            ]
        );
    }

    /**
     * Guardar tarjeta.
     */
    public function store(
        StoreCardRequest $request
    ): RedirectResponse {
        $validated = $request->validated();

        $card = DB::transaction(function () use (
            $request,
            $validated
        ) {
            $card = Card::create(
                $this->cardAttributes($request, $validated)
            );

            $this->syncQuizQuestions(
                $card,
                $validated['questions'] ?? []
            );

            /*
             * Imagen de la tarjeta.
             */
            if (
                $request->hasFile('card_image') &&
                $request->file('card_image')->isValid()
            ) {
                $card->update([
                    'card_image' => $this->moveUploadedFile(
                        $request->file('card_image'),
                        'cards/'.$card->id
                    ),
                ]);
            }

            /*
             * Modelo 3D.
             */
            if (
                $request->hasFile('model_file') &&
                $request->file('model_file')->isValid()
            ) {
                $modelPath = $this->moveUploadedFile(
                    $request->file('model_file'),
                    'cards/'.$card->id.'/models'
                );

                $card->update([
                    'model_file' => $modelPath,
                ]);
            }

            return $card;
        });

        return redirect()
            ->route('admin.cards.index')
            ->with(
                'success',
                'Tarjeta creada correctamente.'
            );
    }

    /**
     * Formulario para editar.
     */
    public function edit(Card $card): Response
    {
        $card->load([
            'species',
            'quizQuestions.answers',
        ]);

        return Inertia::render(
            'admin/cards/Edit',
            [
                'card' => $card,

                'raritySettings' => $this->raritySettings(),

                'species' => Species::query()
                    ->where('is_active', true)
                    ->orderBy('common_name')
                    ->get([
                        'id',
                        'common_name',
                        'scientific_name',
                    ]),
            ]
        );
    }

    /**
     * Actualizar tarjeta.
     */
    public function update(
        UpdateCardRequest $request,
        Card $card
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use (
            $request,
            $card,
            $validated
        ) {
            $card->update(
                $this->cardAttributes($request, $validated)
            );

            $this->syncQuizQuestions(
                $card,
                $validated['questions'] ?? []
            );

            /*
             * Nueva imagen.
             */
            if (
                $request->hasFile('card_image') &&
                $request->file('card_image')->isValid()
            ) {
                if (
                    $card->card_image &&
                    Storage::disk('public')->exists(
                        $card->card_image
                    )
                ) {
                    Storage::disk('public')->delete(
                        $card->card_image
                    );
                }

                $card->update([
                    'card_image' => $this->moveUploadedFile(
                        $request->file('card_image'),
                        'cards/'.$card->id
                    ),
                ]);
            }

            /*
             * Nuevo modelo 3D.
             */
            if (
                $request->hasFile('model_file') &&
                $request->file('model_file')->isValid()
            ) {
                if (
                    $card->model_file &&
                    Storage::disk('public')->exists(
                        $card->model_file
                    )
                ) {
                    Storage::disk('public')->delete(
                        $card->model_file
                    );
                }

                $modelPath = $this->moveUploadedFile(
                    $request->file('model_file'),
                    'cards/'.$card->id.'/models'
                );

                $card->update([
                    'model_file' => $modelPath,
                ]);
            }
        });

        return redirect()
            ->route('admin.cards.index')
            ->with(
                'success',
                'Tarjeta actualizada correctamente.'
            );
    }

    /**
     * Eliminar tarjeta.
     */
    public function destroy(
        Card $card
    ): RedirectResponse {
        /*
         * Por ahora no permitimos eliminar una tarjeta
         * que ya tenga capturas.
         */
        if ($card->captures()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar esta tarjeta porque ya tiene capturas.'
            );
        }

        DB::transaction(function () use ($card) {
            $cardDirectory = 'cards/'.$card->id;

            /*
             * Eliminamos todos los archivos asociados.
             */
            if (
                Storage::disk('public')->exists(
                    $cardDirectory
                )
            ) {
                Storage::disk('public')->deleteDirectory(
                    $cardDirectory
                );
            }

            $card->delete();
        });

        return redirect()
            ->route('admin.cards.index')
            ->with(
                'success',
                'Tarjeta eliminada correctamente.'
            );
    }

    /**
     * Eliminar solamente la imagen de la tarjeta.
     */
    public function destroyImage(
        Card $card
    ): RedirectResponse {
        if (! $card->card_image) {
            return back()->with(
                'error',
                'La tarjeta no tiene una imagen.'
            );
        }

        if (
            Storage::disk('public')->exists(
                $card->card_image
            )
        ) {
            Storage::disk('public')->delete(
                $card->card_image
            );
        }

        $card->update([
            'card_image' => null,
        ]);

        return back()->with(
            'success',
            'Imagen de la tarjeta eliminada correctamente.'
        );
    }

    /**
     * Eliminar solamente el modelo 3D.
     */
    public function destroyModel(
        Card $card
    ): RedirectResponse {
        if (! $card->model_file) {
            return back()->with(
                'error',
                'La tarjeta no tiene un archivo 3D.'
            );
        }

        if (
            Storage::disk('public')->exists(
                $card->model_file
            )
        ) {
            Storage::disk('public')->delete(
                $card->model_file
            );
        }

        $card->update([
            'model_file' => null,
        ]);

        return back()->with(
            'success',
            'Modelo 3D eliminado correctamente.'
        );
    }

    /**
     * Configuración de duelos de cada rareza.
     */
    private function raritySettings(): Collection
    {
        return CardRaritySetting::query()
            ->orderBy('id')
            ->get([
                'id',
                'rarity',
                'questions_per_duel',
                'retries',
                'cooldown_minutes',
            ]);
    }

    /**
     * Atributos de la tarjeta a partir de los datos validados.
     */
    private function cardAttributes(
        FormRequest $request,
        array $validated
    ): array {
        $usesCardSettings =
            $validated['duel_settings_source'] === Card::DUEL_SETTINGS_CARD;

        return [
            'species_id' => $validated['species_id'],
            'name' => $validated['name'],
            'rarity' => $validated['rarity'],
            'edition' => $validated['edition'] ?? null,
            'description' => $validated['description'] ?? null,
            'model_name' => $validated['model_name'] ?? null,
            'model_url' => $validated['model_url'] ?? null,
            'model_format' => $validated['model_format'] ?? null,
            'model_description' => $validated['model_description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'astucia' => $validated['astucia'],
            'rapidez' => $validated['rapidez'],
            'fuerza' => $validated['fuerza'],
            'duel_settings_source' => $validated['duel_settings_source'],

            /*
             * Solo se guardan valores propios si la tarjeta
             * no usa la configuración de su rareza.
             */
            'questions_per_duel' => $usesCardSettings
                ? $validated['questions_per_duel']
                : null,
            'retries' => $usesCardSettings
                ? $validated['retries']
                : null,
            'cooldown_minutes' => $usesCardSettings
                ? $validated['cooldown_minutes']
                : null,
        ];
    }

    /**
     * Sincronizar las preguntas del quiz y sus respuestas.
     *
     * Se actualizan las existentes, se crean las nuevas
     * y se eliminan las que ya no vienen en la petición.
     */
    private function syncQuizQuestions(
        Card $card,
        array $questions
    ): void {
        $existingQuestions = $card->quizQuestions()
            ->with('answers')
            ->get()
            ->keyBy('id');

        $keptQuestionIds = [];

        foreach (array_values($questions) as $index => $data) {
            $attributes = [
                'question' => $data['question'],
                'is_active' => filter_var(
                    $data['is_active'] ?? true,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'sort_order' => $data['sort_order'] ?? $index,
            ];

            $question = $existingQuestions->get($data['id'] ?? null);

            if ($question) {
                $question->update($attributes);
            } else {
                $question = $card->quizQuestions()->create($attributes);
            }

            $keptQuestionIds[] = $question->id;

            $existingAnswers = $question->answers->keyBy('id');

            $keptAnswerIds = [];

            foreach (array_values($data['answers']) as $answerIndex => $answerData) {
                $answerAttributes = [
                    'answer' => $answerData['answer'],
                    'is_correct' => filter_var(
                        $answerData['is_correct'],
                        FILTER_VALIDATE_BOOLEAN
                    ),
                    'sort_order' => $answerData['sort_order'] ?? $answerIndex,
                ];

                $answer = $existingAnswers->get($answerData['id'] ?? null);

                if ($answer) {
                    $answer->update($answerAttributes);
                } else {
                    $answer = $question->answers()->create($answerAttributes);
                }

                $keptAnswerIds[] = $answer->id;
            }

            $question->answers()
                ->whereNotIn('id', $keptAnswerIds)
                ->delete();
        }

        $card->quizQuestions()
            ->whereNotIn('id', $keptQuestionIds)
            ->delete();
    }

    /**
     * Mover archivo al almacenamiento público.
     */
    private function moveUploadedFile(
        UploadedFile $file,
        string $directory
    ): string {
        $directory = trim(
            $directory,
            '/'
        );

        $destination = storage_path(
            'app/public/'.$directory
        );

        File::ensureDirectoryExists(
            $destination
        );

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $filename =
            uniqid('', true).'.'.$extension;

        $file->move(
            $destination,
            $filename
        );

        return $directory.'/'.$filename;
    }
}
