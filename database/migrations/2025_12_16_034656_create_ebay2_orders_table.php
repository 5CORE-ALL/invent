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
        Schema::create('ebay2_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ebay_order_id')->unique();
            $table->datetime('order_date')->nullable();
            $table->string('status')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('period')->nullable();
            $table->longText('raw_data')->nullable();
            $table->timestamps();
            
            $table->index('period');
            $table->index('order_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay2_orders');
    }
};
