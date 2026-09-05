<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Species;
use App\Models\SpeciesCategory;
use App\Models\SpeciesImage;
use App\Models\SpeciesLocation;
use App\Models\SpeciesModel;
use App\Models\SpeciesTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SpeciesController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $species = Species::query()
            ->with('category')
            ->withCount([
                'images',
                'models',
                'locations',
                'tags',
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where(
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
                $request->category_id,
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
                    'search' => $request->search,
                    'category_id' => $request->category_id,
                ],
            ]
        );
    }

    public function show(Species $species)
    {
        $species->load([
            'category',
            'images',
            'models',
            'locations',
            'tags',
        ]);

        return Inertia::render('admin/species/Show', [
            'species' => $species,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
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
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | Información
            |--------------------------------------------------------------------------
            */

            'species_category_id' => [
                'required',
                'exists:species_categories,id',
            ],

            'common_name' => [
                'required',
                'string',
                'max:255',
            ],

            'scientific_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'habitat' => [
                'nullable',
                'string',
                'max:255',
            ],

            'origin' => [
                'nullable',
                'string',
                'max:255',
            ],

            'diet' => [
                'nullable',
                'string',
                'max:255',
            ],

            'conservation_status' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            'tags' => [
                'nullable',
                'array',
            ],

            'tags.*' => [
                'integer',
                'exists:species_tags,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Imágenes
            |--------------------------------------------------------------------------
            */

            'main_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'thumbnail_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'gallery_images' => [
                'nullable',
                'array',
            ],

            'gallery_images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            'model_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_file' => [
                'nullable',
                'file',
                'mimes:glb,gltf,usdz',
                'max:51200',
            ],

            'model_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'model_format' => [
                'nullable',
                'string',
                'max:50',
            ],

            'model_description' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Ubicación
            |--------------------------------------------------------------------------
            */

            'location_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'location_description' => [
                'nullable',
                'string',
            ],
        ]);

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
                !empty($validated['model_name'])
                || $request->hasFile('model_file')
                || !empty($validated['model_url'])
            ) {

                $modelPath = null;

                if (
                    $request->hasFile('model_file') &&
                    $request->file('model_file')->isValid()
                ) {
                    $modelPath = $this->moveUploadedFile(
                        $request->file('model_file'),
                        'species/' . $species->id . '/models'
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
            | Solo se crea cuando existen ambas coordenadas.
            |
            */

            if (
                $validated['latitude'] !== null
                && $validated['longitude'] !== null
            ) {
                SpeciesLocation::create([
                    'species_id' =>
                        $species->id,

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

    public function edit(Species $species)
    {
        $species->load([
            'category',
            'tags',
            'images',
            'models',
            'locations',
        ]);

        return Inertia::render(
            'admin/species/Edit',
            [
                'species' =>
                    $species,

                'categories' =>
                    SpeciesCategory::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),

                'tags' =>
                    SpeciesTag::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
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
        Request $request,
        Species $species
    ) {
        $validated = $request->validate([

            /*
            |--------------------------------------------------------------------------
            | Información
            |--------------------------------------------------------------------------
            */

            'species_category_id' => [
                'required',
                'exists:species_categories,id',
            ],

            'common_name' => [
                'required',
                'string',
                'max:255',
            ],

            'scientific_name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'habitat' => [
                'nullable',
                'string',
                'max:255',
            ],

            'origin' => [
                'nullable',
                'string',
                'max:255',
            ],

            'diet' => [
                'nullable',
                'string',
                'max:255',
            ],

            'conservation_status' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            'tags' => [
                'nullable',
                'array',
            ],

            'tags.*' => [
                'integer',
                'exists:species_tags,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | Imágenes
            |--------------------------------------------------------------------------
            */

            'main_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'thumbnail_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'card_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'gallery_images' => [
                'nullable',
                'array',
            ],

            'gallery_images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Modelo 3D
            |--------------------------------------------------------------------------
            */

            'model_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'model_file' => [
                'nullable',
                'file',
                'mimes:glb,gltf,usdz',
                'max:51200',
            ],

            'model_url' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'model_format' => [
                'nullable',
                'string',
                'max:50',
            ],

            'model_description' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Ubicación
            |--------------------------------------------------------------------------
            */

            'location_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'location_description' => [
                'nullable',
                'string',
            ],
        ]);

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

                $currentImage = $species->images()
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

                $currentImage = $species->images()
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

                $currentImage = $species->images()
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

                $lastSortOrder = $species->images()
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
                |--------------------------------------------------------------
                | Nuevo archivo 3D
                |--------------------------------------------------------------
                */

                if (
                    $request->hasFile('model_file') &&
                    $request->file('model_file')->isValid()
                ) {

                    $newModelPath = $this->moveUploadedFile(
                        $request->file('model_file'),
                        'species/' . $species->id . '/models'
                    );

                    /*
                    | Eliminamos el archivo anterior solamente
                    | después de guardar correctamente el nuevo.
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
                || $validated['latitude'] !== null
                || $validated['longitude'] !== null
                || !empty($validated['location_description']);

            if ($hasLocationData) {

                /*
                | Si existe cualquier dato de ubicación,
                | ambas coordenadas son obligatorias.
                */

                if (
                    $validated['latitude'] === null
                    || $validated['longitude'] === null
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'latitude' =>
                            'La latitud es obligatoria cuando se registra una ubicación.',

                        'longitude' =>
                            'La longitud es obligatoria cuando se registra una ubicación.',
                    ]);
                }

                $locationData = [
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

    public function destroy(Species $species)
    {
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

    /**
     * Mueve un archivo subido directamente al almacenamiento público.
     *
     * Se utiliza move() en lugar de store() debido al comportamiento
     * del UploadedFile en el entorno actual de Windows/PHP.
     */
    private function moveUploadedFile(
        \Illuminate\Http\UploadedFile $file,
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

        $extension = $file->getClientOriginalExtension();

        $filename = uniqid(
            '',
            true
        ) . '.' . $extension;

        $file->move(
            $destination,
            $filename
        );

        return $directory . '/' . $filename;
    }
}