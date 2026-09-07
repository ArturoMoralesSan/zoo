<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $paymentMethods = PaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'admin/payment-methods/Index',
            [
                'paymentMethods' => $paymentMethods,
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'admin/payment-methods/Create'
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
            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:payment_methods,code',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        PaymentMethod::create($validated);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with(
                'success',
                'Método de pago creado correctamente.'
            );
    }

    public function edit(PaymentMethod $paymentMethod): Response
    {
        return Inertia::render(
            'admin/payment-methods/Edit',
            [
                'paymentMethod' => $paymentMethod,
            ]
        );
    }

    public function update(
        Request $request,
        PaymentMethod $paymentMethod
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                'unique:payment_methods,code,' .
                    $paymentMethod->id,
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'boolean',
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        $paymentMethod->update($validated);

        return redirect()
            ->route('admin.payment-methods.index')
            ->with(
                'success',
                'Método de pago actualizado correctamente.'
            );
    }

    public function destroy(
        PaymentMethod $paymentMethod
    ): RedirectResponse {
        if ($paymentMethod->ticketOrders()->exists()) {
            return back()->with(
                'error',
                'No se puede eliminar este método de pago porque tiene órdenes asociadas.'
            );
        }

        $paymentMethod->delete();

        return redirect()
            ->route('admin.payment-methods.index')
            ->with(
                'success',
                'Método de pago eliminado correctamente.'
            );
    }
}