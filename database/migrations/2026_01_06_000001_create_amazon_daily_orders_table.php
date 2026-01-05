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
        Schema::create('amazon_daily_orders', function (Blueprint $table) {
            $table->id();
            $table->string('asin')->index();
            $table->string('sku')->nullable()->index();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('units_ordered')->default(0);
            $table->date('order_date');
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_daily_orders');
    }
};