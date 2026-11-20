<?php

namespace App\Http\Controllers\Api;

use App\Enums\Habitat;
use App\Http\Controllers\Controller;
use App\Models\Species;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SpeciesController extends Controller
{
    /**
     * Listado de especies.
     */
    public function index(): JsonResponse
    {
        $species = Species::query()
            ->with([
                'category:id,name',
                'images' => function ($query) {
                    $query
                        ->select(
                            'id',
                            'species_id',
                            'type',
                            'path',
                            'alt_text',
                            'sort_order'
                        )
                        ->where('is_active', true)
                        ->orderBy('sort_order');
                },
            ])
            ->where('is_active', true)
            ->orderBy('common_name')
            ->get()
            ->map(function (Species $species) {
                $image = $species->images->first();

                return [
                    'id' => $species->id,
                    'common_name' => $species->common_name,
                    'scientific_name' => $species->scientific_name,
                    'slug' => $species->slug,
                    'description' => $species->description,
                    'category' => $species->category
                        ? [
                            'id' => $species->category->id,
                            'name' => $species->category->name,
                        ]
                        : null,
                    'image' => $image
                        ? url(Storage::url($image->path))
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'species' => $species,
            ],
        ]);
    }

    /**
     * Detalle de una especie.
     */
    public function show(Species $species): JsonResponse
    {
        if (! $species->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'La especie no está disponible.',
            ], 404);
        }

        $species->load([
            'category:id,name',
            'tags' => function ($query) {
                $query
                    ->select(
                        'species_tags.id',
                        'species_tags.name',
                        'species_tags.slug'
                    )
                    ->where('is_active', true)
                    ->orderBy('name');
            },
            'images' => function ($query) {
                $query
                    ->select(
                        'id',
                        'species_id',
                        'type',
                        'path',
                        'alt_text',
                        'sort_order'
                    )
                    ->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'species' => [
                    'id' => $species->id,
                    'common_name' => $species->common_name,
                    'scientific_name' => $species->scientific_name,
                    'slug' => $species->slug,
                    'description' => $species->description,
                    // Etiquetas en español, como antes de los catálogos.
                    'habitat' => $species->habitatLabel(),
                    'origin' => $species->origin,
                    'diet' => $species->diet?->label(),
                    'conservation_status' => $species->conservation_status?->label(),

                    // Códigos estables de los catálogos.
                    'habitats' => $species->sortedHabitats()
                        ->map(fn (Habitat $habitat) => [
                            'code' => $habitat->value,
                            'label' => $habitat->label(),
                        ])
                        ->values(),
                    'diet_code' => $species->diet?->value,
                    'conservation_status_code' => $species->conservation_status?->value,

                    'category' => $species->category
                        ? [
                            'id' => $species->category->id,
                            'name' => $species->category->name,
                        ]
                        : null,

                    'tags' => $species->tags
                        ->map(function ($tag) {
                            return [
                                'id' => $tag->id,
                                'name' => $tag->name,
                                'slug' => $tag->slug,
                            ];
                        })
                        ->values(),

                    'images' => $species->images
                        ->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'type' => $image->type,
                                'url' => url(Storage::url($image->path)),
                                'alt_text' => $image->alt_text,
                                'sort_order' => $image->sort_order,
                            ];
                        })
                        ->values(),
                ],
            ],
        ]);
    }
}
