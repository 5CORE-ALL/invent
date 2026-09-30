<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_fulfillment_manual_orders')
            || Schema::hasColumn('order_fulfillment_manual_orders', 'unit_price')) {
            return;
        }

        Schema::table('order_fulfillment_manual_orders', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_fulfillment_manual_orders')
            && Schema::hasColumn('order_fulfillment_manual_orders', 'unit_price')) {
            Schema::table('order_fulfillment_manual_orders', function (Blueprint $table) {
                $table->dropColumn('unit_price');
            });
        }
    }
};
