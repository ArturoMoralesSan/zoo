<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeciesCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpeciesCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = SpeciesCategory::query()
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
            'admin/species-categories/Index',
            [
                'categories' => $categories,

                'filters' => [
                    'search' => $request->search,
                ],
            ]
        );
    }

    public function create()
    {
        return Inertia::render(
            'admin/species-categories/Create'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:species_categories,name',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        // Checkbox:
        // marcado = true
        // desmarcado = false
        $validated['is_active'] = $request->boolean(
            'is_active'
        );

        SpeciesCategory::create($validated);

        return redirect()
            ->route('admin.species-categories.index')
            ->with(
                'success',
                'Categoría creada correctamente.'
            );
    }

    public function edit(
        SpeciesCategory $speciesCategory
    ) {
        return Inertia::render(
            'admin/species-categories/Edit',
            [
                'category' => $speciesCategory,
            ]
        );
    }

    public function update(
        Request $request,
        SpeciesCategory $speciesCategory
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:species_categories,name,' .
                    $speciesCategory->id,
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        // Convierte correctamente el checkbox
        // a true o false.
        $validated['is_active'] = $request->boolean(
            'is_active'
        );

        $speciesCategory->update($validated);

        return redirect()
            ->route('admin.species-categories.index')
            ->with(
                'success',
                'Categoría actualizada correctamente.'
            );
    }

    public function destroy(
        SpeciesCategory $speciesCategory
    ) {
        // No permitir eliminar categorías que ya
        // tengan especies asociadas.
        if ($speciesCategory->species()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar esta categoría porque tiene especies asignadas.'
            );
        }

        $speciesCategory->delete();

        return redirect()
            ->route('admin.species-categories.index')
            ->with(
                'success',
                'Categoría eliminada correctamente.'
            );
    }
}