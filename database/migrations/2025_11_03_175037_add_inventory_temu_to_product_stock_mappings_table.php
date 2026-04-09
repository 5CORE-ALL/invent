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
        if (! Schema::hasTable('product_stock_mappings')) {
            return;
        }
        if (Schema::hasColumn('product_stock_mappings', 'inventory_temu')) {
            return;
        }

        Schema::table('product_stock_mappings', function (Blueprint $table) {
            $table->integer('inventory_temu')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_stock_mappings')) {
            return;
        }
        if (! Schema::hasColumn('product_stock_mappings', 'inventory_temu')) {
            return;
        }

        Schema::table('product_stock_mappings', function (Blueprint $table) {
            $table->dropColumn('inventory_temu');
        });
    }
};
