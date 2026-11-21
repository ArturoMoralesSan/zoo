<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PointMode\StorePointModeRequest;
use App\Http\Requests\Admin\PointMode\UpdatePointModeRequest;
use App\Models\PointMode;
use App\Models\PointRule;
use App\Services\PointService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Modos de puntos (temporadas o eventos). Cada modo cambia los puntos de
 * algunas reglas; se activan a mano y solo uno puede estar activo.
 */
class PointModeController extends Controller
{
    private const FIELDS = ['name', 'description', 'is_public', 'public_title', 'public_message', 'icon', 'color'];

    public function __construct(
        private PointService $points
    ) {}

    public function index(): Response
    {
        $modes = PointMode::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (PointMode $mode) => $this->present($mode));

        return Inertia::render('admin/point-modes/Index', [
            'modes' => $modes,
            'rules' => $this->rules(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/point-modes/Create', [
            'rules' => $this->rules(),
        ]);
    }

    public function store(StorePointModeRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $mode = PointMode::create($request->safe()->only(self::FIELDS));
            $mode->rules()->sync($request->overrides());
        });

        $this->toast('Modo de puntos creado. Actívalo cuando quieras usarlo.');

        return redirect()->route('admin.point-modes.index');
    }

    public function edit(PointMode $pointMode): Response
    {
        return Inertia::render('admin/point-modes/Edit', [
            'mode' => $this->present($pointMode),
            'rules' => $this->rules(),
        ]);
    }

    public function update(UpdatePointModeRequest $request, PointMode $pointMode): RedirectResponse
    {
        DB::transaction(function () use ($request, $pointMode) {
            $pointMode->update($request->safe()->only(self::FIELDS));
            $pointMode->rules()->sync($request->overrides());
        });

        $this->toast('Modo de puntos actualizado.');

        return redirect()->route('admin.point-modes.index');
    }

    public function destroy(PointMode $pointMode): RedirectResponse
    {
        // Borrar el modo activo cambiaría los puntos sin que nadie lo decida.
        if ($pointMode->is_active) {
            $this->toast('No puedes eliminar el modo activo. Activa otro o vuelve a los puntos base primero.', 'error');

            return back();
        }

        $pointMode->delete();

        $this->toast('Modo de puntos eliminado.');

        return redirect()->route('admin.point-modes.index');
    }

    /**
     * Activar este modo; el que estaba activo se desactiva.
     */
    public function activate(PointMode $pointMode): RedirectResponse
    {
        DB::transaction(function () use ($pointMode) {
            PointMode::query()->lockForUpdate()->get();

            PointMode::query()
                ->whereKeyNot($pointMode->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $pointMode->update(['is_active' => true]);
        });

        $this->toast("Modo \"{$pointMode->name}\" activado. Los puntos nuevos ya usan este modo.");

        return back();
    }

    /**
     * Volver a los puntos base (ningún modo activo).
     */
    public function deactivate(): RedirectResponse
    {
        PointMode::query()
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $this->toast('Se volvió a los puntos base de cada regla.');

        return back();
    }

    /**
     * Copiar un modo (inactivo) para crear otra temporada parecida.
     */
    public function duplicate(PointMode $pointMode): RedirectResponse
    {
        $copy = DB::transaction(function () use ($pointMode) {
            $copy = PointMode::create([
                'name' => $this->copyName($pointMode->name),
                'description' => $pointMode->description,
                'is_public' => $pointMode->is_public,
                'public_title' => $pointMode->public_title,
                'public_message' => $pointMode->public_message,
                'icon' => $pointMode->icon,
                'color' => $pointMode->color,
            ]);

            $copy->rules()->sync(
                array_map(
                    fn (int $points) => ['points' => $points],
                    $pointMode->overridePoints()
                )
            );

            return $copy;
        });

        $this->toast('Modo duplicado. Ajusta los puntos y guárdalo.');

        return redirect()->route('admin.point-modes.edit', $copy);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PointMode $mode): array
    {
        $overrides = $mode->overridePoints();

        return [
            'id' => $mode->id,
            'name' => $mode->name,
            'description' => $mode->description,
            'is_active' => $mode->is_active,
            'is_public' => $mode->is_public,
            'public_title' => $mode->public_title,
            'public_message' => $mode->public_message,
            'icon' => $mode->icon,
            'color' => $mode->color,
            'overrides' => array_map(
                fn (int $ruleId, int $points) => [
                    'point_rule_id' => $ruleId,
                    'points' => $points,
                ],
                array_keys($overrides),
                $overrides
            ),
            'updated_at' => $mode->updated_at?->toJSON(),
        ];
    }

    /**
     * Con `in_use`: cambiar los puntos de una regla que ninguna acción otorga
     * ni ninguna rareza usa no tiene efecto, y el formulario lo avisa.
     *
     * @return Collection<int, PointRule>
     */
    private function rules(): Collection
    {
        $rules = PointRule::query()
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'points', 'is_active']);

        $usage = $this->points->ruleUsage($rules);

        return $rules->each(
            fn (PointRule $rule) => $rule->setAttribute('in_use', $usage[$rule->id]['in_use'])
        );
    }

    private function copyName(string $name): string
    {
        $base = "Copia de {$name}";
        $candidate = $base;
        $n = 2;

        while (PointMode::query()->where('name', $candidate)->exists()) {
            $candidate = "{$base} ({$n})";
            $n++;
        }

        return $candidate;
    }

    private function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}
