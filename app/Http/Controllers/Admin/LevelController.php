<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LevelController extends Controller
{
    public function index(Request $request)
    {
        $levels = Level::query()
            ->withCount('users')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('min_points')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/levels/Index', [
            'levels' => $levels,
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/levels/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_points' => ['required', 'integer', 'min:0'],
            'max_points' => ['nullable', 'integer', 'gte:min_points'],
            'description' => ['nullable', 'string'],
        ]);

        Level::create($validated);

        return redirect()
            ->route('admin.levels.index')
            ->with('success', 'Nivel creado correctamente.');
    }

    public function show(Level $level)
    {
        return Inertia::render('admin/levels/Show', [
            'level' => $level,
        ]);
    }

    public function edit(Level $level)
    {
        return Inertia::render('admin/levels/Edit', [
            'level' => $level,
        ]);
    }

    public function update(Request $request, Level $level)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_points' => ['required', 'integer', 'min:0'],
            'max_points' => ['nullable', 'integer', 'gte:min_points'],
            'description' => ['nullable', 'string'],
        ]);

        $level->update($validated);

        return redirect()
            ->route('admin.levels.index')
            ->with('success', 'Nivel actualizado correctamente.');
    }

    public function destroy(Level $level)
    {
        if ($level->users()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar este nivel porque tiene usuarios asignados.'
            );
        }

        $level->delete();

        return redirect()
            ->route('admin.levels.index')
            ->with('success', 'Nivel eliminado correctamente.');
    }
}