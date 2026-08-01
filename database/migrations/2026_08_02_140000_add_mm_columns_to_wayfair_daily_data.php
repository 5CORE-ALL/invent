<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wayfair_daily_data')) {
            Schema::table('wayfair_daily_data', function (Blueprint $table) {
                if (! Schema::hasColumn('wayfair_daily_data', 'shopify_order_id')) {
                    $table->string('shopify_order_id')->nullable()->index();
                }
                if (! Schema::hasColumn('wayfair_daily_data', 'pushed_to_shopify_at')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable();
                }
                if (! Schema::hasColumn('wayfair_daily_data', 'import_status')) {
                    $table->string('import_status')->nullable()->index();
                }
                if (! Schema::hasColumn('wayfair_daily_data', 'raw_payload')) {
                    $table->json('raw_payload')->nullable();
                }
            });
        }

        if (Schema::hasTable('product_stock_mappings')
            && ! Schema::hasColumn('product_stock_mappings', 'inventory_wayfair')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->integer('inventory_wayfair')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wayfair_daily_data')) {
            Schema::table('wayfair_daily_data', function (Blueprint $table) {
                foreach (['shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'raw_payload'] as $col) {
                    if (Schema::hasColumn('wayfair_daily_data', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('product_stock_mappings')
            && Schema::hasColumn('product_stock_mappings', 'inventory_wayfair')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_wayfair');
            });
        }
    }
};
