<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\PaymentMethod;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function __construct(
        private PointService $pointService
    ) {
    }

    /**
     * Tipos de boletos disponibles para la aplicación.
     */
    public function types(): JsonResponse
    {
        $ticketTypes = TicketType::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'price',
                'description',
            ]);

        return response()->json([
            'data' => $ticketTypes,
        ]);
    }

    /**
     * Crear y aprobar inmediatamente una compra desde la app.
     *
     * Por ahora el pago se considera aprobado automáticamente.
     * Posteriormente aquí se integrará Mercado Pago.
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],

            'items.*.ticket_type_id' => [
                'required',
                'integer',
                'exists:ticket_types,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:20',
            ],
        ]);

        $order = DB::transaction(function () use ($validated, $request) {

            /*
            |--------------------------------------------------------------------------
            | Tipos de boleto
            |--------------------------------------------------------------------------
            |
            | Los precios SIEMPRE salen de la base de datos.
            | La app nunca puede mandar el precio.
            |
            */

            $ticketTypeIds = collect($validated['items'])
                ->pluck('ticket_type_id')
                ->unique()
                ->values();

            $ticketTypes = TicketType::query()
                ->whereIn('id', $ticketTypeIds)
                ->where('is_active', true)
                ->get()
                ->keyBy('id');

            if ($ticketTypes->count() !== $ticketTypeIds->count()) {
                abort(
                    422,
                    'Uno o más tipos de boleto no están disponibles.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Calcular subtotal
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            foreach ($validated['items'] as $item) {
                $ticketType = $ticketTypes->get(
                    $item['ticket_type_id']
                );

                $subtotal +=
                    ((float) $ticketType->price)
                    * ((int) $item['quantity']);
            }

            /*
            |--------------------------------------------------------------------------
            | Método de pago
            |--------------------------------------------------------------------------
            |
            | Por ahora buscamos el método activo llamado Tarjeta.
            | Posteriormente Mercado Pago utilizará este mismo registro.
            |
            */

            $paymentMethod = PaymentMethod::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query
                        ->where('name', 'Tarjeta')
                        ->orWhere('name', 'like', '%Tarjeta%');
                })
                ->orderBy('sort_order')
                ->first();

            if (!$paymentMethod) {
                abort(
                    422,
                    'No existe un método de pago activo para tarjeta.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Crear orden
            |--------------------------------------------------------------------------
            |
            | De momento se aprueba inmediatamente.
            |
            */

            $order = TicketOrder::create([
                'folio' => $this->generateFolio(),

                'user_id' => $request->user()->id,

                'seller_id' => null,

                'source' => 'app',

                'subtotal' => $subtotal,

                'discount' => 0,

                'total' => $subtotal,

                'status' => 'paid',

                'paid_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Crear items
            |--------------------------------------------------------------------------
            */

            foreach ($validated['items'] as $item) {
                $ticketType = $ticketTypes->get(
                    $item['ticket_type_id']
                );

                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $ticketType->price;

                $orderItem = $order->items()->create([
                    'ticket_type_id' => $ticketType->id,

                    'quantity' => $quantity,

                    'unit_price' => $unitPrice,

                    'subtotal' => $unitPrice * $quantity,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Generar boletos y QR
                |--------------------------------------------------------------------------
                */

                for ($i = 0; $i < $quantity; $i++) {
                    Ticket::create([
                        'ticket_order_id' => $order->id,

                        'ticket_order_item_id' => $orderItem->id,

                        'ticket_type_id' => $ticketType->id,

                        'qr_token' => (string) Str::uuid(),

                        'status' => 'active',

                        'used_at' => null,

                        'validated_by' => null,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Registrar pago
            |--------------------------------------------------------------------------
            */

            $order->payments()->create([
                'payment_method_id' => $paymentMethod->id,

                'amount' => $subtotal,

                'reference' => 'APP-SIMULATED-' . Str::upper(
                    Str::random(12)
                ),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Puntos
            |--------------------------------------------------------------------------
            */

            $this->pointService->add(
                $request->user(),
                'ticket_purchase',
                'Compra de boleto',
                $order
            );

            return $order;
        });

        /*
        |--------------------------------------------------------------------------
        | Cargar información para devolverla a la app
        |--------------------------------------------------------------------------
        */

        $order->load([
            'items.ticketType',
            'payments.paymentMethod',
            'tickets.ticketType',
        ]);

        return response()->json([
            'message' => 'Compra realizada correctamente.',

            'data' => [
                'id' => $order->id,

                'folio' => $order->folio,

                'source' => $order->source,

                'status' => $order->status,

                'subtotal' => $order->subtotal,

                'discount' => $order->discount,

                'total' => $order->total,

                'paid_at' => $order->paid_at,

                'payment' => [
                    'method' => $order->payments->first()?->paymentMethod?->name,

                    'reference' => $order->payments->first()?->reference,
                ],

                'items' => $order->items
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,

                            'ticket_type_id' => $item->ticket_type_id,

                            'name' => $item->ticketType?->name,

                            'quantity' => $item->quantity,

                            'unit_price' => $item->unit_price,

                            'subtotal' => $item->subtotal,
                        ];
                    })
                    ->values(),

                'tickets' => $order->tickets
                    ->map(function ($ticket) {
                        return [
                            'id' => $ticket->id,

                            'ticket_type_id' => $ticket->ticket_type_id,

                            'name' => $ticket->ticketType?->name,

                            'qr_token' => $ticket->qr_token,

                            'status' => $ticket->status,

                            'used_at' => $ticket->used_at,
                        ];
                    })
                    ->values(),
            ],
        ], 201);
    }

    /**
     * Mostrar una orden perteneciente al usuario autenticado.
     */
    public function showOrder(
        Request $request,
        TicketOrder $ticketOrder
    ): JsonResponse {
        if ($ticketOrder->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No tienes permiso para consultar esta orden.',
            ], 403);
        }

        $ticketOrder->load([
            'items.ticketType',
            'payments.paymentMethod',
            'tickets.ticketType',
        ]);

        return response()->json([
            'data' => [
                'id' => $ticketOrder->id,

                'folio' => $ticketOrder->folio,

                'source' => $ticketOrder->source,

                'status' => $ticketOrder->status,

                'subtotal' => $ticketOrder->subtotal,

                'discount' => $ticketOrder->discount,

                'total' => $ticketOrder->total,

                'paid_at' => $ticketOrder->paid_at,

                'items' => $ticketOrder->items
                    ->map(function ($item) {
                        return [
                            'id' => $item->id,

                            'ticket_type_id' => $item->ticket_type_id,

                            'name' => $item->ticketType?->name,

                            'quantity' => $item->quantity,

                            'unit_price' => $item->unit_price,

                            'subtotal' => $item->subtotal,
                        ];
                    })
                    ->values(),

                'tickets' => $ticketOrder->tickets
                    ->map(function ($ticket) {
                        return [
                            'id' => $ticket->id,

                            'ticket_type_id' => $ticket->ticket_type_id,

                            'name' => $ticket->ticketType?->name,

                            'qr_token' => $ticket->qr_token,

                            'status' => $ticket->status,

                            'used_at' => $ticket->used_at,
                        ];
                    })
                    ->values(),
            ],
        ]);
    }


    /**
     * Obtener todos los boletos del usuario autenticado,
     * agrupados posteriormente por compra en la aplicación.
     */
    public function myTickets(Request $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->whereHas('order', function ($query) use ($request) {
                $query->where(
                    'user_id',
                    $request->user()->id
                );
            })
            ->with([
                'ticketType',
                'order:id,user_id,folio,status,subtotal,discount,total,paid_at,created_at',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $tickets
                ->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,

                        'qr_token' => $ticket->qr_token,

                        'status' => $ticket->status,

                        'used_at' => $ticket->used_at,

                        'ticket_type' => [
                            'id' => $ticket->ticketType?->id,

                            'name' => $ticket->ticketType?->name,

                            'price' => $ticket->ticketType?->price,
                        ],

                        'order' => [
                            'id' => $ticket->order?->id,

                            'folio' => $ticket->order?->folio,

                            'status' => $ticket->order?->status,

                            'subtotal' => $ticket->order?->subtotal,

                            'discount' => $ticket->order?->discount,

                            'total' => $ticket->order?->total,

                            'paid_at' => $ticket->order?->paid_at,

                            'created_at' => $ticket->order?->created_at,
                        ],
                    ];
                })
                ->values(),
        ]);
    }


    /**
     * Generar folio único.
     */
    private function generateFolio(): string
    {
        do {
            $folio = 'ZOO-'
                . now()->format('Ymd')
                . '-'
                . str_pad(
                    (string) random_int(1, 99999),
                    5,
                    '0',
                    STR_PAD_LEFT
                );
        } while (
            TicketOrder::where('folio', $folio)->exists()
        );

        return $folio;
    }
}