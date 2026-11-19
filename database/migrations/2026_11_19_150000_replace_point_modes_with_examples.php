<?php

use Database\Seeders\PointModeSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reemplaza los modos de puntos por las seis temporadas de ejemplo de
 * PointModeSeeder. Los movimientos ya otorgados conservan su historial
 * (point_mode_id queda en null).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // point_mode_rules se borra en cascada.
            DB::table('point_modes')->delete();

            // En una instalación nueva las reglas aún no existen: DatabaseSeeder
            // llama a PointModeSeeder después de PointRuleSeeder.
            if (DB::table('point_rules')->where('type', 'ticket_purchase')->exists()) {
                (new PointModeSeeder)->run();
            }
        });
    }

    /**
     * Quita solo los ejemplos; los modos borrados en up() no se pueden recuperar.
     */
    public function down(): void
    {
        DB::table('point_modes')
            ->whereIn('name', array_column(PointModeSeeder::MODES, 'name'))
            ->delete();
    }
};
