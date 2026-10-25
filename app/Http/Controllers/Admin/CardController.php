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
     * Imágenes que se pueden eliminar desde el formulario, por tipo.
     */
    public const IMAGE_FIELDS = [
        'thumbnail' => 'thumbnail_image',
        'artwork' => 'artwork_image',
    ];

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
        ])->loadCount([
            'qrCodes',
            'quizQuestions',
            'quizQuestions as active_quiz_questions_count' => fn ($query) => $query->where('is_active', true),
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

            $this->storeCardFiles($request, $card);

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

            $this->storeCardFiles($request, $card);
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
     * Eliminar solamente una imagen de la tarjeta (miniatura o ilustración).
     */
    public function destroyImage(
        Card $card,
        string $image
    ): RedirectResponse {
        $field = self::IMAGE_FIELDS[$image];

        if (! $card->{$field}) {
            return back()->with(
                'error',
                'La tarjeta no tiene esta imagen.'
            );
        }

        $this->deletePublicFile($card->{$field});

        $card->update([
            $field => null,
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

        $this->deletePublicFile($card->model_file);

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
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'astucia' => $validated['astucia'],
            'rapidez' => $validated['rapidez'],
            'fuerza' => $validated['fuerza'],
            'duel_settings_source' => $validated['duel_settings_source'],
            'fade_color' => strtolower($validated['fade_color']),
            'accent_color' => $validated['accent_color'] ?? null,
            'title_font_family' => $validated['title_font_family'],
            'title_font_weight' => $validated['title_font_weight'],
            'title_font_size' => $validated['title_font_size'],

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
     * Guardar los archivos subidos de la tarjeta, reemplazando los anteriores.
     */
    private function storeCardFiles(
        FormRequest $request,
        Card $card
    ): void {
        $directories = [
            'thumbnail_image' => 'cards/'.$card->id,
            'artwork_image' => 'cards/'.$card->id,
            'generated_image' => 'cards/'.$card->id,
            'model_file' => 'cards/'.$card->id.'/models',
        ];

        foreach ($directories as $field => $directory) {
            if (
                ! $request->hasFile($field) ||
                ! $request->file($field)->isValid()
            ) {
                continue;
            }

            $this->deletePublicFile($card->{$field});

            $card->update([
                $field => $this->moveUploadedFile(
                    $request->file($field),
                    $directory
                ),
            ]);
        }
    }

    /**
     * Eliminar un archivo del disco público si existe.
     */
    private function deletePublicFile(?string $path): void
    {
        if (
            $path &&
            Storage::disk('public')->exists($path)
        ) {
            Storage::disk('public')->delete($path);
        }
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
