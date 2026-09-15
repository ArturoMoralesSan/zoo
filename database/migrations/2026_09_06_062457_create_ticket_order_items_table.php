<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_order_id')
                ->constrained('ticket_orders')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 10, 2);

            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            $table->index('ticket_order_id');
            $table->index('ticket_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_order_items');
    }
};
