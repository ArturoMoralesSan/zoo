<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Fechas del filtro
        |--------------------------------------------------------------------------
        */

        $from = $request->input(
            'from',
            now()->startOfMonth()->format('Y-m-d')
        );

        $to = $request->input(
            'to',
            now()->format('Y-m-d')
        );

        $request->validate([
            'from' => [
                'nullable',
                'date',
            ],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],
        ]);

        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();

        /*
        |--------------------------------------------------------------------------
        | Ventas del periodo seleccionado
        |--------------------------------------------------------------------------
        */

        $ordersQuery = TicketOrder::query()
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $fromDate,
                    $toDate,
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | KPIs DEL DÍA ACTUAL
        |--------------------------------------------------------------------------
        */

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $todayOrdersQuery = TicketOrder::query()
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $todayStart,
                    $todayEnd,
                ]
            );

        $todaySales = (clone $todayOrdersQuery)
            ->count('ticket_orders.id');

        $todayIncome = (clone $todayOrdersQuery)
            ->sum('ticket_orders.total');

        $todayTicketsSold = DB::table(
            'ticket_order_items'
        )
            ->join(
                'ticket_orders',
                'ticket_orders.id',
                '=',
                'ticket_order_items.ticket_order_id'
            )
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $todayStart,
                    $todayEnd,
                ]
            )
            ->sum(
                'ticket_order_items.quantity'
            );

        $todayAverageTicket = $todaySales > 0
            ? round(
                (float) $todayIncome /
                    $todaySales,
                2
            )
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Donaciones de HOY
        |--------------------------------------------------------------------------
        */

        $todayDonationsQuery = Donation::query()
            ->where(
                'donations.status',
                'completed'
            )
            ->whereBetween(
                'donations.created_at',
                [
                    $todayStart,
                    $todayEnd,
                ]
            );

        $todayDonations = (clone $todayDonationsQuery)
            ->count('donations.id');

        $todayDonationsAmount = (clone $todayDonationsQuery)
            ->sum('donations.amount');

        /*
        |--------------------------------------------------------------------------
        | Ventas por día
        |--------------------------------------------------------------------------
        */

        $salesByDay = (clone $ordersQuery)
            ->select([
                DB::raw(
                    'DATE(ticket_orders.created_at) as date'
                ),
                DB::raw(
                    'COUNT(ticket_orders.id) as sales'
                ),
                DB::raw(
                    'SUM(ticket_orders.total) as amount'
                ),
            ])
            ->groupBy(
                DB::raw(
                    'DATE(ticket_orders.created_at)'
                )
            )
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'sales' => (int) $row->sales,
                    'amount' => (float) $row->amount,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Boletos vendidos por tipo
        |--------------------------------------------------------------------------
        */

        $ticketsSold = DB::table(
            'ticket_order_items'
        )
            ->join(
                'ticket_orders',
                'ticket_orders.id',
                '=',
                'ticket_order_items.ticket_order_id'
            )
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $fromDate,
                    $toDate,
                ]
            )
            ->sum(
                'ticket_order_items.quantity'
            );

        $ticketsByType = DB::table(
            'ticket_order_items'
        )
            ->join(
                'ticket_orders',
                'ticket_orders.id',
                '=',
                'ticket_order_items.ticket_order_id'
            )
            ->join(
                'ticket_types',
                'ticket_types.id',
                '=',
                'ticket_order_items.ticket_type_id'
            )
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $fromDate,
                    $toDate,
                ]
            )
            ->select([
                'ticket_types.id',
                'ticket_types.name',
                DB::raw(
                    'SUM(ticket_order_items.quantity) as quantity'
                ),
                DB::raw(
                    'SUM(ticket_order_items.subtotal) as amount'
                ),
            ])
            ->groupBy([
                'ticket_types.id',
                'ticket_types.name',
            ])
            ->orderByDesc('quantity')
            ->get()
            ->map(function ($ticket) use ($ticketsSold) {
                return [
                    'id' => (int) $ticket->id,
                    'name' => $ticket->name,
                    'quantity' => (int) $ticket->quantity,
                    'amount' => (float) $ticket->amount,
                    'percentage' => $ticketsSold > 0
                        ? round(
                            (
                                $ticket->quantity /
                                $ticketsSold
                            ) * 100,
                            2
                        )
                        : 0,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Métodos de pago
        |--------------------------------------------------------------------------
        */

        $salesTotal = (clone $ordersQuery)
            ->count('ticket_orders.id');

        $incomeTotal = (clone $ordersQuery)
            ->sum('ticket_orders.total');

        $paymentMethods = DB::table(
            'ticket_order_payments'
        )
            ->join(
                'ticket_orders',
                'ticket_orders.id',
                '=',
                'ticket_order_payments.ticket_order_id'
            )
            ->join(
                'payment_methods',
                'payment_methods.id',
                '=',
                'ticket_order_payments.payment_method_id'
            )
            ->where(
                'ticket_orders.status',
                'paid'
            )
            ->whereBetween(
                'ticket_orders.created_at',
                [
                    $fromDate,
                    $toDate,
                ]
            )
            ->select([
                'payment_methods.id',
                'payment_methods.name',
                'payment_methods.code',
                DB::raw(
                    'COUNT(DISTINCT ticket_orders.id) as sales'
                ),
                DB::raw(
                    'SUM(ticket_order_payments.amount) as amount'
                ),
            ])
            ->groupBy([
                'payment_methods.id',
                'payment_methods.name',
                'payment_methods.code',
            ])
            ->orderByDesc('sales')
            ->get()
            ->map(function ($method) use ($salesTotal) {
                return [
                    'id' => (int) $method->id,
                    'name' => $method->name,
                    'code' => $method->code,
                    'sales' => (int) $method->sales,
                    'amount' => (float) $method->amount,
                    'percentage' => $salesTotal > 0
                        ? round(
                            (
                                $method->sales /
                                $salesTotal
                            ) * 100,
                            2
                        )
                        : 0,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Donaciones por día
        |--------------------------------------------------------------------------
        */

        $donationsQuery = Donation::query()
            ->where(
                'donations.status',
                'completed'
            )
            ->whereBetween(
                'donations.created_at',
                [
                    $fromDate,
                    $toDate,
                ]
            );

        $donationsByDay = (clone $donationsQuery)
            ->select([
                DB::raw(
                    'DATE(donations.created_at) as date'
                ),
                DB::raw(
                    'COUNT(donations.id) as donations'
                ),
                DB::raw(
                    'SUM(donations.amount) as amount'
                ),
            ])
            ->groupBy(
                DB::raw(
                    'DATE(donations.created_at)'
                )
            )
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'donations' => (int) $row->donations,
                    'amount' => (float) $row->amount,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Resultado
        |--------------------------------------------------------------------------
        */

        return Inertia::render(
            'Dashboard',
            [
                'filters' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d'),
                ],

                /*
                |--------------------------------------------------------------------------
                | KPIs de HOY
                |--------------------------------------------------------------------------
                */

                'today' => [
                    'sales' => $todaySales,
                    'income' => (float) $todayIncome,
                    'tickets' => (int) $todayTicketsSold,
                    'average_ticket' => $todayAverageTicket,
                    'donations' => $todayDonations,
                    'donations_amount' => (float) $todayDonationsAmount,
                ],

                /*
                |--------------------------------------------------------------------------
                | Información del periodo
                |--------------------------------------------------------------------------
                */

                'period' => [
                    'sales' => $salesTotal,
                    'income' => (float) $incomeTotal,
                    'tickets' => (int) $ticketsSold,
                    'donations' => $donationsQuery->count(
                        'donations.id'
                    ),
                    'donations_amount' => (float) $donationsQuery->sum(
                        'donations.amount'
                    ),
                ],

                'sales_by_day' => $salesByDay,

                'donations_by_day' => $donationsByDay,

                'tickets_by_type' => $ticketsByType,

                'payment_methods' => $paymentMethods,
            ]
        );
    }
}
