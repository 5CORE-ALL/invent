<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shopify_orders')) {
            return;
        }

        Schema::create('shopify_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shopify_order_id')->unique();
            $table->unsignedBigInteger('shopify_customer_id')->nullable()->index();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('order_status', 64)->nullable()->index();
            $table->timestamp('order_date')->nullable()->index();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('shopify_customer_id')
                ->references('shopify_customer_id')
                ->on('shopify_customers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_orders');
    }
};
