<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Order Status" column on /order-fulfillment: the status each marketplace itself reports for an order,
 * saved right after the twice-daily marketplace fetch (9:00 and 14:00 California time) or the
 * "Fetch tracking status from marketplace" button, together with the time it was fetched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_fulfillment_marketplace_statuses')) {
            return;
        }

        Schema::create('order_fulfillment_marketplace_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('mm_slug', 64);
            $table->string('order_id', 128);
            $table->string('order_status', 191)->nullable();
            // Delivered / InTransit / OutForDelivery when the marketplace's own words say so, else null.
            $table->string('tracking_status', 32)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['mm_slug', 'order_id'], 'ofms_slug_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_marketplace_statuses');
    }
};
