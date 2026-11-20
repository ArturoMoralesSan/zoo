<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AssetInventory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Página "Recursos" (Administración). Pestañas: Optimización (assets.view)
 * y Descargas (assets.export o assets.import, ver CardTransferController).
 */
class AssetController extends Controller
{
    public function index(AssetInventory $inventory): Response
    {
        $user = request()->user();
        $canView = (bool) $user?->can('assets.view');

        return Inertia::render('admin/assets/Index', [
            'summary' => fn () => $canView ? $inventory->summary() : null,
            'canView' => $canView,
            'canOptimize' => (bool) $user?->can('assets.optimize'),
            'canTransfer' => (bool) $user?->canAny(['assets.export', 'assets.import']),
            // Pestaña inicial (?tab=downloads desde el aviso en tarjetas).
            'initialTab' => request()->query('tab') === 'downloads' ? 'downloads' : 'optimization',
        ]);
    }
}
