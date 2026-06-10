<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_orders')) {
            return;
        }

        Schema::create('newegg_orders', function (Blueprint $table) {
            $table->id();

            $table->string('seller_id', 20)->nullable()->index();
            $table->string('order_number', 30)->unique();
            $table->string('seller_order_number', 60)->nullable();
            $table->string('invoice_number', 30)->nullable();
            $table->boolean('order_downloaded')->default(false);

            $table->timestamp('order_date')->nullable();
            $table->timestamp('auto_void_time')->nullable();

            $table->tinyInteger('order_status')->nullable()->index();
            $table->string('order_status_description', 50)->nullable();

            // Customer
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone_number', 50)->nullable();
            $table->string('customer_email_address', 191)->nullable();

            $table->date('on_time_ship_due_date')->nullable();
            $table->date('deliver_due_date')->nullable();

            // Ship-to
            $table->string('ship_to_first_name', 100)->nullable();
            $table->string('ship_to_last_name', 100)->nullable();
            $table->string('ship_to_company', 150)->nullable();
            $table->string('ship_to_address1', 255)->nullable();
            $table->string('ship_to_address2', 255)->nullable();
            $table->string('ship_to_city_name', 100)->nullable();
            $table->string('ship_to_state_code', 50)->nullable();
            $table->string('ship_to_zip_code', 20)->nullable();
            $table->string('ship_to_country_code', 60)->nullable();
            $table->string('ship_service', 150)->nullable();
            $table->boolean('signature_required')->nullable();

            // Money
            $table->string('currency_code', 10)->nullable();
            $table->decimal('order_item_amount', 12, 2)->nullable();
            $table->decimal('shipping_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->decimal('sales_tax', 12, 2)->nullable();
            $table->decimal('order_total_amount', 12, 2)->nullable();
            $table->integer('order_qty')->nullable();

            $table->boolean('is_auto_void')->nullable();
            $table->tinyInteger('sales_channel')->nullable();
            $table->tinyInteger('fulfillment_option')->nullable();

            $table->json('raw_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_orders');
    }
};
