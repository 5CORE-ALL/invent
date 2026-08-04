<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_products') && ! Schema::hasColumn('tiktok_products', 'sku_id')) {
            Schema::table('tiktok_products', function (Blueprint $table) {
                $table->string('sku_id')->nullable()->after('sku');
            });
        }

        if (Schema::hasTable('tiktok_orders')) {
            Schema::table('tiktok_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('tiktok_orders', 'shopify_order_id')) {
                    $table->string('shopify_order_id')->nullable()->after('fetched_at');
                }
                if (! Schema::hasColumn('tiktok_orders', 'import_status')) {
                    $table->string('import_status')->nullable()->after('shopify_order_id');
                }
                if (! Schema::hasColumn('tiktok_orders', 'pushed_to_shopify_at')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable()->after('import_status');
                }
                if (! Schema::hasColumn('tiktok_orders', 'tracking_pushed_at')) {
                    $table->timestamp('tracking_pushed_at')->nullable()->after('pushed_to_shopify_at');
                }
                if (! Schema::hasColumn('tiktok_orders', 'line_item_id')) {
                    $table->string('line_item_id')->nullable()->after('order_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tiktok_products') && Schema::hasColumn('tiktok_products', 'sku_id')) {
            Schema::table('tiktok_products', function (Blueprint $table) {
                $table->dropColumn('sku_id');
            });
        }

        if (Schema::hasTable('tiktok_orders')) {
            Schema::table('tiktok_orders', function (Blueprint $table) {
                $cols = ['shopify_order_id', 'import_status', 'pushed_to_shopify_at', 'tracking_pushed_at'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tiktok_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
