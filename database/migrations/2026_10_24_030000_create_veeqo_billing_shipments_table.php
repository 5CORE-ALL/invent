<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Veeqo shipment. Veeqo has no billing endpoint; what it bills is the
 * label postage, surcharges and carrier fees on each order's allocation shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('veeqo_billing_shipments')) {
            return;
        }

        Schema::create('veeqo_billing_shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id')->unique();
            $table->unsignedBigInteger('allocation_id')->nullable();
            $table->unsignedBigInteger('order_id')->index();
            $table->string('order_number', 100)->nullable()->index();
            $table->string('order_status', 50)->nullable();
            $table->string('channel_name')->nullable();
            $table->string('channel_type', 100)->nullable();
            $table->string('warehouse_name')->nullable();
            $table->timestamp('order_created_at')->nullable()->index();
            $table->timestamp('order_shipped_at')->nullable();
            $table->timestamp('shipment_created_at')->nullable()->index();
            $table->timestamp('outbound_shipment_date')->nullable();

            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('carrier_slug', 100)->nullable();
            $table->string('provider_type', 100)->nullable();
            $table->string('sub_carrier_id', 100)->nullable()->index();
            $table->string('service_type')->nullable();
            $table->string('service_name')->nullable();
            $table->string('short_service_name')->nullable();
            $table->string('service_carrier_name')->nullable();

            $table->string('tracking_number', 100)->nullable()->index();
            $table->string('tracking_status', 50)->nullable();
            $table->boolean('tracking_billed')->nullable();
            $table->boolean('tracking_cancelled')->nullable();
            $table->boolean('tracking_used')->nullable();
            $table->timestamp('in_transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_to_sender_at')->nullable();

            $table->decimal('label_cost', 12, 2)->nullable();
            $table->string('label_currency', 10)->nullable();
            $table->decimal('surcharge_total', 12, 2)->nullable();
            $table->decimal('carrier_fee_total', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->decimal('insured_value', 12, 2)->nullable();
            $table->decimal('shipment_weight', 14, 4)->nullable();
            $table->decimal('allocation_weight', 14, 4)->nullable();
            $table->string('weight_unit', 20)->nullable();
            $table->boolean('has_return_label')->nullable();
            $table->string('shipped_by')->nullable();

            $table->decimal('order_total_price', 12, 2)->nullable();
            $table->decimal('order_subtotal_price', 12, 2)->nullable();
            $table->decimal('order_delivery_cost', 12, 2)->nullable();
            $table->string('order_currency', 10)->nullable();
            $table->string('delivery_method')->nullable();
            $table->unsignedInteger('line_item_count')->nullable();
            $table->string('skus', 1000)->nullable();

            $table->json('charges')->nullable();
            $table->json('carrier_fees')->nullable();
            $table->json('raw_shipment')->nullable();
            $table->timestamp('veeqo_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veeqo_billing_shipments');
    }
};
