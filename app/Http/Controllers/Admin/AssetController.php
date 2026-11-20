<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AssetInventory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página "Recursos" (Administración): optimización de imágenes y modelos 3D.
 */
class AssetController extends Controller
{
    public function index(AssetInventory $inventory): Response
    {
        return Inertia::render('admin/assets/Index', [
            'summary' => fn () => $inventory->summary(),
            'canOptimize' => (bool) request()->user()?->can('assets.optimize'),
        ]);
    }
}
