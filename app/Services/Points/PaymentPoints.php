<?php

namespace App\Services\Points;

use App\Enums\PointAction;
use App\Models\Donation;
use App\Models\PointRule;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\PointService;

/**
 * Puntos de las acciones con pago: compra de boletos y donaciones.
 *
 * Se otorgan cuando el pago queda confirmado. Hoy eso pasa en la misma
 * petición de la compra o donación; cuando exista el webhook del proveedor
 * de pagos, debe llamar a estos mismos métodos. Son idempotentes: una orden
 * o donación nunca da puntos dos veces. Llámalos dentro de la transacción
 * de la acción; nunca fallan por la regla (desactivada o en 0 = 0 puntos).
 */
class PaymentPoints
{
    public function __construct(
        private PointService $points,
    ) {}

    /**
     * Una vez por orden pagada, sin importar cuántos boletos tenga.
     * null si la orden no tiene usuario (venta en taquilla sin QR) o no
     * está pagada.
     */
    public function awardTicketOrder(TicketOrder $order): ?PointsAward
    {
        $user = $this->owner($order->user_id);

        if ($user === null || $order->status !== 'paid') {
            return null;
        }

        return $this->points->awardOnce(
            $user,
            $this->rule(PointAction::TicketPurchase),
            PointAction::TicketPurchase->movementDescription(),
            $order
        );
    }

    /**
     * Una vez por donación completada, solo si alcanza el monto mínimo
     * (config points.donation_min_amount). Bajo el mínimo devuelve 0 puntos
     * y la temporada lleva base_points null: esa donación no da puntos.
     */
    public function awardDonation(Donation $donation): ?PointsAward
    {
        $user = $this->owner($donation->user_id);

        if ($user === null || $donation->status !== 'completed') {
            return null;
        }

        $eligible = (float) $donation->amount >= (float) config('points.donation_min_amount');

        return $this->points->awardOnce(
            $user,
            $eligible ? $this->rule(PointAction::Donation) : null,
            PointAction::Donation->movementDescription(),
            $donation
        );
    }

    /**
     * Sin cargar la relación: la orden o donación se devuelve tal cual en la
     * respuesta y no debe ganar un campo `user`.
     */
    private function owner(?int $userId): ?User
    {
        return $userId ? User::query()->whereKey($userId)->first() : null;
    }

    private function rule(PointAction $action): ?PointRule
    {
        return PointRule::query()->where('type', $action->value)->first();
    }
}
