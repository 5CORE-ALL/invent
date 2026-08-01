<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topdawg_order_metrics')) {
            return;
        }

        Schema::table('topdawg_order_metrics', function (Blueprint $table) {
            if (! Schema::hasColumn('topdawg_order_metrics', 'order_id')) {
                $table->string('order_id', 64)->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'product_id')) {
                $table->string('product_id', 64)->nullable()->after('sku');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'display_title')) {
                $table->string('display_title', 512)->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'shopify_order_id')) {
                $table->string('shopify_order_id', 64)->nullable()->index()->after('quantity');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'pushed_to_shopify_at')) {
                $table->timestamp('pushed_to_shopify_at')->nullable()->after('shopify_order_id');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'import_status')) {
                $table->string('import_status', 32)->nullable()->index()->after('pushed_to_shopify_at');
            }
            if (! Schema::hasColumn('topdawg_order_metrics', 'raw_payload')) {
                $table->json('raw_payload')->nullable()->after('import_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('topdawg_order_metrics')) {
            return;
        }

        Schema::table('topdawg_order_metrics', function (Blueprint $table) {
            foreach ([
                'order_id', 'product_id', 'display_title',
                'shopify_order_id', 'pushed_to_shopify_at', 'import_status', 'raw_payload',
            ] as $col) {
                if (Schema::hasColumn('topdawg_order_metrics', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
