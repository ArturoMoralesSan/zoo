<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que ven los visitantes de un modo de puntos ("temporada"). El nombre y
 * la descripción del modo son internos; solo se muestra el aviso público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_modes', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
            $table->string('public_title', 80)->nullable()->after('is_public');
            $table->string('public_message', 300)->nullable()->after('public_title');
        });
    }

    public function down(): void
    {
        Schema::table('point_modes', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_title', 'public_message']);
        });
    }
};
