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
        Schema::create('fba_orders', function (Blueprint $table) {
            $table->id();
            $table->string('amazon_order_id');
            $table->string('sku');
            $table->date('order_date');
            $table->date('dispatch_date')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fba_orders');
    }
};
