<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Usuario que realiza la donación
            |--------------------------------------------------------------------------
            */
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Vendedor / usuario de taquilla que registra la donación
            |--------------------------------------------------------------------------
            */
            $table->foreignId('seller_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Método de pago
            |--------------------------------------------------------------------------
            |
            | Cada donación tiene UN solo método de pago.
            |
            */
            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Monto
            |--------------------------------------------------------------------------
            */
            $table->decimal('amount', 10, 2);

            /*
            |--------------------------------------------------------------------------
            | Referencia
            |--------------------------------------------------------------------------
            |
            | Obligatoria para algunos métodos como transferencia o Mercado Pago,
            | pero opcional a nivel de base de datos.
            |
            */
            $table->string('reference')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */
            $table->string('status')
                ->default('completed');

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Índices
            |--------------------------------------------------------------------------
            */
            $table->index('user_id');
            $table->index('seller_id');
            $table->index('payment_method_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
