<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_b2b_daily_data', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->nullable()->index();
            $table->string('order_number')->nullable();
            $table->string('line_item_id')->nullable();
            $table->string('product_id')->nullable();
            $table->string('variant_id')->nullable();
            $table->timestamp('order_date')->nullable()->index();
            $table->string('financial_status')->nullable();
            $table->string('fulfillment_status')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('product_title', 500)->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('tracking_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('source_name')->nullable();
            $table->text('tags')->nullable();
            $table->string('period', 10)->nullable()->index();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['order_id', 'line_item_id'], 'unique_b2b_order_line_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_b2b_daily_data');
    }
};
