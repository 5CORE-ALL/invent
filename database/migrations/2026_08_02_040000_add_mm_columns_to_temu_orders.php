<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('temu_orders')) {
            return;
        }

        Schema::table('temu_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('temu_orders', 'shopify_order_id')) {
                $table->string('shopify_order_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('temu_orders', 'pushed_to_shopify_at')) {
                $table->timestamp('pushed_to_shopify_at')->nullable();
            }
            if (! Schema::hasColumn('temu_orders', 'import_status')) {
                $table->string('import_status', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('temu_orders', 'display_sku')) {
                $table->string('display_sku', 128)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_orders')) {
            return;
        }

        Schema::table('temu_orders', function (Blueprint $table) {
            foreach (['shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'display_sku'] as $col) {
                if (Schema::hasColumn('temu_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
