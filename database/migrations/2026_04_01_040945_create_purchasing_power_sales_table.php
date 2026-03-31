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
        Schema::create('purchasing_power_sales', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable()->index();
            $table->dateTime('date_created')->nullable();
            $table->integer('quantity')->nullable();
            $table->text('product_name')->nullable();
            $table->string('status')->nullable()->index();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('product_sku')->nullable()->index();
            $table->string('offer_sku')->nullable();
            $table->string('brand')->nullable();
            $table->string('category_code')->nullable();
            $table->string('category_label')->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('shipping_price', 10, 2)->nullable();
            $table->string('commission_rule_name')->nullable();
            $table->decimal('commission_excl_tax', 10, 2)->nullable();
            $table->decimal('commission_incl_tax', 10, 2)->nullable();
            $table->decimal('amount_transferred', 10, 2)->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('tracking_url')->nullable();
            $table->string('customer_first_name')->nullable();
            $table->string('customer_last_name')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_state')->nullable();
            $table->string('customer_country')->nullable();
            $table->string('order_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchasing_power_sales');
    }
};
