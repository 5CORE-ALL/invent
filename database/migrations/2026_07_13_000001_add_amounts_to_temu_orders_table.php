<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actual per-sub-order amounts from Temu bg.order.amount.query (the "like Amazon"
     * fix — Amazon stores real order price; this stores Temu's real order amount so
     * reported sales stop relying on catalog price × qty).
     */
    public function up(): void
    {
        if (! Schema::hasTable('temu_orders')) {
            return;
        }

        Schema::table('temu_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('temu_orders', 'order_base_amount')) {
                // Base-price sales portion for this sub-order (matches Temu "Base price sales").
                $table->decimal('order_base_amount', 12, 2)->nullable()->after('canceled_quantity_before_shipment');
            }
            if (! Schema::hasColumn('temu_orders', 'order_total_amount')) {
                // Total amount incl. shipping/tax for this sub-order.
                $table->decimal('order_total_amount', 12, 2)->nullable()->after('order_base_amount');
            }
            if (! Schema::hasColumn('temu_orders', 'amount_raw_json')) {
                // Raw bg.order.amount.query result kept for auditing / re-parsing.
                $table->longText('amount_raw_json')->nullable()->after('raw_json');
            }
            if (! Schema::hasColumn('temu_orders', 'amount_fetched_at')) {
                $table->timestamp('amount_fetched_at')->nullable()->after('amount_raw_json');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('temu_orders')) {
            return;
        }

        Schema::table('temu_orders', function (Blueprint $table) {
            foreach (['order_base_amount', 'order_total_amount', 'amount_raw_json', 'amount_fetched_at'] as $col) {
                if (Schema::hasColumn('temu_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
