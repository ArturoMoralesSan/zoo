<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ZooZone\StoreZooZoneRequest;
use App\Http\Requests\Admin\ZooZone\UpdateZooZoneRequest;
use App\Models\ZooZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ZooZoneController extends Controller
{
    /**
     * Listado de zonas.
     */
    public function index(Request $request): Response
    {
        $search = $request
            ->string('search')
            ->trim()
            ->toString();

        $status = $request
            ->string('status')
            ->toString();

        $zones = ZooZone::query()
            ->withCount([
                'speciesLocations',
                'mapMarkers',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'type',
                            'like',
                            "%{$search}%"
                        );
                });
            })
            ->when(
                $status === 'active',
                fn ($query) => $query->where(
                    'is_active',
                    true
                )
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where(
                    'is_active',
                    false
                )
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'admin/zoo-zones/Index',
            [
                'zones' => $zones,
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                ],
            ]
        );
    }

    /**
     * Formulario para crear una zona.
     */
    public function create(): Response
    {
        return Inertia::render(
            'admin/zoo-zones/Create'
        );
    }

    /**
     * Guardar una nueva zona.
     */
    public function store(
        StoreZooZoneRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        /**
         * Imagen del plano.
         *
         * Se utiliza el mismo mecanismo que
         * el avatar del ProfileController.
         */
        if ($request->hasFile('map_image')) {
            $mapImage = $request->file(
                'map_image'
            );

            if (
                $mapImage &&
                $mapImage->isValid()
            ) {
                $filename =
                    uniqid() .
                    '.' .
                    $mapImage->getClientOriginalExtension();

                $directory = storage_path(
                    'app/public/zones'
                );

                if (!is_dir($directory)) {
                    mkdir(
                        $directory,
                        0755,
                        true
                    );
                }

                $mapImage->move(
                    $directory,
                    $filename
                );

                $data['map_image'] =
                    'zones/' . $filename;
            }
        }

        ZooZone::create($data);

        return redirect()
            ->route(
                'admin.zoo-zones.index'
            )
            ->with(
                'success',
                'Zona creada correctamente.'
            );
    }

    /**
     * Mostrar una zona.
     */
    public function show(
        ZooZone $zooZone
    ): Response {
        $zooZone->loadCount([
            'speciesLocations',
            'mapMarkers',
        ]);

        return Inertia::render(
            'admin/zoo-zones/Show',
            [
                'zone' => $zooZone,
            ]
        );
    }

    /**
     * Formulario para editar una zona.
     */
    public function edit(
        ZooZone $zooZone
    ): Response {
        return Inertia::render(
            'admin/zoo-zones/Edit',
            [
                'zone' => $zooZone,
            ]
        );
    }

    /**
     * Actualizar una zona.
     */
    public function update(
        UpdateZooZoneRequest $request,
        ZooZone $zooZone
    ): RedirectResponse {
        $data = $request->validated();

        /**
         * Si se seleccionó un nuevo plano,
         * reemplazar el anterior.
         */
        if ($request->hasFile('map_image')) {
            $mapImage = $request->file(
                'map_image'
            );

            if (
                $mapImage &&
                $mapImage->isValid()
            ) {
                /**
                 * Eliminar plano anterior.
                 */
                if ($zooZone->map_image) {
                    Storage::disk('public')->delete(
                        $zooZone->map_image
                    );
                }

                /**
                 * Generar nombre único.
                 */
                $filename =
                    uniqid() .
                    '.' .
                    $mapImage->getClientOriginalExtension();

                /**
                 * Asegurar directorio.
                 */
                $directory = storage_path(
                    'app/public/zones'
                );

                if (!is_dir($directory)) {
                    mkdir(
                        $directory,
                        0755,
                        true
                    );
                }

                /**
                 * Mover archivo.
                 */
                $mapImage->move(
                    $directory,
                    $filename
                );

                /**
                 * Guardar nueva ruta.
                 */
                $data['map_image'] =
                    'zones/' . $filename;
            }
        } else {
            /**
             * No se seleccionó una imagen nueva.
             *
             * No debemos enviar map_image al update,
             * porque podría venir como null desde
             * $request->validated() y borrar la existente.
             */
            unset($data['map_image']);
        }

        $zooZone->update($data);

        return redirect()
            ->route(
                'admin.zoo-zones.index'
            )
            ->with(
                'success',
                'Zona actualizada correctamente.'
            );
    }

    /**
     * Eliminar una zona.
     */
    public function destroy(
        ZooZone $zooZone
    ): RedirectResponse {
        $hasSpeciesLocations = $zooZone
            ->speciesLocations()
            ->exists();

        $hasMapMarkers = $zooZone
            ->mapMarkers()
            ->exists();

        $hasMapPaths = $zooZone
            ->mapPaths()
            ->exists();

        if (
            $hasSpeciesLocations ||
            $hasMapMarkers ||
            $hasMapPaths
        ) {
            return back()->with(
                'error',
                'No puedes eliminar esta zona porque tiene información relacionada. Puedes desactivarla en su lugar.'
            );
        }

        /**
         * Eliminar plano físico.
         */
        if ($zooZone->map_image) {
            Storage::disk('public')->delete(
                $zooZone->map_image
            );
        }

        /**
         * Eliminar zona.
         */
        $zooZone->delete();

        return redirect()
            ->route(
                'admin.zoo-zones.index'
            )
            ->with(
                'success',
                'Zona eliminada correctamente.'
            );
    }
}