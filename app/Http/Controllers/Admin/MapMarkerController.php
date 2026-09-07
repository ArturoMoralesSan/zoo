<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MapMarker\StoreMapMarkerRequest;
use App\Http\Requests\Admin\MapMarker\UpdateMapMarkerRequest;
use App\Models\MapMarker;
use App\Models\ZooZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapMarkerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $zoneId = $request->string('zone_id')->toString();

        $markers = MapMarker::query()
            ->with('zone')
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
            ->when(
                $zoneId !== '',
                fn ($query) => $query->where('zone_id', $zoneId)
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/map-markers/Index', [
            'markers' => $markers,

            'zones' => ZooZone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'filters' => [
                'search' => $search,
                'status' => $status,
                'zone_id' => $zoneId,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/map-markers/Create', [
            'zones' => ZooZone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'geometry',
                ]),
        ]);
    }

    public function store(
        StoreMapMarkerRequest $request
    ): RedirectResponse {
        MapMarker::create($request->validated());

        return redirect()
            ->route('admin.map-markers.index')
            ->with('success', 'Marker creado correctamente.');
    }

    public function show(MapMarker $mapMarker): Response
    {
        $mapMarker->load('zone');

        return Inertia::render('admin/map-markers/Show', [
            'marker' => $mapMarker,
        ]);
    }

    public function edit(MapMarker $mapMarker): Response
    {
        $mapMarker->load('zone');

        return Inertia::render('admin/map-markers/Edit', [
            'marker' => $mapMarker,

            'zones' => ZooZone::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'geometry',
                ]),
        ]);
    }

    public function update(
        UpdateMapMarkerRequest $request,
        MapMarker $mapMarker
    ): RedirectResponse {
        $mapMarker->update($request->validated());

        return redirect()
            ->route('admin.map-markers.index')
            ->with('success', 'Marker actualizado correctamente.');
    }

    public function destroy(
        MapMarker $mapMarker
    ): RedirectResponse {
        $mapMarker->delete();

        return redirect()
            ->route('admin.map-markers.index')
            ->with('success', 'Marker eliminado correctamente.');
    }
}