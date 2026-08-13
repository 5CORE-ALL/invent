<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_orders')) {
            return;
        }

        Schema::table('amazon_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_orders', 'shopify_order_id')) {
                $table->string('shopify_order_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('amazon_orders', 'pushed_to_shopify_at')) {
                $table->timestamp('pushed_to_shopify_at')->nullable();
            }
            if (! Schema::hasColumn('amazon_orders', 'import_status')) {
                $table->string('import_status', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('amazon_orders', 'fulfillment_channel')) {
                $table->string('fulfillment_channel', 16)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_orders')) {
            return;
        }

        Schema::table('amazon_orders', function (Blueprint $table) {
            foreach (['shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'fulfillment_channel'] as $col) {
                if (Schema::hasColumn('amazon_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
