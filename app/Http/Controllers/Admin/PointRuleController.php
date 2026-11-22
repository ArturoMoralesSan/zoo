<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PointRuleController extends Controller
{
    public function index(PointService $points): Response
    {
        $rules = PointRule::query()
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Una regla solo da puntos si una acción del código la otorga o si
        // está asignada a una rareza; el panel lo muestra por regla, salvo
        // en modo anterior (APP_POINTS_RULES_LEGACY=1), donde usage es null.
        $usage = $points->tracksRuleUsage() ? $points->ruleUsage($rules->items()) : [];
        $rules->through(fn (PointRule $rule) => [
            ...$rule->toArray(),
            'usage' => $usage[$rule->id] ?? null,
        ]);

        return Inertia::render(
            'admin/point-rules/Index',
            [
                'rules' => $rules,

                // Con un modo activo, los puntos otorgados pueden ser otros.
                'activeMode' => PointMode::active()?->only(['id', 'name']),
            ]
        );
    }

    public function create(PointService $points): Response
    {
        return Inertia::render(
            'admin/point-rules/Create',
            [
                // Aviso de que una regla nueva no da puntos por sí sola.
                'showUsageNotice' => $points->tracksRuleUsage(),
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'max:100',
                'unique:point_rules,type',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'points' => [
                'required',
                'integer',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        PointRule::create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'points' => $validated['points'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.point-rules.index')
            ->with(
                'success',
                'Regla de puntos creada correctamente.'
            );
    }

    public function show(PointRule $pointRule): Response
    {
        return Inertia::render(
            'admin/point-rules/Show',
            [
                'rule' => $pointRule,
            ]
        );
    }

    public function edit(PointRule $pointRule): Response
    {
        return Inertia::render(
            'admin/point-rules/Edit',
            [
                'rule' => $pointRule,
            ]
        );
    }

    public function update(
        Request $request,
        PointRule $pointRule
    ): RedirectResponse {
        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                'max:100',
                'unique:point_rules,type,'.$pointRule->id,
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'points' => [
                'required',
                'integer',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
        ]);

        $pointRule->update([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'points' => $validated['points'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        return redirect()
            ->route('admin.point-rules.index')
            ->with(
                'success',
                'Regla de puntos actualizada correctamente.'
            );
    }

    public function destroy(
        PointRule $pointRule
    ): RedirectResponse {
        $pointRule->delete();

        return redirect()
            ->route('admin.point-rules.index')
            ->with(
                'success',
                'Regla de puntos eliminada correctamente.'
            );
    }
}
