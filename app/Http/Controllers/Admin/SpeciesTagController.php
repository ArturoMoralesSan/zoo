<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeciesTag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SpeciesTagController extends Controller
{
    public function index(Request $request)
    {
        $tags = SpeciesTag::query()
            ->withCount('species')
            ->when($request->search, function ($query, $search) {
                $query->where(
                    'name',
                    'like',
                    "%{$search}%"
                );
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/species-tags/Index',
            [
                'tags' => $tags,

                'filters' => [
                    'search' => $request->search,
                ],
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'admin/species-tags/Create'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:species_tags,name',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated['slug'] = Str::slug(
            $validated['name']
        );

        $validated['is_active'] = $request->boolean(
            'is_active'
        );

        SpeciesTag::create($validated);

        return redirect()
            ->route('admin.species-tags.index')
            ->with(
                'success',
                'Tag creada correctamente.'
            );
    }

    public function edit(SpeciesTag $speciesTag)
    {
        return Inertia::render(
            'admin/species-tags/Edit',
            [
                'tag' => $speciesTag,
            ]
        );
    }

    public function update(
        Request $request,
        SpeciesTag $speciesTag
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:species_tags,name,' .
                    $speciesTag->id,
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $validated['slug'] = Str::slug(
            $validated['name']
        );

        $validated['is_active'] = $request->boolean(
            'is_active'
        );

        $speciesTag->update($validated);

        return redirect()
            ->route('admin.species-tags.index')
            ->with(
                'success',
                'Tag actualizada correctamente.'
            );
    }

    public function destroy(SpeciesTag $speciesTag)
    {
        if ($speciesTag->species()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar esta tag porque está asignada a una o más especies.'
            );
        }

        $speciesTag->delete();

        return redirect()
            ->route('admin.species-tags.index')
            ->with(
                'success',
                'Tag eliminada correctamente.'
            );
    }
}