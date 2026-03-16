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
        Schema::create('bestbuy_price_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('offer_sku')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('category_code')->nullable();
            $table->string('category_label')->nullable();
            $table->string('brand')->nullable();
            $table->text('product_name')->nullable();
            $table->string('offer_state')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('alert_threshold')->nullable();
            $table->string('logistic_class')->nullable();
            $table->boolean('activated')->default(false);
            $table->date('available_start_date')->nullable();
            $table->date('available_end_date')->nullable();
            $table->boolean('favorite_offer')->default(false);
            $table->string('product_tax_code')->nullable();
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->date('discount_start_date')->nullable();
            $table->date('discount_end_date')->nullable();
            $table->integer('lead_time_to_ship')->nullable();
            $table->string('gtin')->nullable();
            $table->string('inactivity_reason')->nullable();
            $table->string('fulfillment_center_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bestbuy_price_data');
    }
};
