<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Efectivo',
                'code' => 'cash',
                'description' => 'Pago realizado en efectivo.',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Tarjeta',
                'code' => 'card',
                'description' => 'Pago realizado mediante tarjeta de débito o crédito.',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Transferencia',
                'code' => 'transfer',
                'description' => 'Pago realizado mediante transferencia bancaria.',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mercado Pago',
                'code' => 'mercadopago',
                'description' => 'Pago realizado mediante Mercado Pago.',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($paymentMethods as $paymentMethod) {
            PaymentMethod::updateOrCreate(
                [
                    'code' => $paymentMethod['code'],
                ],
                $paymentMethod
            );
        }
    }
}