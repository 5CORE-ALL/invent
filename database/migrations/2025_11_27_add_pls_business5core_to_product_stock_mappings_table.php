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
        Schema::table('product_stock_mappings', function (Blueprint $table) {
            // Add PLS inventory column
            if (!Schema::hasColumn('product_stock_mappings', 'inventory_pls')) {
                $table->integer('inventory_pls')->nullable();
            }
            
            // Add Business5Core inventory column
            if (!Schema::hasColumn('product_stock_mappings', 'inventory_business5core')) {
                $table->integer('inventory_business5core')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_stock_mappings', function (Blueprint $table) {
            $table->dropColumn(['inventory_pls', 'inventory_business5core']);
        });
    }
};
