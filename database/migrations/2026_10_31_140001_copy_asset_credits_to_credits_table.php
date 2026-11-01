<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copia los créditos de asset_credits (uno por recurso) a credits como
     * créditos de origen. "Generado a partir de la ilustración"
     * (derived_from_id) pasa a based_on_asset = artwork.
     *
     * asset_credits no se modifica ni se borra.
     */
    public function up(): void
    {
        if (! Schema::hasTable('asset_credits')) {
            return;
        }

        DB::table('asset_credits')->orderBy('id')->each(function (object $credit) {
            DB::table('credits')->insert([
                'creditable_type' => $credit->creditable_type,
                'creditable_id' => $credit->creditable_id,
                'asset' => $credit->asset,
                'role' => 'origin',
                'sort_order' => 0,
                'license_id' => $credit->license_id,
                'title' => $credit->title,
                'author_name' => $credit->author_name,
                'author_url' => $credit->author_url,
                'source_name' => $credit->source_name,
                'source_url' => $credit->source_url,
                'acquired_at' => $credit->acquired_at,
                'modified' => $credit->modified,
                'modification_note' => $credit->modification_note,
                'attribution_override' => $credit->attribution_override,
                'based_on_asset' => $credit->derived_from_id !== null ? 'artwork' : null,
                'notes' => $credit->notes,
                'created_at' => $credit->created_at,
                'updated_at' => $credit->updated_at,
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('credits')->delete();
    }
};
