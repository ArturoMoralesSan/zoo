<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RewardController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $rewards = Reward::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        );
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'admin/rewards/Index',
            [
                'rewards' => $rewards,
                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'admin/rewards/Create'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'points' => [
                'required',
                'integer',
                'gt:0',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'image' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        Reward::create($validated);

        return redirect()
            ->route('admin.rewards.index')
            ->with(
                'success',
                'Recompensa creada correctamente.'
            );
    }

    public function edit(Reward $reward): Response
    {
        return Inertia::render(
            'admin/rewards/Edit',
            [
                'reward' => $reward,
            ]
        );
    }

    public function update(
        Request $request,
        Reward $reward
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'points' => [
                'required',
                'integer',
                'gt:0',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'image' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $reward->update($validated);

        return redirect()
            ->route('admin.rewards.index')
            ->with(
                'success',
                'Recompensa actualizada correctamente.'
            );
    }

    public function destroy(
        Reward $reward
    ): RedirectResponse {
        if ($reward->redemptions()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar una recompensa que ya tiene canjes registrados.'
            );
        }

        $reward->delete();

        return redirect()
            ->route('admin.rewards.index')
            ->with(
                'success',
                'Recompensa eliminada correctamente.'
            );
    }
}