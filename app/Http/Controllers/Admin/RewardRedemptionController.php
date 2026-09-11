<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class RewardRedemptionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $redemptions = RewardRedemption::query()
            ->with([
                'user:id,name,email,points,level_id',
                'user.level:id,name',
                'reward:id,name,points',
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where(
                            'folio',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'status',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            function ($query) use ($search) {
                                $query
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'reward',
                            function ($query) use ($search) {
                                $query->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                );
                            }
                        );
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'admin/reward-redemptions/Index',
            [
                'redemptions' => $redemptions,
                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    public function create(): Response
    {
        $users = User::query()
            ->with('level:id,name')
            ->select([
                'id',
                'name',
                'email',
                'points',
                'level_id',
                'qr_token',
            ])
            ->orderBy('name')
            ->get();

        $rewards = Reward::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'description',
                'points',
                'stock',
                'image',
            ]);

        return Inertia::render(
            'admin/reward-redemptions/Create',
            [
                'users' => $users,
                'rewards' => $rewards,
            ]
        );
    }

    /**
     * Busca un usuario mediante su QR permanente.
     */
    public function userByQr(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'qr_token' => [
                'required',
                'string',
                'size:64',
            ],
        ]);

        $user = User::query()
            ->with('level:id,name')
            ->where(
                'qr_token',
                $validated['qr_token']
            )
            ->first();

        if (!$user) {
            return response()->json([
                'message' =>
                    'No se encontró ningún usuario con ese código QR.',
            ], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'points' => $user->points,
                'level' => $user->level
                    ? [
                        'id' => $user->level->id,
                        'name' => $user->level->name,
                    ]
                    : null,
            ],
        ]);
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'reward_id' => [
                'required',
                'integer',
                'exists:rewards,id',
            ],
        ]);

        try {
            DB::transaction(
                function () use ($validated) {
                    $user = User::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $validated['user_id']
                        );

                    $reward = Reward::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $validated['reward_id']
                        );

                    if (!$reward->is_active) {
                        throw new RuntimeException(
                            'La recompensa no está disponible.'
                        );
                    }

                    if ($reward->stock <= 0) {
                        throw new RuntimeException(
                            'La recompensa está agotada.'
                        );
                    }

                    if (
                        $user->points <
                        $reward->points
                    ) {
                        throw new RuntimeException(
                            'El usuario no tiene suficientes puntos para esta recompensa.'
                        );
                    }

                    $folio = $this->generateFolio();

                    RewardRedemption::create([
                        'user_id' => $user->id,
                        'reward_id' => $reward->id,

                        /*
                         * Guardamos el requisito de puntos
                         * vigente en el momento del canje.
                         *
                         * NO se descuentan del usuario.
                         */
                        'points' => $reward->points,

                        'folio' => $folio,
                        'status' => 'completed',
                        'redeemed_at' => now(),
                    ]);

                    $reward->decrement('stock');
                }
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        }

        return redirect()
            ->route(
                'admin.reward-redemptions.index'
            )
            ->with(
                'success',
                'Recompensa canjeada correctamente.'
            );
    }

    private function generateFolio(): string
    {
        do {
            $folio = 'ZOO-RDM-'
                . now()->format('Ymd')
                . '-'
                . strtoupper(
                    Str::random(5)
                );
        } while (
            RewardRedemption::query()
                ->where('folio', $folio)
                ->exists()
        );

        return $folio;
    }
}