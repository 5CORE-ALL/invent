<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing column if table exists but column doesn't
        if (Schema::hasTable('shopify_inventory_logs')) {
            if (!Schema::hasColumn('shopify_inventory_logs', 'quantity_adjustment')) {
                Schema::table('shopify_inventory_logs', function (Blueprint $table) {
                    $table->integer('quantity_adjustment')->after('sku');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shopify_inventory_logs', 'quantity_adjustment')) {
            Schema::table('shopify_inventory_logs', function (Blueprint $table) {
                $table->dropColumn('quantity_adjustment');
            });
        }
    }
};
