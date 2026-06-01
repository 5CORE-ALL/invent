<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L7 sales export / analytics snapshot table (denormalized columns for quick export).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu_daily_data_l7')) {
            return;
        }

        Schema::create('temu_daily_data_l7', function (Blueprint $table) {
            $table->id();
            $table->string('contribution_sku')->nullable();
            $table->integer('quantity_purchased')->default(0);
            $table->decimal('base_price_total', 10, 2)->default(0);
            $table->string('order_id')->nullable();
            $table->text('product_name_by_customer_order')->nullable();
            $table->string('variation')->nullable();
            $table->integer('quantity_shipped')->default(0);
            $table->integer('quantity_to_ship')->default(0);
            $table->decimal('temu_ship', 10, 2)->default(0);
            $table->decimal('lp', 10, 2)->default(0);
            $table->string('order_status')->nullable();
            $table->string('fulfillment_mode')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->string('parent')->nullable();
            $table->timestamps();

            $table->index('contribution_sku');
            $table->index('order_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temu_daily_data_l7');
    }
};
