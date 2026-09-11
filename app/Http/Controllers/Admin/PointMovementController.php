<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointMovement;
use Inertia\Inertia;
use Inertia\Response;

class PointMovementController extends Controller
{
    public function index(): Response
    {
        $movements = PointMovement::query()
            ->with([
                'user:id,name,email',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render(
            'admin/point-movements/Index',
            [
                'movements' => $movements,
            ]
        );
    }
}