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
        Schema::create('ebay_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ebay_order_id')->unique();
            $table->datetime('order_date');
            $table->string('status');
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->string('period'); // l30 or l60
            $table->json('raw_data'); // Store full order data
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_orders');
    }
};
