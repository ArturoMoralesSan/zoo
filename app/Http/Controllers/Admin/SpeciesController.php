<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Species\StoreSpeciesRequest;
use App\Http\Requests\Admin\Species\UpdateSpeciesRequest;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\SpeciesLocation;
use App\Models\SpeciesModel;
use App\Models\SpeciesTag;
use App\Models\ZooZone;
use App\Services\SpeciesImageOptimizer;
use App\Support\UploadExtension;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpeciesController extends Controller
{
    public function __construct(private SpeciesImageOptimizer $imageOptimizer) {}

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(): Response
    {
        $species = Species::query()
            ->with('category')
            ->withCount([
                'images',
                'models',
                'locations',
                'tags',
                // Imágenes con archivo y sin crédito de origen (sección Créditos).
                'imagesMissingCredits as images_missing_credits_count',
            ])
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($query) use ($search) {
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
                });
            })
            ->when(
                request('category_id'),
                fn ($query, $categoryId) => $query->where(
                    'species_category_id',
                    $categoryId
                )
            )
            ->orderBy('common_name')
            ->paginate(3)
            ->withQueryString();

        return Inertia::render(
            'admin/species/Index',
            [
                'species' => $species,

                'categories' => SpeciesCategory::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                'filters' => [
                    'search' => request('search'),
                    'category_id' => request('category_id'),
                ],
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

    public function show(Species $species): Response
    {
        $species->load([
            'category',
            'images',
            'models',
            'locations.zone',
            'tags',
        ]);

        $this->withImageBytes($species);

        return Inertia::render(
            'admin/species/Show',
            [
                'species' => $species,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(): Response
    {
        return Inertia::render(
            'admin/species/Create',
            [
                'categories' => SpeciesCategory::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                'tags' => SpeciesTag::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Zonas
                |--------------------------------------------------------------------------
                |
                | Se envía geometry, plano y límites del plano para
                | mostrar correctamente el mapa de la zona.
                |
                */

                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'geometry',
                        'map_image',
                        'map_image_bounds',
                    ]),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(
        StoreSpeciesRequest $request
    ): RedirectResponse {
        $validated = $request->validated();

        $deletedPaths = [];

        /** @var Species|null $species */
        $species = null;

        DB::transaction(function () use (
            $request,
            $validated,
            &$deletedPaths,
            &$species
        ) {
            /*
            |--------------------------------------------------------------------------
            | Crear especie
            |--------------------------------------------------------------------------
            */

            $species = Species::create([
                'species_category_id' => $validated['species_category_id'],

                'common_name' => $validated['common_name'],

                'scientific_name' => $validated['scientific_name'],

                'slug' => Str::slug(
                    $validated['common_name']
                ),

                'description' => $validated['description'] ?? null,

                'habitats' => $validated['habitats'] ?? null,

                'origin' => $validated['origin'] ?? null,

                'diet' => $validated['diet'] ?? null,

                'conservation_status' => $validated['conservation_status'] ?? null,

                'is_active' => $request->boolean('is_active'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            $species->tags()->sync(
                $validated['tags'] ?? []
            );

            /*
            |--------------------------------------------------------------------------
            | Imagen principal
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('main_image') &&
                $request->file('main_image')->isValid()
            ) {
                [$path, $originalBytes] = $this->storeOptimizedImage(
                    $request,
                    'main_image',
                    'main',
                    $species
                );

                SpeciesImage::create([
                    'species_id' => $species->id,

                    'type' => 'main',

                    'path' => $path,

                    'original_bytes' => $originalBytes,

                    'alt_text' => $species->common_name,

                    'is_active' => true,

                    'sort_order' => 0,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Miniatura
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('thumbnail_image') &&
                $request->file('thumbnail_image')->isValid()
            ) {
                [$path, $originalBytes] = $this->storeOptimizedImage(
                    $request,
                    'thumbnail_image',
                    'thumbnail',
                    $species
                );

                SpeciesImage::create([
                    'species_id' => $species->id,

                    'type' => 'thumbnail',

                    'path' => $path,

                    'original_bytes' => $originalBytes,

                    'alt_text' => $species->common_name,

                    'is_active' => true,

                    'sort_order' => 0,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Imagen de tarjeta
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('card_image') &&
                $request->file('card_image')->isValid()
            ) {
                $path = $this->moveUploadedFile(
                    $request->file('card_image'),
                    'species/'.$species->id
                );

                SpeciesImage::create([
                    'species_id' => $species->id,

                    'type' => 'card',

                    'path' => $path,

                    'alt_text' => $species->common_name,

                    'is_active' => true,

                    'sort_order' => 0,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Galería
            |--------------------------------------------------------------------------
            */

            $deletedPaths = $this->syncGallery(
                $request,
                $species,
                $validated
            );

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            if (
                ! empty($validated['model_name']) ||
                $request->hasFile('model_file') ||
                ! empty($validated['model_url'])
            ) {
                $modelPath = null;

                if (
                    $request->hasFile('model_file') &&
                    $request->file('model_file')->isValid()
                ) {
                    $modelPath = $this->moveUploadedFile(
                        $request->file('model_file'),
                        'species/'.
                            $species->id.
                            '/models',
                        'glb'
                    );
                }

                SpeciesModel::create([
                    'species_id' => $species->id,

                    'name' => $validated['model_name']
                        ?? $species->common_name.' 3D',

                    'path' => $modelPath,

                    'url' => $validated['model_url']
                        ?? null,

                    'format' => $validated['model_format']
                        ?? null,

                    'description' => $validated['model_description']
                        ?? null,

                    'is_active' => true,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Ubicación
            |--------------------------------------------------------------------------
            |
            | La ubicación solamente se crea cuando existen
            | latitud y longitud.
            |
            */

            if (
                $validated['latitude'] !== null &&
                $validated['longitude'] !== null
            ) {
                SpeciesLocation::create([
                    'species_id' => $species->id,

                    'zone_id' => $validated['zone_id'] ?? null,

                    'name' => $validated['location_name']
                        ?: 'Ubicación principal',

                    'latitude' => $validated['latitude'],

                    'longitude' => $validated['longitude'],

                    'description' => $validated['location_description']
                        ?? null,

                    'is_active' => true,
                ]);
            }
        });

        // Los archivos se borran después del commit para no perderlos si
        // la transacción falla.
        Storage::disk('public')->delete($deletedPaths);

        /*
         * Con imágenes, el siguiente paso es registrar sus créditos. Una
         * especie activa sin ellos se permite, pero se avisa: la app muestra
         * esas imágenes sin sus créditos.
         */
        if ($missing = $species?->missingCreditsText()) {
            Inertia::flash('toast', $species->is_active
                ? [
                    'type' => 'warning',
                    'message' => "Especie creada y activa, pero falta la licencia de {$missing}: la app las muestra sin sus créditos. Regístralos aquí.",
                ]
                : [
                    'type' => 'success',
                    'message' => 'Especie creada. Registra los créditos de sus imágenes.',
                ]);

            return redirect()->route('admin.species.credits.index', $species);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Especie creada correctamente.',
        ]);

        return redirect()->route('admin.species.index');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Species $species): Response
    {
        $species->load([
            'category',
            'tags',
            'images',
            'models',
            'locations.zone',
        ]);

        $this->withImageBytes($species);

        return Inertia::render(
            'admin/species/Edit',
            [
                'species' => $species,

                // Imágenes sin crédito de origen: aviso junto a "Especie activa".
                'uncreditedImageIds' => $species->imagesMissingCredits()->pluck('id'),

                'categories' => SpeciesCategory::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                'tags' => SpeciesTag::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ]),

                /*
                |--------------------------------------------------------------------------
                | Zonas
                |--------------------------------------------------------------------------
                |
                | Se envía geometry, plano y límites del plano.
                |
                */

                'zones' => ZooZone::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'geometry',
                        'map_image',
                        'map_image_bounds',
                    ]),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(
        UpdateSpeciesRequest $request,
        Species $species
    ): RedirectResponse {
        $validated = $request->validated();

        $deletedPaths = [];

        // Imágenes reemplazadas que ya tenían créditos (se conservan, pero hay que revisarlos).
        $replacedWithCredits = [];

        DB::transaction(function () use (
            $request,
            $species,
            $validated,
            &$deletedPaths,
            &$replacedWithCredits
        ) {
            /*
            |--------------------------------------------------------------------------
            | Información de la especie
            |--------------------------------------------------------------------------
            */

            $species->update([
                'species_category_id' => $validated['species_category_id'],

                'common_name' => $validated['common_name'],

                'scientific_name' => $validated['scientific_name'],

                'slug' => Str::slug(
                    $validated['common_name']
                ),

                'description' => $validated['description'] ?? null,

                'habitats' => $validated['habitats'] ?? null,

                'origin' => $validated['origin'] ?? null,

                'diet' => $validated['diet'] ?? null,

                'conservation_status' => $validated['conservation_status'] ?? null,

                'is_active' => $request->boolean('is_active'),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            $species->tags()->sync(
                $validated['tags'] ?? []
            );

            /*
            |--------------------------------------------------------------------------
            | Imagen principal
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('main_image') &&
                $request->file('main_image')->isValid()
            ) {
                $currentImage = $species
                    ->images()
                    ->where('type', 'main')
                    ->first();

                if ($currentImage?->credits()->exists()) {
                    $replacedWithCredits[] = mb_strtolower($currentImage->typeLabel());
                }

                [$path, $originalBytes] = $this->storeOptimizedImage(
                    $request,
                    'main_image',
                    'main',
                    $species
                );

                if ($currentImage) {
                    if (
                        $currentImage->path &&
                        Storage::disk('public')->exists(
                            $currentImage->path
                        )
                    ) {
                        Storage::disk('public')->delete(
                            $currentImage->path
                        );
                    }

                    $currentImage->update([
                        'path' => $path,

                        'original_bytes' => $originalBytes,

                        'alt_text' => $species->common_name,

                        'is_active' => true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' => $species->id,

                        'type' => 'main',

                        'path' => $path,

                        'original_bytes' => $originalBytes,

                        'alt_text' => $species->common_name,

                        'is_active' => true,

                        'sort_order' => 0,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Miniatura
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('thumbnail_image') &&
                $request->file('thumbnail_image')->isValid()
            ) {
                $currentImage = $species
                    ->images()
                    ->where('type', 'thumbnail')
                    ->first();

                if ($currentImage?->credits()->exists()) {
                    $replacedWithCredits[] = mb_strtolower($currentImage->typeLabel());
                }

                [$path, $originalBytes] = $this->storeOptimizedImage(
                    $request,
                    'thumbnail_image',
                    'thumbnail',
                    $species
                );

                if ($currentImage) {
                    if (
                        $currentImage->path &&
                        Storage::disk('public')->exists(
                            $currentImage->path
                        )
                    ) {
                        Storage::disk('public')->delete(
                            $currentImage->path
                        );
                    }

                    $currentImage->update([
                        'path' => $path,

                        'original_bytes' => $originalBytes,

                        'alt_text' => $species->common_name,

                        'is_active' => true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' => $species->id,

                        'type' => 'thumbnail',

                        'path' => $path,

                        'original_bytes' => $originalBytes,

                        'alt_text' => $species->common_name,

                        'is_active' => true,

                        'sort_order' => 0,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Imagen de tarjeta
            |--------------------------------------------------------------------------
            */

            if (
                $request->hasFile('card_image') &&
                $request->file('card_image')->isValid()
            ) {
                $currentImage = $species
                    ->images()
                    ->where('type', 'card')
                    ->first();

                $path = $this->moveUploadedFile(
                    $request->file('card_image'),
                    'species/'.$species->id
                );

                if ($currentImage) {
                    if (
                        $currentImage->path &&
                        Storage::disk('public')->exists(
                            $currentImage->path
                        )
                    ) {
                        Storage::disk('public')->delete(
                            $currentImage->path
                        );
                    }

                    $currentImage->update([
                        'path' => $path,

                        'alt_text' => $species->common_name,

                        'is_active' => true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' => $species->id,

                        'type' => 'card',

                        'path' => $path,

                        'alt_text' => $species->common_name,

                        'is_active' => true,

                        'sort_order' => 0,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Galería
            |--------------------------------------------------------------------------
            */

            $deletedPaths = $this->syncGallery(
                $request,
                $species,
                $validated
            );

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            $currentModel = $species
                ->models()
                ->first();

            $hasModelData =
                ! empty($validated['model_name'])
                || $request->hasFile('model_file')
                || ! empty($validated['model_url'])
                || ! empty($validated['model_format'])
                || ! empty($validated['model_description']);

            if ($hasModelData) {
                $modelPath =
                    $currentModel?->path;

                /*
                |--------------------------------------------------------------------------
                | Nuevo archivo 3D
                |--------------------------------------------------------------------------
                */

                if (
                    $request->hasFile('model_file') &&
                    $request->file('model_file')->isValid()
                ) {
                    $newModelPath = $this->moveUploadedFile(
                        $request->file('model_file'),
                        'species/'.
                            $species->id.
                            '/models',
                        'glb'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Eliminar archivo anterior
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $modelPath &&
                        Storage::disk('public')->exists(
                            $modelPath
                        )
                    ) {
                        Storage::disk('public')->delete(
                            $modelPath
                        );
                    }

                    $modelPath = $newModelPath;
                }

                $modelData = [
                    'name' => $validated['model_name']
                        ?? $species->common_name.' 3D',

                    'path' => $modelPath,

                    'url' => $validated['model_url']
                        ?? null,

                    'format' => $validated['model_format']
                        ?? null,

                    'description' => $validated['model_description']
                        ?? null,

                    'is_active' => true,
                ];

                if ($currentModel) {
                    $currentModel->update(
                        $modelData
                    );
                } else {
                    SpeciesModel::create([
                        'species_id' => $species->id,

                        ...$modelData,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Ubicación
            |--------------------------------------------------------------------------
            */

            $currentLocation = $species
                ->locations()
                ->first();

            $hasLocationData =
                ! empty($validated['location_name'])
                || $validated['zone_id'] !== null
                || $validated['latitude'] !== null
                || $validated['longitude'] !== null
                || ! empty($validated['location_description']);

            if ($hasLocationData) {
                $locationData = [
                    'zone_id' => $validated['zone_id'] ?? null,

                    'name' => $validated['location_name']
                        ?: 'Ubicación principal',

                    'latitude' => $validated['latitude'],

                    'longitude' => $validated['longitude'],

                    'description' => $validated['location_description']
                        ?? null,

                    'is_active' => true,
                ];

                if ($currentLocation) {
                    $currentLocation->update(
                        $locationData
                    );
                } else {
                    SpeciesLocation::create([
                        'species_id' => $species->id,

                        ...$locationData,
                    ]);
                }
            }
        });

        // Los archivos se borran después del commit para no perderlos si
        // la transacción falla.
        Storage::disk('public')->delete($deletedPaths);

        /*
         * Como en la tarjeta, los créditos de una imagen reemplazada se
         * conservan (suele ser la misma foto recortada), pero hay que revisarlos.
         */
        $missing = $species->is_active ? $species->missingCreditsText() : null;

        $message = $replacedWithCredits
            ? 'Especie actualizada. La '.implode(' y la ', $replacedWithCredits)
                .(count($replacedWithCredits) > 1 ? ' cambiaron' : ' cambió').': revisa sus créditos.'
            : 'Especie actualizada correctamente.';

        if ($missing) {
            $message .= " Está activa sin la licencia de {$missing}: la app las muestra sin sus créditos.";
        }

        Inertia::flash('toast', [
            'type' => $replacedWithCredits || $missing ? 'warning' : 'success',
            'message' => $message,
        ]);

        return redirect()->route('admin.species.index');
    }

    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Species $species
    ): RedirectResponse {
        if (
            $species->images()->exists()
            || $species->models()->exists()
            || $species->locations()->exists()
            // Las tarjetas se borran en cascada con la especie, y con ellas
            // las capturas de los visitantes.
            || $species->cards()->exists()
        ) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'No se puede eliminar esta especie porque tiene información relacionada.',
            ]);

            return back();
        }

        $species->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Especie eliminada correctamente.',
        ]);

        return redirect()->route('admin.species.index');
    }

    /**
     * |--------------------------------------------------------------------------
     * | DESTROY IMAGE
     * |--------------------------------------------------------------------------
     * |
     * | Elimina individualmente una imagen de la galería.
     * |
     */
    public function destroyImage(
        Species $species,
        SpeciesImage $image
    ): RedirectResponse {
        // La imagen debe pertenecer a la especie.
        if ($image->species_id !== $species->id) {
            abort(404);
        }

        // Por seguridad, este método solamente permite
        // eliminar imágenes de tipo galería.
        if ($image->type !== 'gallery') {
            return back()->with(
                'error',
                'La imagen seleccionada no pertenece a la galería.'
            );
        }

        DB::transaction(function () use ($image) {
            // Eliminar archivo físico.
            if (
                $image->path &&
                Storage::disk('public')->exists($image->path)
            ) {
                Storage::disk('public')->delete(
                    $image->path
                );
            }

            // Eliminar registro de la base de datos.
            $image->delete();
        });

        return back()->with(
            'success',
            'Imagen de la galería eliminada correctamente.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS PRIVADOS
    |--------------------------------------------------------------------------
    */

    /**
     * Deja la galería como quedó en el formulario: borra las imágenes
     * marcadas, guarda los archivos nuevos y aplica orden y texto
     * alternativo. Las imágenes que el formulario no menciona conservan
     * su orden relativo al final.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string> Rutas a borrar del disco después del commit.
     */
    private function syncGallery(
        Request $request,
        Species $species,
        array $validated
    ): array {
        $deletedPaths = [];

        $deletedIds = $validated['deleted_gallery_ids'] ?? [];

        if ($deletedIds !== []) {
            $species->images()
                ->where('type', 'gallery')
                ->whereIn('id', $deletedIds)
                ->get()
                ->each(function (SpeciesImage $image) use (&$deletedPaths) {
                    if ($image->path) {
                        $deletedPaths[] = $image->path;
                    }

                    $image->delete();
                });
        }

        $existing = $species->images()
            ->where('type', 'gallery')
            ->reorder('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        // Archivos nuevos, por su índice en gallery_images.
        $uploaded = [];

        foreach ($request->file('gallery_images', []) as $index => $file) {
            if (! $file->isValid()) {
                continue;
            }

            $uploaded[$index] = SpeciesImage::create([
                'species_id' => $species->id,
                'type' => 'gallery',
                'original_bytes' => $this->imageOptimizer->originalBytes(
                    $request,
                    'gallery_original_bytes.'.$index,
                    $file
                ),
                'path' => $this->moveUploadedFile(
                    $this->imageOptimizer->prepareUpload($file, 'gallery'),
                    'species/'.$species->id
                ),
                'alt_text' => $species->common_name,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        /** @var array<int, array{0: SpeciesImage, 1: string|null}> $ordered */
        $ordered = [];

        // validated() arma los elementos anidados regla por regla, así que
        // las claves pueden quedar desordenadas: la clave es la posición.
        $items = $validated['gallery'] ?? [];
        ksort($items);

        foreach ($items as $item) {
            $image = isset($item['id'])
                ? $existing->get($item['id'])
                : ($uploaded[$item['file_index'] ?? -1] ?? null);

            if ($image && ! isset($ordered[$image->id])) {
                $ordered[$image->id] = [
                    $image,
                    trim((string) ($item['alt_text'] ?? '')) ?: $species->common_name,
                ];
            }
        }

        foreach ([...$existing->values(), ...array_values($uploaded)] as $image) {
            $ordered[$image->id] ??= [$image, $image->alt_text];
        }

        foreach (array_values($ordered) as $position => [$image, $altText]) {
            $image->update([
                'sort_order' => $position,
                'alt_text' => $altText,
            ]);
        }

        return $deletedPaths;
    }

    /**
     * Peso actual de cada imagen (`bytes`), para mostrar el ahorro junto a
     * `original_bytes`.
     */
    private function withImageBytes(Species $species): void
    {
        $species->images->each(
            fn (SpeciesImage $image) => $image->setAttribute(
                'bytes',
                $this->imageOptimizer->storedBytes($image)
            )
        );
    }

    /**
     * Guarda la imagen principal o la miniatura. Si el navegador no pudo
     * comprimirla, lo intenta el servidor.
     *
     * @return array{0: string, 1: int} Ruta y peso antes de comprimir.
     */
    private function storeOptimizedImage(
        Request $request,
        string $field,
        string $kind,
        Species $species
    ): array {
        $file = $request->file($field);

        $originalBytes = $this->imageOptimizer->originalBytes(
            $request,
            'image_original_bytes.'.$kind,
            $file
        );

        $path = $this->moveUploadedFile(
            $this->imageOptimizer->prepareUpload($file, $kind),
            'species/'.$species->id
        );

        return [$path, $originalBytes];
    }

    private function moveUploadedFile(
        UploadedFile $file,
        string $directory,
        ?string $extension = null
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

        // Solo los GLB ya validados por GlbFile pasan su extensión.
        $extension ??= UploadExtension::for($file);

        $filename =
            uniqid('', true).'.'.$extension;

        $file->move(
            $destination,
            $filename
        );

        return $directory.'/'.$filename;
    }
}
