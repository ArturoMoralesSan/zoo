<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DonationController extends Controller
{
    /**
     * Mostrar listado de donaciones.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search'));

        $donations = Donation::query()
            ->with([
                'user:id,name,email',
                'seller:id,name',
                'paymentMethod:id,name,code',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('seller', function ($query) use ($search) {
                            $query->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/donations/Index', [
            'donations' => $donations,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Mostrar formulario para crear una donación.
     */
    public function create(): Response
    {
        $paymentMethods = PaymentMethod::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
            ]);

        return Inertia::render('admin/donations/Create', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Registrar una donación.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],

            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id'),
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],

            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validar usuario
        |--------------------------------------------------------------------------
        */
        $user = User::query()->findOrFail(
            $validated['user_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Método de pago
        |--------------------------------------------------------------------------
        */
        $paymentMethod = PaymentMethod::query()->findOrFail(
            $validated['payment_method_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Referencia obligatoria para determinados métodos
        |--------------------------------------------------------------------------
        */
        $requiresReference = in_array(
            $paymentMethod->code,
            [
                'transfer',
                'mercadopago',
            ],
            true
        );

        if (
            $requiresReference &&
            blank($validated['reference'] ?? null)
        ) {
            return back()
                ->withErrors([
                    'reference' =>
                        'Este método de pago requiere una referencia.',
                ])
                ->withInput();
        }

        /*
        |--------------------------------------------------------------------------
        | Registrar donación
        |--------------------------------------------------------------------------
        |
        | seller_id se obtiene del usuario autenticado.
        |
        */
        $donation = Donation::create([
            'user_id' => $user->id,

            'seller_id' => Auth::id(),

            'payment_method_id' =>
                $validated['payment_method_id'],

            'amount' => $validated['amount'],

            'reference' =>
                $validated['reference'] ?? null,

            'status' => 'completed',
        ]);

        return redirect()
            ->route('admin.donations.show', $donation)
            ->with(
                'success',
                'Donación registrada correctamente.'
            );
    }

    /**
     * Mostrar una donación.
     */
    public function show(Donation $donation): Response
    {
        $donation->load([
            'user:id,name,email,qr_token',
            'seller:id,name,email',
            'paymentMethod:id,name,code',
        ]);

        return Inertia::render('admin/donations/Index', [
            'donation' => $donation,
        ]);
    }

    /**
     * Buscar usuario mediante QR.
     */
    public function userByQr(Request $request): JsonResponse    {
        $validated = $request->validate([
            'qr_token' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $user = User::query()
            ->with([
                'level:id,name',
            ])
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
                'qr_token' => $user->qr_token,
                'level' => $user->level
                    ? [
                        'id' => $user->level->id,
                        'name' => $user->level->name,
                    ]
                    : null,
            ],
        ]);
    }
}