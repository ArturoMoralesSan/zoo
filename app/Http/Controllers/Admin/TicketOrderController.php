<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TicketOrderController extends Controller
{
    public function index(): Response
    {
        $orders = TicketOrder::query()
            ->with([
                'user:id,name,email',
                'seller:id,name',
                'items.ticketType:id,name',
                'payments.paymentMethod:id,name',
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'admin/ticket-orders/Index',
            [
                'orders' => $orders,
            ]
        );
    }

    public function create(): Response
    {
        $ticketTypes = TicketType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'price',
            ]);

        $paymentMethods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
            ]);

        return Inertia::render(
            'admin/ticket-orders/Create',
            [
                'ticketTypes' => $ticketTypes,
                'paymentMethods' => $paymentMethods,
            ]
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.ticket_type_id' => [
                'required',
                'integer',
                'exists:ticket_types,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'discount' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'payments' => [
                'required',
                'array',
                'min:1',
            ],

            'payments.*.payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id',
            ],

            'payments.*.amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payments.*.reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;

            $items = [];

            foreach ($validated['items'] as $item) {
                $ticketType = TicketType::query()
                    ->where('is_active', true)
                    ->findOrFail($item['ticket_type_id']);

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) $ticketType->price;

                $itemSubtotal = $unitPrice * $quantity;

                $subtotal += $itemSubtotal;

                $items[] = [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $discount = min(
                (float) ($validated['discount'] ?? 0),
                $subtotal
            );

            $total = $subtotal - $discount;

            /*
            * Validar los pagos.
            *
            * Los pagos distintos a efectivo no pueden exceder
            * el saldo que corresponde cubrir.
            *
            * El efectivo sí puede exceder el saldo y el excedente
            * se considera cambio.
            */
            $paymentMethods = \App\Models\PaymentMethod::query()
                ->whereIn(
                    'id',
                    collect($validated['payments'])
                        ->pluck('payment_method_id')
                        ->unique()
                )
                ->get()
                ->keyBy('id');

            $nonCashTotal = 0;
            $cashTotal = 0;

            foreach ($validated['payments'] as $payment) {
                $paymentMethod = $paymentMethods->get(
                    $payment['payment_method_id']
                );

                if (!$paymentMethod) {
                    abort(
                        422,
                        'El método de pago seleccionado no es válido.'
                    );
                }

                $amount = (float) $payment['amount'];

                if ($paymentMethod->code === 'cash') {
                    $cashTotal += $amount;
                } else {
                    $nonCashTotal += $amount;
                }
            }

            /*
            * Los métodos que no son efectivo deben cubrir como máximo
            * el total de la orden.
            */
            if ($nonCashTotal > $total) {
                abort(
                    422,
                    'Los pagos que no son en efectivo no pueden exceder el total de la orden.'
                );
            }

            /*
            * El efectivo debe cubrir el saldo restante.
            *
            * Si sobra efectivo, la diferencia será el cambio.
            */
            $remainingBeforeCash = max(
                $total - $nonCashTotal,
                0
            );

            if ($cashTotal < $remainingBeforeCash) {
                abort(
                    422,
                    'El monto recibido no cubre el total de la orden.'
                );
            }

            $change = max(
                $cashTotal - $remainingBeforeCash,
                0
            );

            $order = TicketOrder::create([
                'folio' => $this->generateFolio(),
                'user_id' => $validated['user_id'] ?? null,
                'seller_id' => $request->user()?->id,
                'source' => 'taquilla',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            foreach ($items as $item) {
                $orderItem = $order->items()->create($item);

                for ($i = 0; $i < $item['quantity']; $i++) {
                    $order->tickets()->create([
                        'ticket_order_item_id' => $orderItem->id,
                        'ticket_type_id' => $item['ticket_type_id'],
                        'qr_token' => (string) Str::uuid(),
                        'status' => 'active',
                    ]);
                }
            }

            foreach ($validated['payments'] as $payment) {
                $order->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            return $order;
        });

        return redirect()
            ->route('admin.ticket-orders.index')
            ->with(
                'success',
                "Orden {$order->folio} creada correctamente."
            );
    }

    public function show(TicketOrder $ticketOrder): Response
    {
        $ticketOrder->load([
            'user:id,name,email',
            'seller:id,name,email',
            'items.ticketType:id,name,price',
            'payments.paymentMethod:id,name,code',
            'tickets.ticketType:id,name',
            'tickets.validatedBy:id,name',
        ]);

        return Inertia::render(
            'admin/ticket-orders/Show',
            [
                'order' => $ticketOrder,
            ]
        );
    }



    // public function edit(TicketOrder $ticketOrder): Response
    // {
    //     $ticketOrder->load([
    //         'items.ticketType',
    //         'payments.paymentMethod',
    //         'user:id,name,email',
    //         'seller:id,name',
    //     ]);

    //     $ticketTypes = TicketType::query()
    //         ->where('is_active', true)
    //         ->orderBy('name')
    //         ->get([
    //             'id',
    //             'name',
    //             'price',
    //         ]);

    //     return Inertia::render(
    //         'admin/ticket-orders/Edit',
    //         [
    //             'order' => $ticketOrder,
    //             'ticketTypes' => $ticketTypes,
    //         ]
    //     );
    // }

    // public function update(
    //     Request $request,
    //     TicketOrder $ticketOrder
    // ): RedirectResponse {
    //     /*
    //      * Por seguridad, una orden pagada no debería modificarse
    //      * directamente.
    //      *
    //      * Posteriormente podemos implementar:
    //      * - cancelación
    //      * - reembolso
    //      * - devolución
    //      * - corrección administrativa
    //      */

    //     if ($ticketOrder->status === 'paid') {
    //         return back()->with(
    //             'error',
    //             'Una orden pagada no puede modificarse directamente.'
    //         );
    //     }

    //     return back()->with(
    //         'error',
    //         'La actualización de órdenes aún no está disponible.'
    //     );
    // }

    public function destroy(
        TicketOrder $ticketOrder
    ): RedirectResponse {
        if ($ticketOrder->status === 'paid') {
            return back()->with(
                'error',
                'Una orden pagada no puede eliminarse.'
            );
        }

        $ticketOrder->delete();

        return redirect()
            ->route('admin.ticket-orders.index')
            ->with(
                'success',
                'Orden eliminada correctamente.'
            );
    }

    private function generateFolio(): string
    {
        do {
            $folio = 'ZOO-' . now()->format('Ymd') . '-' .
                str_pad(
                    (string) random_int(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                );
        } while (
            TicketOrder::query()
                ->where('folio', $folio)
                ->exists()
        );

        return $folio;
    }
}