<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reverb_order_metrics')) {
            return;
        }

        if (! Schema::hasColumn('reverb_order_metrics', 'shopify_order_id')) {
            Schema::table('reverb_order_metrics', function (Blueprint $table) {
                if (Schema::hasColumn('reverb_order_metrics', 'order_number')) {
                    $table->string('shopify_order_id')->nullable()->after('order_number');
                } else {
                    $table->string('shopify_order_id')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('reverb_order_metrics', 'pushed_to_shopify_at')) {
            Schema::table('reverb_order_metrics', function (Blueprint $table) {
                if (Schema::hasColumn('reverb_order_metrics', 'shopify_order_id')) {
                    $table->timestamp('pushed_to_shopify_at')->nullable()->after('shopify_order_id');
                } else {
                    $table->timestamp('pushed_to_shopify_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reverb_order_metrics')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('reverb_order_metrics', 'shopify_order_id') ? 'shopify_order_id' : null,
            Schema::hasColumn('reverb_order_metrics', 'pushed_to_shopify_at') ? 'pushed_to_shopify_at' : null,
        ]));

        if ($columns !== []) {
            Schema::table('reverb_order_metrics', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
