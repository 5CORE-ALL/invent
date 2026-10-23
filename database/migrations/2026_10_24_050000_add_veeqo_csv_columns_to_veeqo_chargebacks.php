<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Veeqo bills its own labels (all channels) to the Veeqo card; those chargebacks only exist in the
 * Veeqo shipping-transactions CSV, so rows now carry a source (amazon_api | veeqo_csv).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('veeqo_chargebacks') || Schema::hasColumn('veeqo_chargebacks', 'source')) {
            return;
        }

        Schema::table('veeqo_chargebacks', function (Blueprint $table) {
            $table->string('source', 20)->default('amazon_api')->after('transaction_id')->index();
            $table->string('order_number', 100)->nullable()->after('amazon_order_id')->index();
            $table->string('reason', 150)->nullable()->after('description');
            $table->string('carrier', 100)->nullable()->after('item_types');
            $table->string('service', 150)->nullable()->after('carrier');
            $table->string('payment_status', 50)->nullable()->after('service');
            $table->string('payment_method', 100)->nullable()->after('payment_status');
        });

        DB::table('veeqo_chargebacks')->whereNull('order_number')->update(['order_number' => DB::raw('amazon_order_id')]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('veeqo_chargebacks', 'source')) {
            return;
        }
        Schema::table('veeqo_chargebacks', function (Blueprint $table) {
            $table->dropColumn(['source', 'order_number', 'reason', 'carrier', 'service', 'payment_status', 'payment_method']);
        });
    }
};
