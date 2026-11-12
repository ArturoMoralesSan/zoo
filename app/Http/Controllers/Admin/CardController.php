<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CreditAsset;
use App\Exceptions\InvalidGlbException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\StoreCardRequest;
use App\Http\Requests\Admin\Card\UpdateCardRequest;
use App\Models\Card;
use App\Models\CardCapture;
use App\Models\CardQuizState;
use App\Models\CardRaritySetting;
use App\Models\DuelMessageSetting;
use App\Models\Species;
use App\Services\CardImageOptimizer;
use App\Services\GlbInspection;
use App\Services\GlbInspector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
     * Columnas del modelo 3D vacías (tarjeta sin modelo).
     */
    private const EMPTY_MODEL = [
        'model_file' => null,
        'model_original_file' => null,
        'model_target_height' => null,
        'model_original_height' => null,
        'model_final_height' => null,
        'model_final_width' => null,
        'model_final_depth' => null,
    ];

    public function __construct(private CardImageOptimizer $imageOptimizer) {}

    /**
     * Lista de tarjetas.
     */
    public function index(): Response
    {
        $cards = Card::query()
            ->with([
                'species:id,common_name,scientific_name',
                'raritySetting',
                'credits:id,creditable_type,creditable_id,asset,role,based_on_asset,sort_order',
            ])
            /*
             * Contenido para duelos que se muestra en cada tarjeta.
             */
            ->withCount([
                'qrCodes',
                'quizQuestions as active_quiz_questions_count' => fn ($query) => $query->where('is_active', true),
            ])
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
            ->paginate(12)
            ->withQueryString();

        $cards->getCollection()->each(function (Card $card) {
            $card->append('effective_questions_per_duel');

            // Recursos subidos sin licencia (insignia "Falta licencia").
            $card->setAttribute('missing_credits', $this->missingCreditValues($card));
            $card->unsetRelation('credits');
        });

        $devTools = (bool) config('app.zoodeck_dev');

        return Inertia::render(
            'admin/cards/Index',
            [
                'cards' => $cards,

                // Pestañas de desarrollo (APP_ZOODECK_DEV).
                'devTools' => $devTools,

                'raritySettings' => $this->raritySettings(),

                /*
                 * Mensajes predeterminados del duelo: los guardados (vacíos = texto
                 * integrado) y los textos integrados.
                 */
                'duelMessages' => DuelMessageSetting::current()
                    ->only(['win_message', 'retry_message', 'locked_message']),

                'duelMessagesBuiltIn' => DuelMessageSetting::BUILT_IN,

                /*
                 * Tarjetas obtenidas por los usuarios, para poder revocarlas.
                 * Solo con las herramientas de desarrollo activas.
                 */
                'captures' => ! $devTools ? [] : CardCapture::query()
                    ->with([
                        'user:id,name,email',
                        'card:id,name,rarity',
                    ])
                    ->latest('captured_at')
                    ->latest('id')
                    ->get(['id', 'user_id', 'card_id', 'captured_at']),

                /*
                 * Intentos de duelo de los usuarios con tarjetas que aún no tienen,
                 * para poder reiniciarlos. Solo con las herramientas de desarrollo
                 * activas.
                 */
                'quizStates' => ! $devTools ? [] : CardQuizState::query()
                    ->with([
                        'user:id,name,email',
                        'card:id,name,rarity,duel_settings_source,retries',
                        'card.raritySetting',
                    ])
                    ->latest('updated_at')
                    ->latest('id')
                    ->get()
                    ->map(fn (CardQuizState $state) => [
                        'id' => $state->id,
                        'failed_attempts' => $state->failed_attempts,
                        'max_attempts' => $state->card?->effective_retries,
                        'locked_until' => $state->isLocked()
                            ? $state->locked_until->toJSON()
                            : null,
                        'user' => $state->user?->only(['id', 'name', 'email']),
                        'card' => $state->card?->only(['id', 'name', 'rarity']),
                    ]),

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

                'duelMessageDefaults' => $this->duelMessageDefaults(),

                'creditSummary' => $this->creditSummary($card),

                'fileSizes' => $this->fileSizes($card),
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

                'duelMessageDefaults' => $this->duelMessageDefaults(),

                'glbConfig' => $this->glbConfig(),

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

        /*
         * El modelo escalado se valida antes de escribir cualquier archivo.
         */
        $inspection = $this->inspectModel($request);
        $sizeNote = $this->modelSizeNote($request, $inspection);

        $written = [];
        $card = null;

        try {
            DB::transaction(function () use (
                $request,
                $validated,
                $inspection,
                &$written,
                &$card
            ) {
                $card = Card::create(
                    $this->cardAttributes($request, $validated)
                );

                $this->storeCardFiles($request, $card);

                $this->storeModelFiles($request, $card, $inspection, $written);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($written);

            throw $exception;
        }

        /*
         * Con archivos, el siguiente paso es registrar sus créditos (sin ellos
         * la tarjeta no se puede activar).
         */
        if ($card->missingCredits()) {
            $this->flashSaved('Tarjeta creada. Registra los créditos de sus archivos para poder activarla.', $inspection, $sizeNote);

            return redirect()->route('admin.cards.credits.index', $card);
        }

        $this->flashSaved('Tarjeta creada correctamente.', $inspection, $sizeNote);

        return redirect()->route('admin.cards.index');
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

                'duelMessageDefaults' => $this->duelMessageDefaults(),

                'glbConfig' => $this->glbConfig(),

                /*
                 * Original guardado (o el archivo actual en modelos anteriores)
                 * para volver a escalar el modelo.
                 */
                'modelOriginalUrl' => $card->model_file
                    ? route('admin.cards.model.original', $card)
                    : null,

                'fileSizes' => $this->fileSizes($card),

                'creditSummary' => $this->creditSummary($card),

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

        $inspection = $this->inspectModel($request);
        $sizeNote = $this->modelSizeNote($request, $inspection);

        /*
         * Archivos nuevos en recursos que ya tienen créditos: los créditos se
         * conservan, pero hay que revisarlos.
         */
        $replaced = array_filter(
            CreditAsset::cases(),
            fn (CreditAsset $asset) => $card->credits()->where('asset', $asset->value)->exists()
                && $request->hasFile($asset === CreditAsset::Model ? 'model_original_file' : $asset->column())
        );

        $written = [];
        $obsolete = [];

        try {
            DB::transaction(function () use (
                $request,
                $card,
                $validated,
                $inspection,
                &$written,
                &$obsolete
            ) {
                $card->update(
                    $this->cardAttributes($request, $validated)
                );

                $this->storeCardFiles($request, $card);

                $obsolete = $this->storeModelFiles($request, $card, $inspection, $written);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredFiles($written);

            throw $exception;
        }

        /*
         * Los archivos anteriores se eliminan solo después de guardar.
         */
        $this->deleteStoredFiles($obsolete);

        $this->flashSaved(
            'Tarjeta actualizada correctamente.'.($replaced
                ? ' El archivo cambió: revisa los créditos de '.implode(', ', array_map(fn (CreditAsset $asset) => mb_strtolower($asset->label()), $replaced)).'.'
                : ''),
            $inspection,
            $sizeNote
        );

        return redirect()->route('admin.cards.index');
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
            return $this->backWithToast(
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

            /*
             * Modelo original (disco privado).
             */
            $originalDisk = Storage::disk(config('glb.disks.original'));

            if ($originalDisk->exists($cardDirectory)) {
                $originalDisk->deleteDirectory($cardDirectory);
            }

            $card->credits()->delete();

            $card->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Tarjeta eliminada correctamente.',
        ]);

        return redirect()->route('admin.cards.index');
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
            return $this->backWithToast(
                'error',
                'La tarjeta no tiene esta imagen.'
            );
        }

        $this->deletePublicFile($card->{$field});

        DB::transaction(function () use ($card, $field, $image) {
            $originals = $card->image_original_bytes ?? [];
            unset($originals[$image]);

            $card->update([
                $field => null,
                'image_original_bytes' => $originals ?: null,
            ]);

            $this->deleteAssetCredits($card, CreditAsset::from($image));
        });

        return $this->backWithToast(
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
            return $this->backWithToast(
                'error',
                'La tarjeta no tiene un archivo 3D.'
            );
        }

        $obsolete = [
            [config('glb.disks.resized'), $card->model_file],
            [config('glb.disks.original'), $card->model_original_file],
        ];

        DB::transaction(function () use ($card) {
            $card->update(self::EMPTY_MODEL);

            $this->deleteAssetCredits($card, CreditAsset::Model);
        });

        $this->deleteStoredFiles($obsolete);

        return $this->backWithToast(
            'success',
            'Modelo 3D eliminado correctamente.'
        );
    }

    /**
     * Descargar el modelo original para volver a escalarlo en el navegador.
     *
     * En modelos subidos antes del escalado no hay original: el archivo
     * actual es el original.
     */
    public function originalModel(
        Card $card
    ): StreamedResponse {
        $candidates = [
            [config('glb.disks.original'), $card->model_original_file],
            [config('glb.disks.resized'), $card->model_file],
        ];

        foreach ($candidates as [$disk, $path]) {
            if ($path && Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download(
                    $path,
                    'modelo-original-'.$card->id.'.glb',
                    [
                        'Content-Type' => 'model/gltf-binary',
                        'Cache-Control' => 'private, no-store',
                    ]
                );
            }
        }

        abort(404);
    }

    /**
     * Configuración de duelos de cada rareza.
     */
    /**
     * Mensajes predeterminados del duelo (los usan las tarjetas sin mensajes propios).
     *
     * @return array<string, string>
     */
    private function duelMessageDefaults(): array
    {
        return DuelMessageSetting::current()->effective();
    }

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
                'reveal_answers_on_lockout',
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

        $usesCardMessages =
            $validated['messages_source'] === Card::MESSAGES_CARD;

        // Un mensaje vacío usa el de la rareza.
        $message = fn (string $field): ?string => $usesCardMessages
            ? (trim((string) ($validated[$field] ?? '')) ?: null)
            : null;

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

            /*
             * Mensajes del resultado del duelo: solo se guardan
             * si la tarjeta no usa los de su rareza.
             */
            'messages_source' => $validated['messages_source'],
            'win_message' => $message('win_message'),
            'retry_message' => $message('retry_message'),
            'locked_message' => $message('locked_message'),
        ];
    }

    /**
     * Guardar los archivos subidos de la tarjeta, reemplazando los anteriores.
     */
    private function storeCardFiles(
        FormRequest $request,
        Card $card
    ): void {
        $directory = 'cards/'.$card->id;

        foreach (CardImageOptimizer::FIELDS as $image => $field) {
            if (
                ! $request->hasFile($field) ||
                ! $request->file($field)->isValid()
            ) {
                continue;
            }

            $this->deletePublicFile($card->{$field});

            /*
             * Peso antes de comprimir: el navegador lo envía porque sube la
             * imagen ya comprimida; sin él, el del archivo recibido.
             */
            $originalBytes = (int) ($request->input('image_original_bytes.'.$image)
                ?: $request->file($field)->getSize());

            // Si el navegador no pudo comprimirla, lo intenta el servidor.
            $file = $this->imageOptimizer->prepareUpload(
                $request->file($field),
                $image
            );

            $card->update([
                $field => $this->moveUploadedFile($file, $directory),
                'image_original_bytes' => [
                    ...($card->image_original_bytes ?? []),
                    $image => $originalBytes,
                ],
            ]);
        }
    }

    /**
     * Eliminar los créditos de un recurso y quitar las referencias a él
     * (p. ej. la miniatura que era un render del modelo eliminado).
     */
    private function deleteAssetCredits(Card $card, CreditAsset $asset): void
    {
        $card->credits()->where('asset', $asset->value)->delete();

        $card->credits()
            ->where('based_on_asset', $asset->value)
            ->update(['based_on_asset' => null]);

        $card->unsetRelation('credits');
    }

    /**
     * Estado de los créditos de cada recurso con archivo, para el formulario
     * y el detalle de la tarjeta (la gestión está en su sección Créditos).
     *
     * @return array<int, array<string, mixed>>
     */
    private function creditSummary(Card $card): array
    {
        $card->load('credits.license');

        $summary = collect(CreditAsset::cases())
            ->filter(fn (CreditAsset $asset) => $card->hasAsset($asset))
            ->map(fn (CreditAsset $asset) => [
                'asset' => $asset->value,
                'label' => $asset->label(),
                'complete' => $card->hasCompleteCredits($asset),
                'origin' => $card->credit($asset)?->attribution,
                'extra_count' => $card->creditsFor($asset)->count() - ($card->credit($asset) ? 1 : 0),
            ])
            ->values()
            ->all();

        // La tarjeta se envía aparte: sin sus créditos.
        $card->unsetRelation('credits');

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    private function missingCreditValues(Card $card): array
    {
        return array_map(
            fn (CreditAsset $asset) => $asset->value,
            $card->missingCredits()
        );
    }

    /**
     * Configuración del tamaño de los modelos 3D para los formularios.
     *
     * @return array{minHeight: float, maxHeight: float, supportedExtensions: array<int, string>}
     */
    private function glbConfig(): array
    {
        return [
            'minHeight' => (float) config('glb.min_height'),
            'maxHeight' => (float) config('glb.max_height'),
            'supportedExtensions' => array_values(config('glb.supported_extensions', [])),
            'textures' => [
                'maxSize' => (int) config('glb.textures.max_size'),
                'quality' => (int) config('glb.textures.quality'),
            ],
        ];
    }

    /**
     * Validar el modelo escalado subido (si hay uno).
     *
     * @throws ValidationException
     */
    private function inspectModel(FormRequest $request): ?GlbInspection
    {
        if (! $request->hasFile('model_file')) {
            return null;
        }

        try {
            if ($request->hasFile('model_original_file')) {
                app(GlbInspector::class)->readJson(
                    $request->file('model_original_file')->getRealPath()
                );
            }

            return app(GlbInspector::class)->inspectResized(
                $request->file('model_file')->getRealPath(),
                (float) $request->input('model_target_height')
            );
        } catch (InvalidGlbException $exception) {
            throw ValidationException::withMessages([
                'model_file' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Guardar el modelo escalado (disco público) y el original (disco privado).
     *
     * Registra en $written cada archivo escrito para borrarlo si algo falla y
     * devuelve los archivos anteriores que se deben borrar después de guardar.
     *
     * @param  array<int, array{0: string, 1: string}>  $written
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function storeModelFiles(
        FormRequest $request,
        Card $card,
        ?GlbInspection $inspection,
        array &$written
    ): array {
        if (! $inspection) {
            return [];
        }

        $resizedDisk = config('glb.disks.resized');
        $originalDisk = config('glb.disks.original');
        $directory = 'cards/'.$card->id.'/models';

        $resizedPath = Storage::disk($resizedDisk)->putFileAs(
            $directory,
            $request->file('model_file'),
            uniqid('', true).'.glb'
        );

        if (! $resizedPath) {
            throw new RuntimeException('No se pudo guardar el modelo 3D.');
        }

        $written[] = [$resizedDisk, $resizedPath];

        $obsolete = [[$resizedDisk, $card->model_file]];
        $originalPath = $card->model_original_file;

        if ($request->hasFile('model_original_file')) {
            $originalPath = Storage::disk($originalDisk)->putFileAs(
                $directory.'/originals',
                $request->file('model_original_file'),
                uniqid('', true).'.glb'
            );

            if (! $originalPath) {
                throw new RuntimeException('No se pudo guardar el modelo 3D original.');
            }

            $written[] = [$originalDisk, $originalPath];
            $obsolete[] = [$originalDisk, $card->model_original_file];
        } elseif (! $originalPath && $card->model_file) {
            /*
             * Modelo anterior al escalado: el archivo actual es el original.
             * Se copia al disco privado antes de reemplazarlo.
             */
            $originalPath = $this->keepLegacyOriginal($card, $directory, $written);
        }

        /*
         * Medidas calculadas por el servidor; si no se pudieron verificar
         * (modelos con piel), las del navegador.
         */
        $card->update([
            'model_file' => $resizedPath,
            'model_original_file' => $originalPath,
            'model_target_height' => (float) $request->input('model_target_height'),
            'model_original_height' => (float) $request->input('model_original_height'),
            'model_final_height' => $inspection->height ?? (float) $request->input('model_final_height'),
            'model_final_width' => $inspection->width ?? (float) $request->input('model_final_width'),
            'model_final_depth' => $inspection->depth ?? (float) $request->input('model_final_depth'),
        ]);

        return $obsolete;
    }

    /**
     * Copiar el modelo actual (sin original guardado) al disco privado.
     *
     * @param  array<int, array{0: string, 1: string}>  $written
     */
    private function keepLegacyOriginal(
        Card $card,
        string $directory,
        array &$written
    ): ?string {
        $resized = Storage::disk(config('glb.disks.resized'));
        $originalDisk = config('glb.disks.original');

        if (! $resized->exists($card->model_file)) {
            return null;
        }

        $path = $directory.'/originals/'.uniqid('', true).'.glb';
        $stream = $resized->readStream($card->model_file);

        try {
            if (! Storage::disk($originalDisk)->writeStream($path, $stream)) {
                throw new RuntimeException('No se pudo conservar el modelo 3D original.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $written[] = [$originalDisk, $path];

        return $path;
    }

    /**
     * Eliminar archivos de sus discos (si existen).
     *
     * @param  array<int, array{0: string, 1: ?string}>  $files
     */
    private function deleteStoredFiles(array $files): void
    {
        foreach ($files as [$disk, $path]) {
            if ($path && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    /**
     * Mensaje de guardado y advertencias del modelo 3D.
     */
    private function flashSaved(
        string $message,
        ?GlbInspection $inspection,
        string $sizeNote
    ): void {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $message.$sizeNote,
        ]);

        if ($inspection?->warnings) {
            Inertia::flash('model_warnings', $inspection->warnings);
        }
    }

    /**
     * Peso del modelo guardado (el navegador ya optimizó sus texturas).
     *
     * Se mide antes de guardar: storeCardFiles() mueve las imágenes fuera de
     * /tmp, y después pedir archivos a request() (otra instancia que el
     * FormRequest) vuelve a leer todas las subidas y falla con la que ya no está.
     */
    private function modelSizeNote(
        FormRequest $request,
        ?GlbInspection $inspection
    ): string {
        $resized = $request->file('model_file');
        $original = $request->file('model_original_file');

        if (! $inspection || ! $resized instanceof UploadedFile) {
            return '';
        }

        $megabytes = fn (int $bytes) => number_format($bytes / 1048576, 2).' MB';

        return $original instanceof UploadedFile
            ? ' Modelo 3D: '.$megabytes($original->getSize()).' → '.$megabytes($resized->getSize()).'.'
            : ' Modelo 3D en la app: '.$megabytes($resized->getSize()).'.';
    }

    private function backWithToast(
        string $type,
        string $message
    ): RedirectResponse {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);

        return back();
    }

    /**
     * Peso de cada archivo de la tarjeta: el que descarga la app (`current`)
     * y el que se subió antes de optimizarlo (`original`), en bytes.
     *
     * @return array<string, array{current: int|null, original: int|null}>
     */
    private function fileSizes(Card $card): array
    {
        $sizes = [];

        foreach (CardImageOptimizer::FIELDS as $image => $field) {
            $sizes[$image] = [
                'current' => $this->storedSize('public', $card->{$field}),
                'original' => $card->{$field}
                    ? ($card->image_original_bytes[$image] ?? null)
                    : null,
            ];
        }

        $sizes['model'] = [
            'current' => $this->storedSize(config('glb.disks.resized'), $card->model_file),
            'original' => $this->storedSize(config('glb.disks.original'), $card->model_original_file),
        ];

        return $sizes;
    }

    /**
     * Peso en bytes de un archivo guardado, o null si no existe.
     */
    private function storedSize(string $disk, ?string $path): ?int
    {
        return $path && Storage::disk($disk)->exists($path)
            ? Storage::disk($disk)->size($path)
            : null;
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
