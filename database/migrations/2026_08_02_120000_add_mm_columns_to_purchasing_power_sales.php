<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchasing_power_sales')) {
            Schema::table('purchasing_power_sales', function (Blueprint $table) {
                if (! Schema::hasColumn('purchasing_power_sales', 'shopify_order_id')) {
                    $table->string('shopify_order_id')->nullable()->index();
                }
                if (! Schema::hasColumn('purchasing_power_sales', 'pushed_to_shopify_at')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable();
                }
                if (! Schema::hasColumn('purchasing_power_sales', 'import_status')) {
                    $table->string('import_status')->nullable()->index();
                }
                if (! Schema::hasColumn('purchasing_power_sales', 'raw_payload')) {
                    $table->json('raw_payload')->nullable();
                }
            });
        }

        if (Schema::hasTable('product_stock_mappings')
            && ! Schema::hasColumn('product_stock_mappings', 'inventory_purchasing_power')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->integer('inventory_purchasing_power')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchasing_power_sales')) {
            Schema::table('purchasing_power_sales', function (Blueprint $table) {
                foreach (['shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'raw_payload'] as $col) {
                    if (Schema::hasColumn('purchasing_power_sales', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('product_stock_mappings')
            && Schema::hasColumn('product_stock_mappings', 'inventory_purchasing_power')) {
            Schema::table('product_stock_mappings', function (Blueprint $table) {
                $table->dropColumn('inventory_purchasing_power');
            });
        }
    }
};
