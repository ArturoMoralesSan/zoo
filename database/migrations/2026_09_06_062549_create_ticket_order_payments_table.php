<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_order_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_order_id')
                ->constrained('ticket_orders')
                ->cascadeOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained('payment_methods')
                ->restrictOnDelete();

            $table->decimal('amount', 10, 2);

            $table->string('reference')->nullable();

            $table->timestamps();

            $table->index('ticket_order_id');
            $table->index('payment_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_order_payments');
    }
};
