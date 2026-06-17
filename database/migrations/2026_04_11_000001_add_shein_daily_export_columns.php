<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shein order export added "Requested Shipping Time" and fee columns; align DB with current sheet.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return;
        }

        Schema::table('shein_daily_data', function (Blueprint $table) {
            if (! Schema::hasColumn('shein_daily_data', 'requested_shipping_time')) {
                $table->dateTime('requested_shipping_time')->nullable();
            }
            if (! Schema::hasColumn('shein_daily_data', 'fulfillment_service_fee')) {
                $table->decimal('fulfillment_service_fee', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('shein_daily_data', 'storage_fee')) {
                $table->decimal('storage_fee', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shein_daily_data')) {
            return;
        }

        Schema::table('shein_daily_data', function (Blueprint $table) {
            foreach (['requested_shipping_time', 'fulfillment_service_fee', 'storage_fee'] as $col) {
                if (Schema::hasColumn('shein_daily_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
