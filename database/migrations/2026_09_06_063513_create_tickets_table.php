<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_order_id')
                ->constrained('ticket_orders')
                ->cascadeOnDelete();

            $table->foreignId('ticket_order_item_id')
                ->constrained('ticket_order_items')
                ->cascadeOnDelete();

            $table->foreignId('ticket_type_id')
                ->constrained('ticket_types')
                ->restrictOnDelete();

            $table->uuid('qr_token')->unique();

            $table->string('status')->default('active');

            $table->timestamp('used_at')->nullable();

            $table->foreignId('validated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('ticket_order_id');
            $table->index('ticket_order_item_id');
            $table->index('ticket_type_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
