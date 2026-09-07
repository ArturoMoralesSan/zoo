<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZooZone\StoreZooZoneRequest;
use App\Http\Requests\Admin\ZooZone\UpdateZooZoneRequest;
use App\Models\ZooZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ZooZoneController extends Controller
{
    /**
     * Listado de zonas.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();

        $zones = ZooZone::query()
            ->withCount([
                'speciesLocations',
                'mapMarkers',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            })
            ->when(
                $status === 'active',
                fn ($query) => $query->where('is_active', true)
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where('is_active', false)
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/zoo-zones/Index', [
            'zones' => $zones,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Formulario para crear una zona.
     */
    public function create(): Response
    {
        return Inertia::render('admin/zoo-zones/Create');
    }

    /**
     * Guardar una nueva zona.
     */
    public function store(StoreZooZoneRequest $request): RedirectResponse
    {
        ZooZone::create($request->validated());

        return redirect()
            ->route('admin.zoo-zones.index')
            ->with('success', 'Zona creada correctamente.');
    }

    /**
     * Mostrar una zona.
     */
    public function show(ZooZone $zooZone): Response
    {
        $zooZone->loadCount([
            'speciesLocations',
            'mapMarkers',
        ]);

        return Inertia::render('admin/zoo-zones/Show', [
            'zone' => $zooZone,
        ]);
    }

    /**
     * Formulario para editar una zona.
     */
    public function edit(ZooZone $zooZone): Response
    {
        return Inertia::render('admin/zoo-zones/Edit', [
            'zone' => $zooZone,
        ]);
    }

    /**
     * Actualizar una zona.
     */
    public function update(
        UpdateZooZoneRequest $request,
        ZooZone $zooZone
    ): RedirectResponse {
        $zooZone->update($request->validated());

        return redirect()
            ->route('admin.zoo-zones.index')
            ->with('success', 'Zona actualizada correctamente.');
    }

    /**
     * Eliminar una zona.
     */
    public function destroy(ZooZone $zooZone): RedirectResponse
    {
        $hasSpeciesLocations = $zooZone
            ->speciesLocations()
            ->exists();

        $hasMapMarkers = $zooZone
            ->mapMarkers()
            ->exists();

        if ($hasSpeciesLocations || $hasMapMarkers) {
            return back()->with(
                'error',
                'No puedes eliminar esta zona porque tiene información relacionada. Puedes desactivarla en su lugar.'
            );
        }

        $zooZone->delete();

        return redirect()
            ->route('admin.zoo-zones.index')
            ->with('success', 'Zona eliminada correctamente.');
    }
}