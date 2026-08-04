<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_products_two') && ! Schema::hasColumn('tiktok_products_two', 'sku_id')) {
            Schema::table('tiktok_products_two', function (Blueprint $table) {
                $table->string('sku_id', 64)->nullable()->after('product_id')->index();
            });
        }

        if (Schema::hasTable('tiktok2_orders')) {
            Schema::table('tiktok2_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('tiktok2_orders', 'shopify_order_id')) {
                    $table->string('shopify_order_id', 64)->nullable()->after('raw_json')->index();
                }
                if (! Schema::hasColumn('tiktok2_orders', 'import_status')) {
                    $table->string('import_status', 32)->nullable()->after('shopify_order_id')->index();
                }
                if (! Schema::hasColumn('tiktok2_orders', 'pushed_to_shopify_at')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable()->after('import_status');
                }
                if (! Schema::hasColumn('tiktok2_orders', 'tracking_pushed_at')) {
                    $table->timestamp('tracking_pushed_at')->nullable()->after('pushed_to_shopify_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiktok_products_two') && Schema::hasColumn('tiktok_products_two', 'sku_id')) {
            Schema::table('tiktok_products_two', function (Blueprint $table) {
                $table->dropColumn('sku_id');
            });
        }

        if (Schema::hasTable('tiktok2_orders')) {
            Schema::table('tiktok2_orders', function (Blueprint $table) {
                $cols = ['shopify_order_id', 'import_status', 'pushed_to_shopify_at', 'tracking_pushed_at'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tiktok2_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
