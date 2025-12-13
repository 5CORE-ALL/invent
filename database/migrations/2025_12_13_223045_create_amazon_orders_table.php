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
        Schema::create('amazon_orders', function (Blueprint $table) {
            $table->id();
            $table->string('amazon_order_id')->unique();
            $table->dateTime('order_date');
            $table->string('status')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('period', 10); // l30, l60
            $table->json('raw_data')->nullable();
            $table->timestamps();
            
            $table->index('period');
            $table->index('order_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_orders');
    }
};
