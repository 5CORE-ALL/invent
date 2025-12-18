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
        Schema::create('mirakl_daily_data', function (Blueprint $table) {
            $table->id();
            
            // Channel identification
            $table->string('channel_name', 100)->index(); // Macy's, Inc., Tiendamia, Best Buy USA
            $table->string('channel_id', 50)->nullable();
            
            // Order info
            $table->string('order_id', 100)->index();
            $table->string('channel_order_id', 100)->nullable();
            $table->string('order_line_id', 100);
            $table->string('status', 50)->nullable();
            $table->timestamp('order_created_at')->nullable();
            $table->timestamp('order_updated_at')->nullable();
            
            // Period for filtering (l30/l60)
            $table->string('period', 10)->index();
            
            // Product info
            $table->string('sku', 100)->index();
            $table->string('product_title', 500)->nullable();
            $table->integer('quantity')->default(1);
            
            // Pricing
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->decimal('shipping_price', 10, 2)->nullable();
            $table->decimal('shipping_tax', 10, 2)->nullable();
            
            // Billing info
            $table->string('billing_first_name', 100)->nullable();
            $table->string('billing_last_name', 100)->nullable();
            $table->string('billing_street', 255)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 50)->nullable();
            $table->string('billing_zip', 20)->nullable();
            $table->string('billing_country', 10)->nullable();
            
            // Shipping info
            $table->string('shipping_first_name', 100)->nullable();
            $table->string('shipping_last_name', 100)->nullable();
            $table->string('shipping_street', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 50)->nullable();
            $table->string('shipping_zip', 20)->nullable();
            $table->string('shipping_country', 10)->nullable();
            $table->string('shipping_carrier', 50)->nullable();
            $table->string('shipping_method', 100)->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicates
            $table->unique(['order_id', 'order_line_id'], 'mirakl_order_line_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mirakl_daily_data');
    }
};
