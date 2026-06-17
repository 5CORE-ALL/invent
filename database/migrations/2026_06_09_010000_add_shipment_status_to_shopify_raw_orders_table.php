<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_raw_orders', function (Blueprint $table) {
            // Normalized shipment status pulled from the tracking provider
            // (e.g. InfoReceived, InTransit, OutForDelivery, Delivered, Exception, Expired, Pending, NotFound)
            $table->string('shipment_status')->nullable()->after('tracking_url');
            // Latest tracking event / sub-status text
            $table->string('shipment_status_detail', 500)->nullable()->after('shipment_status');
            // When we last queried the tracking provider for this tracking number
            $table->timestamp('shipment_checked_at')->nullable()->after('shipment_status_detail');

            $table->index('tracking_number', 'idx_tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_raw_orders', function (Blueprint $table) {
            $table->dropIndex('idx_tracking_number');
            $table->dropColumn(['shipment_status', 'shipment_status_detail', 'shipment_checked_at']);
        });
    }
};
