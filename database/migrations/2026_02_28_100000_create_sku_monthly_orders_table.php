<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores SKU-wise order count per month (e.g. SKU abc sold 3 in Jan → order_count = 3 for that month).
     */
    public function up(): void
    {
        Schema::create('sku_monthly_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->index();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1 = Jan, 12 = Dec
            $table->unsignedInteger('order_count')->default(0)->comment('Total quantity sold for this SKU in this month');
            $table->timestamps();

            $table->unique(['sku', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_monthly_orders');
    }
};
