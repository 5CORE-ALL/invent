<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Channel-agnostic carrier status cache (USPS / UPS / FedEx / 17TRACK).
 * SOF must not rely on Shopify fulfillments for tracking or status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carrier_tracking_statuses')) {
            return;
        }

        Schema::create('carrier_tracking_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 128);
            $table->string('carrier', 128)->nullable();
            $table->string('shipment_status', 64)->nullable();
            $table->string('shipment_status_detail', 512)->nullable();
            $table->timestamp('shipment_checked_at')->nullable();
            $table->timestamps();

            $table->unique('tracking_number', 'cts_tracking_number_unique');
            $table->index(['shipment_status', 'shipment_checked_at'], 'cts_status_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_tracking_statuses');
    }
};
