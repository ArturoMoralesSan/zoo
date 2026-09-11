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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SpeciesController extends Controller
{
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
                fn ($query, $categoryId) =>
                    $query->where(
                        'species_category_id',
                        $categoryId
                    )
            )
            ->orderBy('common_name')
            ->paginate(10)
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

        DB::transaction(function () use (
            $request,
            $validated
        ) {
            /*
            |--------------------------------------------------------------------------
            | Crear especie
            |--------------------------------------------------------------------------
            */

            $species = Species::create([
                'species_category_id' =>
                    $validated['species_category_id'],

                'common_name' =>
                    $validated['common_name'],

                'scientific_name' =>
                    $validated['scientific_name'],

                'slug' =>
                    Str::slug(
                        $validated['common_name']
                    ),

                'description' =>
                    $validated['description'] ?? null,

                'habitat' =>
                    $validated['habitat'] ?? null,

                'origin' =>
                    $validated['origin'] ?? null,

                'diet' =>
                    $validated['diet'] ?? null,

                'conservation_status' =>
                    $validated['conservation_status'] ?? null,

                'is_active' =>
                    $request->boolean('is_active'),
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
                $path = $this->moveUploadedFile(
                    $request->file('main_image'),
                    'species/' . $species->id
                );

                SpeciesImage::create([
                    'species_id' =>
                        $species->id,

                    'type' =>
                        'main',

                    'path' =>
                        $path,

                    'alt_text' =>
                        $species->common_name,

                    'is_active' =>
                        true,

                    'sort_order' =>
                        0,
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
                $path = $this->moveUploadedFile(
                    $request->file('thumbnail_image'),
                    'species/' . $species->id
                );

                SpeciesImage::create([
                    'species_id' =>
                        $species->id,

                    'type' =>
                        'thumbnail',

                    'path' =>
                        $path,

                    'alt_text' =>
                        $species->common_name,

                    'is_active' =>
                        true,

                    'sort_order' =>
                        0,
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
                    'species/' . $species->id
                );

                SpeciesImage::create([
                    'species_id' =>
                        $species->id,

                    'type' =>
                        'card',

                    'path' =>
                        $path,

                    'alt_text' =>
                        $species->common_name,

                    'is_active' =>
                        true,

                    'sort_order' =>
                        0,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Galería
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('gallery_images')) {
                foreach (
                    $request->file('gallery_images')
                    as $index => $image
                ) {
                    if (!$image->isValid()) {
                        continue;
                    }

                    $path = $this->moveUploadedFile(
                        $image,
                        'species/' . $species->id
                    );

                    SpeciesImage::create([
                        'species_id' =>
                            $species->id,

                        'type' =>
                            'gallery',

                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            $index,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            if (
                !empty($validated['model_name']) ||
                $request->hasFile('model_file') ||
                !empty($validated['model_url'])
            ) {
                $modelPath = null;

                if (
                    $request->hasFile('model_file') &&
                    $request->file('model_file')->isValid()
                ) {
                    $modelPath = $this->moveUploadedFile(
                        $request->file('model_file'),
                        'species/' .
                            $species->id .
                            '/models'
                    );
                }

                SpeciesModel::create([
                    'species_id' =>
                        $species->id,

                    'name' =>
                        $validated['model_name']
                        ?? $species->common_name . ' 3D',

                    'path' =>
                        $modelPath,

                    'url' =>
                        $validated['model_url']
                        ?? null,

                    'format' =>
                        $validated['model_format']
                        ?? null,

                    'description' =>
                        $validated['model_description']
                        ?? null,

                    'is_active' =>
                        true,
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
                    'species_id' =>
                        $species->id,

                    'zone_id' =>
                        $validated['zone_id'] ?? null,

                    'name' =>
                        $validated['location_name']
                        ?: 'Ubicación principal',

                    'latitude' =>
                        $validated['latitude'],

                    'longitude' =>
                        $validated['longitude'],

                    'description' =>
                        $validated['location_description']
                        ?? null,

                    'is_active' =>
                        true,
                ]);
            }
        });

        return redirect()
            ->route('admin.species.index')
            ->with(
                'success',
                'Especie creada correctamente.'
            );
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

        return Inertia::render(
            'admin/species/Edit',
            [
                'species' => $species,

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

        DB::transaction(function () use (
            $request,
            $species,
            $validated
        ) {
            /*
            |--------------------------------------------------------------------------
            | Información de la especie
            |--------------------------------------------------------------------------
            */

            $species->update([
                'species_category_id' =>
                    $validated['species_category_id'],

                'common_name' =>
                    $validated['common_name'],

                'scientific_name' =>
                    $validated['scientific_name'],

                'slug' =>
                    Str::slug(
                        $validated['common_name']
                    ),

                'description' =>
                    $validated['description'] ?? null,

                'habitat' =>
                    $validated['habitat'] ?? null,

                'origin' =>
                    $validated['origin'] ?? null,

                'diet' =>
                    $validated['diet'] ?? null,

                'conservation_status' =>
                    $validated['conservation_status'] ?? null,

                'is_active' =>
                    $request->boolean('is_active'),
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

                $path = $this->moveUploadedFile(
                    $request->file('main_image'),
                    'species/' . $species->id
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
                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' =>
                            $species->id,

                        'type' =>
                            'main',

                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            0,
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

                $path = $this->moveUploadedFile(
                    $request->file('thumbnail_image'),
                    'species/' . $species->id
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
                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' =>
                            $species->id,

                        'type' =>
                            'thumbnail',

                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            0,
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
                    'species/' . $species->id
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
                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,
                    ]);
                } else {
                    SpeciesImage::create([
                        'species_id' =>
                            $species->id,

                        'type' =>
                            'card',

                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            0,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Galería
            |--------------------------------------------------------------------------
            |
            | Las imágenes nuevas se agregan a las existentes.
            |
            */

            if ($request->hasFile('gallery_images')) {
                $lastSortOrder = $species
                    ->images()
                    ->where('type', 'gallery')
                    ->max('sort_order');

                $sortOrder = is_null($lastSortOrder)
                    ? 0
                    : $lastSortOrder + 1;

                foreach (
                    $request->file('gallery_images')
                    as $index => $image
                ) {
                    if (!$image->isValid()) {
                        continue;
                    }

                    $path = $this->moveUploadedFile(
                        $image,
                        'species/' . $species->id
                    );

                    SpeciesImage::create([
                        'species_id' =>
                            $species->id,

                        'type' =>
                            'gallery',

                        'path' =>
                            $path,

                        'alt_text' =>
                            $species->common_name,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            $sortOrder + $index,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            $currentModel = $species
                ->models()
                ->first();

            $hasModelData =
                !empty($validated['model_name'])
                || $request->hasFile('model_file')
                || !empty($validated['model_url'])
                || !empty($validated['model_format'])
                || !empty($validated['model_description']);

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
                        'species/' .
                            $species->id .
                            '/models'
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
                    'name' =>
                        $validated['model_name']
                        ?? $species->common_name . ' 3D',

                    'path' =>
                        $modelPath,

                    'url' =>
                        $validated['model_url']
                        ?? null,

                    'format' =>
                        $validated['model_format']
                        ?? null,

                    'description' =>
                        $validated['model_description']
                        ?? null,

                    'is_active' =>
                        true,
                ];

                if ($currentModel) {
                    $currentModel->update(
                        $modelData
                    );
                } else {
                    SpeciesModel::create([
                        'species_id' =>
                            $species->id,

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
                !empty($validated['location_name'])
                || $validated['zone_id'] !== null
                || $validated['latitude'] !== null
                || $validated['longitude'] !== null
                || !empty($validated['location_description']);

            if ($hasLocationData) {
                $locationData = [
                    'zone_id' =>
                        $validated['zone_id'] ?? null,

                    'name' =>
                        $validated['location_name']
                        ?: 'Ubicación principal',

                    'latitude' =>
                        $validated['latitude'],

                    'longitude' =>
                        $validated['longitude'],

                    'description' =>
                        $validated['location_description']
                        ?? null,

                    'is_active' =>
                        true,
                ];

                if ($currentLocation) {
                    $currentLocation->update(
                        $locationData
                    );
                } else {
                    SpeciesLocation::create([
                        'species_id' =>
                            $species->id,

                        ...$locationData,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.species.index')
            ->with(
                'success',
                'Especie actualizada correctamente.'
            );
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
            || $species->captures()->exists()
        ) {
            return back()->with(
                'error',
                'No se puede eliminar esta especie porque tiene información relacionada.'
            );
        }

        $species->delete();

        return redirect()
            ->route('admin.species.index')
            ->with(
                'success',
                'Especie eliminada correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTODOS PRIVADOS
    |--------------------------------------------------------------------------
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
            'app/public/' . $directory
        );

        File::ensureDirectoryExists(
            $destination
        );

        $extension =
            $file->getClientOriginalExtension();

        $filename =
            uniqid('', true) . '.' . $extension;

        $file->move(
            $destination,
            $filename
        );

        return $directory . '/' . $filename;
    }
}