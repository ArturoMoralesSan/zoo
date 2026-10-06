<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationController extends Controller
{
    /**
     * Listar las donaciones del usuario autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $donations = Donation::query()
            ->with([
                'paymentMethod:id,name,code',
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->get([
                'id',
                'user_id',
                'payment_method_id',
                'amount',
                'reference',
                'status',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'donations' => $donations,
            ],
        ]);
    }

    /**
     * Mostrar una donación específica del usuario autenticado.
     */
    public function show(
        Request $request,
        Donation $donation
    ): JsonResponse {
        $user = $request->user();

        if ($donation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar esta donación.',
            ], 403);
        }

        $donation->load([
            'paymentMethod:id,name,code',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'donation' => $donation,
            ],
        ]);
    }

    /**
     * Registrar una donación desde la aplicación móvil.
     *
     * La aplicación solamente permite pago con tarjeta.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:99999999.99',
            ],
        ]);

        $user = $request->user();

        /**
         * Buscar el método de pago de tarjeta.
         */
        $paymentMethod = PaymentMethod::query()
            ->where('code', 'card')
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'El pago con tarjeta no está disponible actualmente.',
            ], 422);
        }

        /**
         * Crear la donación.
         *
         * seller_id queda NULL porque la donación
         * proviene directamente de la aplicación.
         */
        $donation = Donation::create([
            'user_id' => $user->id,
            'seller_id' => null,
            'payment_method_id' => $paymentMethod->id,
            'amount' => $validated['amount'],
            'reference' => null,
            'status' => 'completed',
        ]);

        $donation->load([
            'paymentMethod:id,name,code',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Donación registrada correctamente.',
            'data' => [
                'donation' => $donation,
            ],
        ], 201);
    }
}
