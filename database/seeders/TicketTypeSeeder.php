<?php

namespace Database\Seeders;

use App\Models\TicketType;
use Illuminate\Database\Seeder;

class TicketTypeSeeder extends Seeder
{
    public function run(): void
    {
        TicketType::updateOrCreate(
            ['name' => 'Adulto'],
            [
                'price' => 80.00,
                'description' => 'Boleto de entrada para adultos.',
                'is_active' => true,
            ]
        );

        TicketType::updateOrCreate(
            ['name' => 'Niño'],
            [
                'price' => 50.00,
                'description' => 'Boleto de entrada para niños.',
                'is_active' => true,
            ]
        );

        TicketType::updateOrCreate(
            ['name' => 'INAPAM'],
            [
                'price' => 40.00,
                'description' => 'Boleto de entrada con tarifa preferencial para personas con credencial INAPAM.',
                'is_active' => true,
            ]
        );
    }
}
