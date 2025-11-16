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
        Schema::create('ebay_3_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 100)->nullable();
            $table->string('sku', 50)->nullable();
            $table->integer('ebay_l30')->nullable();
            $table->integer('ebay_l60')->nullable();
            $table->decimal('ebay_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ebay_3_metrics');
    }
};
