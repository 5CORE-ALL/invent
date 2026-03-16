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
        Schema::table('fba_shipments', function (Blueprint $table) {
            // Drop the old unique constraint on shipment_id only
            $table->dropUnique('fba_shipments_shipment_id_unique');
            
            // Add composite unique constraint on shipment_id + sku
            $table->unique(['shipment_id', 'sku'], 'fba_shipments_shipment_sku_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fba_shipments', function (Blueprint $table) {
            // Reverse: drop composite unique and restore single unique
            $table->dropUnique('fba_shipments_shipment_sku_unique');
            $table->unique('shipment_id', 'fba_shipments_shipment_id_unique');
        });
    }
};
