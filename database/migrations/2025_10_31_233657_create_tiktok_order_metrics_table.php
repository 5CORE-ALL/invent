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
        Schema::create('tiktok_order_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('order_date');
            $table->string('status');
            $table->decimal('amount', 10, 2);
            $table->string('display_sku');
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('order_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiktok_order_metrics');
    }
};
