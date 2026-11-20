<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Points\PointsAwardResource;
use App\Models\Donation;
use App\Models\PaymentMethod;
use App\Services\Points\PaymentPoints;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @tags Donaciones
 */
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
     * Donar
     *
     * Registra una donación desde la aplicación móvil. La aplicación solamente permite pago
     * con tarjeta, y por ahora el pago se considera aprobado en esta misma petición.
     *
     * Al confirmarse el pago otorga una vez los puntos de la regla "Donación" (`donation`), solo
     * si el monto es de al menos el mínimo configurado (50 MXN por defecto), y los devuelve en
     * `data.points` con el mismo formato que al ganar un duelo. Bajo el mínimo, con la regla
     * desactivada o puesta en 0 por la temporada, la donación se registra igual con
     * `awarded: 0`. Después de mostrarlos, la app debe revalidar `GET /profile`.
     */
    public function store(Request $request, PaymentPoints $paymentPoints): JsonResponse
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
        [$donation, $points] = DB::transaction(function () use ($user, $paymentMethod, $validated, $paymentPoints) {
            $donation = Donation::create([
                'user_id' => $user->id,
                'seller_id' => null,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $validated['amount'],
                'reference' => null,
                'status' => 'completed',
            ]);

            // El pago ya quedó confirmado. Los puntos nunca impiden la donación.
            return [$donation, $paymentPoints->awardDonation($donation)];
        });

        $donation->load([
            'paymentMethod:id,name,code',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Donación registrada correctamente.',
            'data' => [
                'donation' => $donation,

                /**
                 * Puntos ganados con esta donación, con el mismo formato que `data.points` al
                 * ganar un duelo. `awarded` es `0` si el monto no alcanza el mínimo.
                 *
                 * @var PointsAwardResource
                 */
                'points' => $points ? new PointsAwardResource($points) : null,
            ],
        ], 201);
    }
}
