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
        if (Schema::hasTable('faire_daily_data')) {
            return;
        }

        Schema::create('faire_daily_data', function (Blueprint $table) {
            $table->id();
            $table->timestamp('order_date')->nullable()->index();
            $table->string('order_number')->nullable()->index();
            $table->string('purchase_order_number')->nullable();
            $table->string('retailer_name')->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            $table->text('product_name')->nullable();
            $table->string('option_name')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('gtin')->nullable();
            $table->string('status')->nullable()->index();
            $table->integer('quantity')->default(0);
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->timestamp('ship_date')->nullable();
            $table->timestamp('scheduled_order_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faire_daily_data');
    }
};
